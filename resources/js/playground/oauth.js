/**
 * What the playground needs to get an OAuth 2 token for a reader: the
 * sign-in address for the authorization code flow with PKCE, and the token
 * request for that flow and for client credentials.
 *
 * Like request.js and form.js, nothing here touches the page. Sending the
 * token request is the playground's: it goes the way any request goes.
 */

const base64url = (bytes) =>
  btoa(String.fromCharCode(...bytes))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '')

/** A random string, URL-safe, for a state or a code verifier. */
export function random(bytes = 32) {
  const values = new Uint8Array(bytes)
  crypto.getRandomValues(values)

  return base64url(values)
}

/**
 * A PKCE pair: the verifier the playground keeps, and the challenge made
 * from it that goes with the reader to the provider. Only whoever holds the
 * verifier can turn the code that comes back into a token.
 */
export async function pkce() {
  const verifier = random(48)
  const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier)))

  return { verifier, challenge: base64url(digest) }
}

/** Where to send the reader to sign in. */
export function authorizeUrl(flow, { clientId, redirectUri, scopes, state, challenge }) {
  const url = new URL(flow.authorizationUrl)

  // The address is the spec's, often someone else's file. A window opened
  // on javascript: would run it as this page.
  if (url.protocol !== 'https:' && url.protocol !== 'http:') {
    throw new Error('The sign-in address in the spec is not a web address.')
  }

  url.searchParams.set('response_type', 'code')
  url.searchParams.set('client_id', clientId)
  url.searchParams.set('redirect_uri', redirectUri)
  url.searchParams.set('state', state)
  url.searchParams.set('code_challenge', challenge)
  url.searchParams.set('code_challenge_method', 'S256')

  if (scopes.length > 0) {
    url.searchParams.set('scope', scopes.join(' '))
  }

  return url.toString()
}

/**
 * The request that gets the token, as a request the playground can send:
 * a form-encoded POST. With a client secret, the client is named in an
 * Authorization header, which every provider takes. Without one it is
 * named in the body, as a public client is.
 */
export function tokenRequest(tokenUrl, grant, { clientId, clientSecret = '', code, verifier, redirectUri, scopes = [] }) {
  const body = new URLSearchParams({ grant_type: grant })

  if (grant === 'authorization_code') {
    body.set('code', code)
    body.set('redirect_uri', redirectUri)
    body.set('code_verifier', verifier)
  } else if (scopes.length > 0) {
    body.set('scope', scopes.join(' '))
  }

  const headers = { Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }

  if (clientSecret !== '') {
    // RFC 6749 2.3.1: each part form-encoded before the two are joined.
    const encode = (value) => encodeURIComponent(value).replace(/%20/g, '+')
    headers.Authorization = `Basic ${btoa(`${encode(clientId)}:${encode(clientSecret)}`)}`
  } else {
    body.set('client_id', clientId)
  }

  return { method: 'POST', url: tokenUrl, headers, body: body.toString() }
}

/** The token in a token response, or what the provider said instead. */
export function readToken(status, text) {
  let data = null

  try {
    data = JSON.parse(text ?? '')
  } catch {
    // Some providers answer a form-encoded body.
    data = Object.fromEntries(new URLSearchParams(text ?? ''))
  }

  if (status >= 200 && status < 300 && typeof data?.access_token === 'string' && data.access_token !== '') {
    return { token: data.access_token, expiresIn: Number(data.expires_in) || null }
  }

  const error = typeof data?.error === 'string' ? data.error : `HTTP ${status}`
  const description = typeof data?.error_description === 'string' ? `: ${data.error_description}` : ''

  return { error: `${error}${description}` }
}

/**
 * The authorization code flow of an OpenID Connect provider, from its
 * discovery document.
 */
export function fromDiscovery(document) {
  if (typeof document?.authorization_endpoint !== 'string' || typeof document?.token_endpoint !== 'string') {
    return null
  }

  const scopes = Object.fromEntries((Array.isArray(document.scopes_supported) ? document.scopes_supported : ['openid']).filter((scope) => typeof scope === 'string').map((scope) => [scope, '']))

  return { authorizationCode: { authorizationUrl: document.authorization_endpoint, tokenUrl: document.token_endpoint, scopes } }
}
