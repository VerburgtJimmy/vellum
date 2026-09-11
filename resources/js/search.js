import MiniSearch from 'minisearch'

/**
 * Search chunk loaded on demand when the search dialog first opens.
 */
export { MiniSearch }

let cachedUrl = null
let cachedIndex = null
let cachedDocuments = null

/**
 * Build a MiniSearch index from compiled docs search documents.
 *
 * @param {Array<Record<string, unknown>>} documents
 * @param {import('minisearch').Options} [options]
 */
export function createSearchIndex(documents, options = {}) {
  const index = new MiniSearch({
    fields: ['title', 'content', 'headings'],
    storeFields: ['title', 'url', 'description', 'headings'],
    searchOptions: {
      boost: { title: 3, headings: 2 },
      prefix: true,
      fuzzy: 0.2,
    },
    ...options,
  })

  index.addAll(documents)

  return index
}

/**
 * Fetch the compiled search index JSON.
 *
 * @param {string} url
 * @returns {Promise<Array<Record<string, unknown>>>}
 */
export async function fetchSearchDocuments(url) {
  const response = await fetch(url, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    throw new Error(`Failed to load search index (${response.status})`)
  }

  const payload = await response.json()

  return Array.isArray(payload.documents) ? payload.documents : []
}

/**
 * Load (and memoize) a MiniSearch index for the given URL.
 *
 * @param {string} url
 */
export async function getOrCreateIndex(url) {
  if (cachedIndex && cachedUrl === url) {
    return { index: cachedIndex, documents: cachedDocuments ?? [] }
  }

  const documents = await fetchSearchDocuments(url)
  cachedDocuments = documents
  cachedIndex = createSearchIndex(documents)
  cachedUrl = url

  return { index: cachedIndex, documents }
}

/**
 * Escape HTML for safe highlight injection.
 *
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
 * Highlight query tokens inside text (case-insensitive).
 *
 * @param {string} text
 * @param {string} query
 */
export function highlightMatches(text, query) {
  const safe = escapeHtml(text)
  const trimmed = query.trim()

  if (!trimmed) {
    return safe
  }

  const tokens = trimmed.split(/\s+/).filter(Boolean).map((token) =>
    token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
  )

  if (tokens.length === 0) {
    return safe
  }

  const pattern = new RegExp(`(${tokens.join('|')})`, 'gi')

  return safe.replace(pattern, '<mark class="vellum-search-mark">$1</mark>')
}

/**
 * Group MiniSearch hits by page URL.
 *
 * @param {Array<Record<string, unknown>>} results
 * @param {string} query
 */
export function groupResultsByPage(results, query) {
  /** @type {Map<string, { title: string, url: string, items: Array<{ html: string }> }>} */
  const groups = new Map()

  for (const result of results) {
    const url = String(result.url ?? '')
    const title = String(result.title ?? 'Untitled')
    const description = String(result.description ?? '')
    const content = String(result.content ?? '')
    const snippetSource = description || content.slice(0, 160)
    const snippet = highlightMatches(snippetSource, query)
    const titleHtml = highlightMatches(title, query)

    if (!groups.has(url)) {
      groups.set(url, {
        title,
        titleHtml,
        url,
        items: [],
      })
    }

    groups.get(url).items.push({ html: snippet })
  }

  return [...groups.values()]
}
