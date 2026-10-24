import { createHash } from 'node:crypto'
import { describe, expect, it } from 'vitest'
import { authorizeUrl, fromDiscovery, pkce, random, readToken, tokenRequest } from '../../resources/js/playground/oauth.js'

const flow = { authorizationUrl: 'https://auth.test/authorize?audience=api', tokenUrl: 'https://auth.test/token', scopes: { read: '' } }

describe('getting an OAuth token', () => {
  it('makes a challenge only the verifier answers', async () => {
    const { verifier, challenge } = await pkce()
    const expected = createHash('sha256').update(verifier).digest('base64url')

    expect(verifier).toMatch(/^[A-Za-z0-9_-]{43,128}$/)
    expect(challenge).toBe(expected)
    expect(random()).not.toBe(random())
  })

  it('sends the reader to sign in with the challenge, the state and the scopes', () => {
    const url = new URL(authorizeUrl(flow, { clientId: 'docs', redirectUri: 'https://docs.test/docs/_vellum/oauth', scopes: ['read', 'write'], state: 's1', challenge: 'c1' }))

    expect(Object.fromEntries(url.searchParams)).toEqual({
      audience: 'api',
      response_type: 'code',
      client_id: 'docs',
      redirect_uri: 'https://docs.test/docs/_vellum/oauth',
      state: 's1',
      code_challenge: 'c1',
      code_challenge_method: 'S256',
      scope: 'read write',
    })
  })

  it('never opens a sign-in address that is not a web address', () => {
    for (const address of ['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'vbscript:x']) {
      expect(() => authorizeUrl({ ...flow, authorizationUrl: address }, { clientId: 'a', redirectUri: 'https://d.test/cb', scopes: [], state: 's', challenge: 'c' })).toThrow('not a web address')
    }
  })

  it('trades the code for a token with the verifier, naming a public client in the body', () => {
    const request = tokenRequest(flow.tokenUrl, 'authorization_code', { clientId: 'docs', code: 'abc', verifier: 'v', redirectUri: 'https://docs.test/cb' })

    expect(request.method).toBe('POST')
    expect(request.headers.Authorization).toBeUndefined()
    expect(Object.fromEntries(new URLSearchParams(request.body))).toEqual({
      grant_type: 'authorization_code',
      code: 'abc',
      redirect_uri: 'https://docs.test/cb',
      code_verifier: 'v',
      client_id: 'docs',
    })
  })

  it('names a client with a secret in the Authorization header, each part encoded', () => {
    const request = tokenRequest(flow.tokenUrl, 'client_credentials', { clientId: 'a b', clientSecret: 'p:w&d', scopes: ['read'] })

    expect(atob(request.headers.Authorization.slice(6))).toBe('a+b:p%3Aw%26d')
    expect(Object.fromEntries(new URLSearchParams(request.body))).toEqual({ grant_type: 'client_credentials', scope: 'read' })
  })

  it('reads the token, or what the provider said instead', () => {
    expect(readToken(200, '{"access_token":"t","expires_in":3600}')).toEqual({ token: 't', expiresIn: 3600 })
    expect(readToken(200, 'access_token=t&token_type=bearer')).toEqual({ token: 't', expiresIn: null })
    expect(readToken(400, '{"error":"invalid_grant","error_description":"Expired."}')).toEqual({ error: 'invalid_grant: Expired.' })
    expect(readToken(502, '<html>')).toEqual({ error: 'HTTP 502' })
  })

  it('finds an OpenID Connect provider\'s sign-in from its discovery document', () => {
    expect(fromDiscovery({ authorization_endpoint: 'https://id.test/a', token_endpoint: 'https://id.test/t', scopes_supported: ['openid', 'email'] })).toEqual({
      authorizationCode: { authorizationUrl: 'https://id.test/a', tokenUrl: 'https://id.test/t', scopes: { openid: '', email: '' } },
    })
    expect(fromDiscovery({ issuer: 'x' })).toBeNull()
  })
})
