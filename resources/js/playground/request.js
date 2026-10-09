/**
 * Turns what a reader filled in for one operation into a request the browser
 * can send, and into the curl command that would send the same thing.
 *
 * Nothing here touches the page or the network, so it is tested on its own.
 *
 * operation: { method, path, parameters: [{ name, in }], body: { contentType } | null }
 * values:    { path: {}, query: {}, header: {}, body: any }
 * auth:      [{ type: 'bearer' | 'basic' | 'apiKey', in?, name?, value, username?, password? }]
 */

const unset = (value) => value === undefined || value === null || value === ''

/**
 * An object to keep values in by a name that comes from a spec. It has no
 * prototype, so a parameter called __proto__ is one more key and nothing else.
 */
export const bag = () => Object.create(null)

/** A header name is a token. Anything else is not sent, and not written into a command. */
const headerName = (name) => /^[A-Za-z0-9!#$%&'*+.^_`|~-]+$/.test(name)

/** Fills {name} in a path. A missing value stays as written, so it is visible. */
export function fillPath(path, values = {}) {
  return path.replace(/\{([^}]+)\}/g, (whole, name) =>
    unset(values[name]) ? whole : encodeURIComponent(String(values[name])),
  )
}

/** A query string. An array repeats its key, the OpenAPI default for query parameters. */
export function queryString(values = {}) {
  const pairs = []

  for (const [name, value] of Object.entries(values)) {
    for (const item of Array.isArray(value) ? value : [value]) {
      if (!unset(item)) {
        pairs.push(`${encodeURIComponent(name)}=${encodeURIComponent(String(item))}`)
      }
    }
  }

  return pairs.join('&')
}

function base64(text) {
  const bytes = new TextEncoder().encode(text)
  let binary = ''

  for (const byte of bytes) {
    binary += String.fromCharCode(byte)
  }

  return btoa(binary)
}

function applyAuth(auth, headers, query) {
  for (const entry of auth) {
    if (entry.type === 'bearer' && !unset(entry.value)) {
      headers.Authorization = `Bearer ${entry.value}`
    } else if (entry.type === 'basic' && !unset(entry.username)) {
      headers.Authorization = `Basic ${base64(`${entry.username}:${entry.password ?? ''}`)}`
    } else if (entry.type === 'apiKey' && !unset(entry.value) && entry.name) {
      if (entry.in === 'query') {
        query[entry.name] = entry.value
      } else if (entry.in !== 'cookie' && headerName(entry.name)) {
        // A page cannot set a cookie on a request. A cookie key is sent by
        // the browser itself when the reader is signed in on that origin.
        headers[entry.name] = entry.value
      }
    }
  }
}

function encodeBody(contentType, body) {
  if (unset(body)) {
    return null
  }

  if (typeof body === 'string') {
    return body
  }

  if (contentType === 'application/x-www-form-urlencoded') {
    return queryString(body)
  }

  // Set out over lines, as the command shows it: what is sent is what is shown.
  return JSON.stringify(body, null, 2)
}

/**
 * @returns {{ method: string, url: string, headers: Record<string, string>, body: string | null, files: Record<string, unknown> | null }}
 */
export function buildRequest(operation, server, values = {}, auth = []) {
  const headers = Object.assign(bag(), { Accept: 'application/json' })
  const query = Object.assign(bag(), values.query ?? {})

  for (const [name, value] of Object.entries(values.header ?? {})) {
    if (!unset(value) && headerName(name)) {
      headers[name] = String(value)
    }
  }

  applyAuth(auth, headers, query)

  const method = operation.method.toUpperCase()
  const contentType = operation.body?.contentType ?? null
  const carriesBody = contentType !== null && method !== 'GET' && method !== 'HEAD'
  // A multipart body is built by the browser, which also writes its
  // Content-Type, boundary included. Its fields travel as they are.
  const multipart = carriesBody && contentType === 'multipart/form-data'
  const body = carriesBody && !multipart ? encodeBody(contentType, values.body) : null

  if (body !== null) {
    headers['Content-Type'] = contentType
  }

  const search = queryString(query)
  const path = fillPath(operation.path, values.path)
  const url = String(server ?? '').replace(/\/+$/, '') + path + (search === '' ? '' : `?${search}`)

  return {
    method,
    url,
    headers,
    body,
    files: multipart && !unset(values.body) ? values.body : null,
  }
}

/** Inside single quotes a shell expands nothing, so only the quote itself needs care. */
const shellQuote = (value) => `'${String(value).replace(/'/g, `'\\''`)}'`

/**
 * The same request as a curl command. Matches the PHP generator's quoting.
 *
 * Quoting keeps the shell out of it. curl reads its arguments too: a leading
 * @ in a body or a form field means "the contents of this file", and an
 * address that starts with a dash is an option. So the body goes in with
 * --data-raw, a field with --form-string, and such an address after --url.
 * Only a file the reader chose is written with @.
 */
export function toCurl(request) {
  const url = request.url.startsWith('-') ? `--url ${shellQuote(request.url)}` : shellQuote(request.url)
  const lines = [`curl -X ${request.method.replace(/[^A-Z]/g, '')} ${url}`]

  for (const [name, value] of Object.entries(request.headers)) {
    lines.push(`  -H ${shellQuote(`${name}: ${value}`)}`)
  }

  if (request.body !== null) {
    lines.push(`  --data-raw ${shellQuote(request.body)}`)
  }

  for (const [name, value] of Object.entries(request.files ?? {})) {
    const isFile = typeof value === 'object' && value !== null && 'name' in value
    const field = String(name).replace(/[=;]/g, '_')

    lines.push(
      isFile
        ? // In double quotes, so a ; or , in the file's name is part of the name.
          `  -F ${shellQuote(`${field}=@"${String(value.name).replace(/[\\"]/g, '\\$&')}"`)}`
        : `  --form-string ${shellQuote(`${field}=${typeof value === 'object' ? JSON.stringify(value) : value}`)}`,
    )
  }

  return lines.join(' \\\n')
}

/**
 * Where a request can go from this page.
 *
 * 'direct' when the API is on the page's own origin, which is the usual case
 * for docs served by the app they describe. Another origin is 'cross': the
 * browser sends it only if that API allows this origin, and otherwise the
 * request needs the app's forwarding route.
 */
export function reach(url, pageOrigin) {
  try {
    return new URL(url, pageOrigin).origin === pageOrigin ? 'direct' : 'cross'
  } catch {
    return 'invalid'
  }
}

/** Laravel's XSRF-TOKEN cookie, for requests a signed-in reader sends to the same origin. */
export function xsrfToken(cookie) {
  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(cookie ?? '')

  return match ? decodeURIComponent(match[1]) : null
}
