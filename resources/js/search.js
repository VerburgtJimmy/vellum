/**
 * Search in the browser: the same ranking Vellum\Answers\Ranker runs in PHP,
 * over the files vellum:build wrote. Loaded on demand when the dialog opens.
 *
 * Keep this in step with Bm25.php, Synonyms.php and Ranker.php. The numbers
 * below are theirs.
 */

const K1 = 1.2
const B = 0.7
const DELTA = 0.5
const PREFIX_WEIGHT = 0.375
const FIELD_BOOST = { title: 3, heading: 2, text: 1 }

const TERM_BOOST = 0.1
const EXACT_BOOST = 0.1
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

      for (const term of list) {
        let docs = postings.get(term)

        if (docs === undefined) {
          docs = new Map()
          postings.set(term, docs)
        }

        docs.set(id, (docs.get(id) ?? 0) + 1)
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
  const weights = new Map(terms(query).map((term) => [term, 1]))

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
 * A ranker over one answer index. `cosine` is the semantic scorer when the
 * vectors loaded, and null when they did not: search then runs on words alone.
 *
 * @param {{sections: Array<Record<string, any>>, synonyms: Array<Array<string>>, threshold?: number}} answers
 * @param {((query: string) => Map<string, number>)|null} cosine
 */
export function createRanker(answers, cosine = null) {
  const sections = answers.sections ?? []
  const index = createIndex(
    sections.map((section) => ({
      title: section.title,
      heading: section.heading,
      text: `${section.text} ${(section.questions ?? []).join(' ')}`,
    })),
  )
  const groups = answers.synonyms ?? []
  const threshold = answers.threshold ?? 0.75

  return {
    sections,

    /**
     * @param {string} query
     * @param {number} limit
     */
    search(query, limit = 10) {
      const trimmed = query.trim()

      if (trimmed === '') {
        return { results: [], card: null, confidence: 0 }
      }

      const widened = expansions(trimmed, groups)
      const lexical = score(index, expand(trimmed, widened))
      const best = lexical.size > 0 ? Math.max(...lexical.values()) : 0
      // Embed the reader's words together with the docs' words for them.
      const cosines = cosine ? cosine(`${trimmed} ${widened.join(' ')}`.trim()) : new Map()
      const normalized = ` ${normalizePhrase(trimmed)} `
      const scored = []

      sections.forEach((section, id) => {
        const similarity = cosines.get(section.id) ?? 0
        const term = best > 0 ? (lexical.get(id) ?? 0) / best : 0
        // What the section is called, not what it mentions.
        const exact = (section.names ?? []).some(
          (name) => name !== '' && normalized.includes(` ${name} `),
        )

        if (similarity <= 0 && term <= 0 && !exact) {
          return
        }

        scored.push({
          record: section,
          score: similarity + TERM_BOOST * term + (exact ? EXACT_BOOST : 0),
          cosine: similarity,
          lexical: term,
          exact,
        })
      })

      scored.sort((a, b) => b.score - a.score)

      const results = spread(scored, limit)
      const sureness = confidence(scored)

      return {
        results,
        confidence: sureness,
        card: sureness >= threshold ? (results[0] ?? null) : null,
      }
    },
  }
}

/**
 * How sure the top result is, from 0 to 1. Mirrors Ranker::confidence().
 *
 * @param {Array<{score: number, cosine: number, lexical: number, exact: boolean}>} scored
 */
export function confidence(scored) {
  if (scored.length === 0) {
    return 0
  }

  const top = scored[0]
  const second = scored[1]?.score ?? 0
  const lead = top.score > 0 ? (top.score - second) / top.score : 0

  return Math.min(
    1,
    Math.max(
      0,
      0.6 * Math.max(0, top.cosine) +
        0.25 * Math.min(1, lead / 0.2) +
        0.15 * (top.exact || top.lexical >= 1 ? 1 : 0),
    ),
  )
}

/**
 * Load the answer index, and the vectors when the site has them. The vectors
 * are optional: without them search still runs, on words alone.
 *
 * @param {{answers: string, semantic: string|null, semanticModule: string|null}} urls
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
    throw new Error(`Failed to load the answer index (${response.status})`)
  }

  const answers = await response.json()
  let cosine = null

  if (urls.semantic && urls.semanticModule) {
    try {
      const semantic = await import(/* @vite-ignore */ urls.semanticModule)
      const vectors = await semantic.loadSemanticFile(urls.semantic)
      cosine = (query) => semantic.cosines(query, vectors)
    } catch {
      cosine = null
    }
  }

  loaded = { key: urls.answers, ranker: createRanker(answers, cosine) }

  return loaded.ranker
}

/**
 * Scout answers server-side and knows nothing of sections, so its hits are
 * shown as page results with no card.
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

  return {
    results: documents.slice(0, 8).map((document) => ({
      record: {
        id: document.id ?? document.url,
        url: document.url,
        title: document.title ?? '',
        heading: '',
        passage: document.description || (document.content ?? '').slice(0, 200),
      },
      score: 0,
      cosine: 0,
      lexical: 0,
      exact: false,
    })),
    card: null,
    confidence: 0,
  }
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

/**
 * The rows of a config card: the ones the reader named, or the first few when
 * they named none.
 *
 * @param {Array<Record<string, string>>} rows
 * @param {string} query
 */
export function configRows(rows, query) {
  const asked = ` ${normalizePhrase(query)} `
  const named = rows.filter((row) => row.key && asked.includes(` ${normalizePhrase(row.key)} `))

  return named.length > 0 ? named : rows
}

/**
 * The passage to show under a card's answer: what the section says beyond the
 * answer itself, so a sentence answer is not printed twice.
 *
 * @param {string} passage
 * @param {string} answered
 */
export function remainder(passage, answered) {
  const rest = (passage ?? '').trim()

  if (answered && rest.startsWith(answered.trim())) {
    return rest.slice(answered.trim().length).trim()
  }

  return rest
}

/**
 * What a card shows: the answer in the shape its type calls for, with the
 * passage under it. Everything is the docs' own words.
 *
 * @param {Record<string, any>} record
 * @param {string} query  so a config card shows the key that was asked about
 */
export function cardContent(record, query = '') {
  const answer = record.answer ?? null

  if (answer === null) {
    return { kind: 'text', text: record.passage ?? '' }
  }

  switch (answer.type) {
    case 'command':
      return { kind: 'code', code: answer.command, language: 'bash' }
    case 'code':
      return { kind: 'code', code: answer.code, language: answer.language ?? '' }
    case 'config':
      return { kind: 'config', rows: configRows(answer.rows ?? [], query) }
    case 'row':
      return { kind: 'row', row: answer.row ?? {} }
    default:
      return {
        kind: answer.code ? 'sentence-code' : 'text',
        text: answer.sentence ?? record.passage ?? '',
        code: answer.code ?? '',
        language: answer.language ?? '',
      }
  }
}
