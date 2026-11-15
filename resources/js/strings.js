/**
 * The page's words, in the app's language. The layout writes the lines the
 * scripts use into one JSON block, so a script holds keys, not English.
 */

let strings

function table() {
  if (strings === undefined) {
    try {
      strings = JSON.parse(document.getElementById('vellum-strings')?.textContent || '{}')
    } catch {
      strings = {}
    }
  }

  return strings
}

/**
 * Use these lines instead of the page's. For tests.
 *
 * @param {object} lines
 */
export function setStrings(lines) {
  strings = lines
}

/**
 * The line at a dotted key, with each :name filled in from replace. A key
 * with no line comes back as itself.
 *
 * @param {string} key
 * @param {Record<string, string|number>} [replace]
 * @returns {string}
 */
export function t(key, replace = {}) {
  const line = key.split('.').reduce((node, part) => node?.[part], table())

  return typeof line === 'string'
    ? line.replace(/:(\w+)/g, (match, name) => (name in replace ? String(replace[name]) : match))
    : key
}

/**
 * The form of a line for a count, from "one|many", with :count filled in.
 *
 * @param {string} key
 * @param {number} count
 * @param {Record<string, string|number>} [replace]
 * @returns {string}
 */
export function tc(key, count, replace = {}) {
  const forms = t(key, { ...replace, count }).split('|')

  return forms[count === 1 ? 0 : forms.length - 1]
}
