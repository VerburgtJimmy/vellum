/**
 * Search in the browser: the same ranking Vellum\Answers\Ranker runs in PHP,
 * over the index vellum:build wrote. Loaded on demand when the dialog opens.
 *
 * Keep this in step with Bm25.php, Synonyms.php and Ranker.php. The numbers
 * below are theirs.
 */

const K1 = 1.2
const B = 0.7
const DELTA = 0.5
const PREFIX_WEIGHT = 0.375
const FIELD_BOOST = { title: 3, heading: 2, text: 1 }

const EXACT_BOOST = 1
const STEM_WEIGHT = 1
const SYNONYM_WEIGHT = 0.5
const PER_PAGE = 2

let loaded = null

/**
 * @param {string} text
 * @returns {Array<string>}
 */
export function terms(text) {
  return text.toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean)
}

const VOWELS = new Set(['a', 'e', 'i', 'o', 'u'])

function consonant(word, i) {
  if (VOWELS.has(word[i])) {
    return false
  }

  return word[i] === 'y' ? i === 0 || !consonant(word, i - 1) : true
}

function measure(word) {
  let m = 0
  let vowel = false

  for (let i = 0; i < word.length; i++) {
    if (!consonant(word, i)) {
      vowel = true
    } else if (vowel) {
      m++
      vowel = false
    }
  }

  return m
}

function hasVowel(word) {
  for (let i = 0; i < word.length; i++) {
    if (!consonant(word, i)) {
      return true
    }
  }

  return false
}

function endsCvc(word) {
  const n = word.length

  return n >= 3 && consonant(word, n - 3) && !consonant(word, n - 2) && consonant(word, n - 1) && !'wxy'.includes(word[n - 1])
}

function tidy(stem) {
  if (stem.endsWith('at') || stem.endsWith('bl') || stem.endsWith('iz')) {
    return `${stem}e`
  }

  const n = stem.length

  if (n >= 2 && stem[n - 1] === stem[n - 2] && consonant(stem, n - 1) && !'lsz'.includes(stem[n - 1])) {
    return stem.slice(0, -1)
  }

  if (measure(stem) === 1 && endsCvc(stem)) {
    return `${stem}e`
  }

  return stem
}

/**
 * Steps 1 and 5a of the Porter stemmer. Mirrors Vellum\Answers\Stemmer.
 *
 * @param {string} input
 */
export function stem(input) {
  let word = input

  if (word.length <= 2 || !/^[a-z]+$/.test(word)) {
    return word
  }

  if (word.endsWith('sses') || word.endsWith('ies')) {
    word = word.slice(0, -2)
  } else if (!word.endsWith('ss') && word.endsWith('s')) {
    word = word.slice(0, -1)
  }

  if (word.endsWith('eed')) {
    if (measure(word.slice(0, -3)) > 0) {
      word = word.slice(0, -1)
    }
  } else {
    for (const ending of ['ed', 'ing']) {
      const base = word.slice(0, -ending.length)

      if (word.endsWith(ending) && hasVowel(base)) {
        word = tidy(base)
        break
      }
    }
  }

  if (word.endsWith('y') && hasVowel(word.slice(0, -1))) {
    word = `${word.slice(0, -1)}i`
  }

  if (word.endsWith('e')) {
    const base = word.slice(0, -1)
    const m = measure(base)

    if (m > 1 || (m === 1 && !endsCvc(base))) {
      word = base
    }
  }

  return word
}

/**
 * Lowercase, punctuation to spaces, keeping dots, dashes, slashes and
 * underscores inside words. Mirrors Synonyms::normalize().
 *
 * @param {string} text
 */
export function normalizePhrase(text) {
  return ` ${text.toLowerCase()} `
    .replace(/[^\p{L}\p{N}.\-/_]+/gu, ' ')
    .replace(/(?<![\p{L}\p{N}])[.\-/_]+|[.\-/_]+(?![\p{L}\p{N}])/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

/**
 * Build the inverted index one field at a time.
 *
 * @param {Array<Record<string, string>>} documents
 */
export function createIndex(documents) {
  const fields = {}

  for (const field of Object.keys(FIELD_BOOST)) {
    const postings = new Map()
    const lengths = new Array(documents.length).fill(0)
    let total = 0

    documents.forEach((document, id) => {
      const list = terms(document[field] ?? '')
      lengths[id] = list.length
      total += list.length

      // As written and by its stem, as Bm25.php indexes it.
      for (const term of list) {
        const root = stem(term)

        for (const key of root === term ? [term] : [term, root]) {
          let docs = postings.get(key)

          if (docs === undefined) {
            docs = new Map()
            postings.set(key, docs)
          }

          docs.set(id, (docs.get(id) ?? 0) + 1)
        }
      }
    })

    fields[field] = {
      postings,
      lengths,
      average: documents.length > 0 ? Math.max(1, total / documents.length) : 1,
      terms: [...postings.keys()],
    }
  }

  return { fields, count: documents.length }
}

/**
 * BM25+ with prefix matching, as PHP scores it.
 *
 * @param {ReturnType<typeof createIndex>} index
 * @param {Map<string, number>} weights  term => weight
 * @returns {Map<number, number>}
 */
export function score(index, weights) {
  const scores = new Map()

  for (const [query, weight] of weights) {
    for (const [field, boost] of Object.entries(FIELD_BOOST)) {
      const { postings, lengths, average, terms: all } = index.fields[field]

      for (const term of all) {
        let match

        if (term === query) {
          match = 1
        } else if (term.startsWith(query)) {
          match = (PREFIX_WEIGHT * query.length) / (query.length + 0.3 * (term.length - query.length))
        } else {
          continue
        }

        const docs = postings.get(term)
        const idf = Math.log(1 + (index.count - docs.size + 0.5) / (docs.size + 0.5))

        for (const [id, tf] of docs) {
          const norm = 1 - B + (B * lengths[id]) / average
          const value = weight * boost * match * idf * (DELTA + (tf * (K1 + 1)) / (tf + K1 * norm))
          scores.set(id, (scores.get(id) ?? 0) + value)
        }
      }
    }
  }

  return scores
}

/**
 * The terms the docs use for the words in this query, longest phrase first.
 * Mirrors Synonyms::expand().
 *
 * @param {string} query
 * @param {Array<Array<string>>} groups
 * @returns {Array<string>}
 */
export function expansions(query, groups) {
  const normalized = ` ${normalizePhrase(query)} `
  const added = new Set()

  for (const group of groups) {
    const phrases = group.map(normalizePhrase)

    if (!phrases.some((phrase) => phrase !== '' && normalized.includes(` ${phrase} `))) {
      continue
    }

    for (const phrase of phrases) {
      if (phrase !== '' && !normalized.includes(` ${phrase} `)) {
        added.add(phrase)
      }
    }
  }

  return [...added].sort((a, b) => b.length - a.length || a.localeCompare(b))
}

/**
 * Terms to search for: what the reader typed, plus what those words are also
 * known by, at half weight.
 *
 * @param {string} query
 * @param {Array<string>} widened
 */
export function expand(query, widened) {
  const weights = new Map()

  for (const term of terms(query)) {
    weights.set(term, 1)

    if (!weights.has(stem(term))) {
      weights.set(stem(term), STEM_WEIGHT)
    }
  }

  for (const phrase of widened) {
    for (const term of terms(phrase)) {
      if (!weights.has(term)) {
        weights.set(term, SYNONYM_WEIGHT)
      }
    }
  }

  return weights
}

/**
 * The best results, with at most PER_PAGE from any one page before any other
 * page's. What the cap holds back moves below the rest rather than being
 * dropped. Mirrors Ranker::spread().
 *
 * @param {Array<{record: Record<string, any>}>} scored
 * @param {number} limit
 */
export function spread(scored, limit) {
  const results = []
  const held = []
  const seen = new Map()

  for (const hit of scored) {
    const page = hit.record.page
    const count = (seen.get(page) ?? 0) + 1
    seen.set(page, count)
    ;(count > PER_PAGE ? held : results).push(hit)
  }

  return [...results, ...held].slice(0, limit)
}

/**
 * A ranker over one search index.
 *
 * @param {{sections: Array<Record<string, any>>, synonyms: Array<Array<string>>}} answers
 */
export function createRanker(answers) {
  const sections = answers.sections ?? []
  const index = createIndex(
    sections.map((section) => ({
      title: section.title,
      heading: section.heading,
      text: `${section.text} ${(section.questions ?? []).join(' ')}`,
    })),
  )
  const groups = answers.synonyms ?? []

  return {
    sections,

    /**
     * @param {string} query
     * @param {number} limit
     */
    search(query, limit = 10) {
      const trimmed = query.trim()

      if (trimmed === '') {
        return []
      }

      const lexical = score(index, expand(trimmed, expansions(trimmed, groups)))
      const best = lexical.size > 0 ? Math.max(...lexical.values()) : 0
      const normalized = ` ${normalizePhrase(trimmed)} `
      const scored = []

      sections.forEach((section, id) => {
        const term = best > 0 ? (lexical.get(id) ?? 0) / best : 0
        // What the section is called, not what it mentions.
        const exact = (section.names ?? []).some(
          (name) => name !== '' && normalized.includes(` ${name} `),
        )

        if (term <= 0 && !exact) {
          return
        }

        scored.push({
          record: section,
          score: term + (exact ? EXACT_BOOST : 0),
          lexical: term,
          exact,
        })
      })

      scored.sort((a, b) => b.score - a.score)

      return spread(scored, limit)
    },
  }
}

/**
 * Load the search index for this page's version.
 *
 * @param {{answers: string}} urls
 */
export async function load(urls) {
  if (loaded && loaded.key === urls.answers) {
    return loaded.ranker
  }

  const response = await fetch(urls.answers, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`Failed to load the search index (${response.status})`)
  }

  loaded = { key: urls.answers, ranker: createRanker(await response.json()) }

  return loaded.ranker
}

/**
 * Scout searches on the server and knows nothing of sections, so its hits are
 * shown as page results.
 *
 * @param {string} url
 * @param {string} query
 */
export async function searchScout(url, query) {
  const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}q=${encodeURIComponent(query)}`, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`Search failed (${response.status})`)
  }

  const payload = await response.json()
  const documents = Array.isArray(payload.documents) ? payload.documents : []

  return documents.slice(0, 8).map((document) => ({
    record: {
      id: document.id ?? document.url,
      url: document.url,
      title: document.title ?? '',
      heading: '',
      passage: document.description || (document.content ?? '').slice(0, 200),
    },
    score: 0,
    lexical: 0,
    exact: false,
  }))
}

/**
 * @param {string} value
 */
export function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/**
 * Highlight the reader's words inside a passage.
 *
 * @param {string} text
 * @param {string} query
 */
export function highlight(text, query) {
  const safe = escapeHtml(text)
  const tokens = terms(query).map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'))

  if (tokens.length === 0) {
    return safe
  }

  return safe.replace(new RegExp(`(${tokens.join('|')})`, 'gi'), '<mark class="vellum-search-mark">$1</mark>')
}

/**
 * The breadcrumb over a result: the page, then the heading inside it.
 *
 * @param {Record<string, any>} record
 */
export function breadcrumb(record) {
  return [record.title, record.heading].filter((part) => part !== '' && part !== undefined).join(' › ')
}
