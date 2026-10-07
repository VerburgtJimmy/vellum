import { describe, expect, it } from 'vitest'
import { buildRequest, fillPath, queryString, reach, toCurl, xsrfToken } from '../../resources/js/playground/request.js'

const createPet = {
  method: 'post',
  path: '/pets/{petId}/notes',
  body: { contentType: 'application/json' },
}

describe('fillPath', () => {
  it('encodes a value so it cannot add a path segment or a query', () => {
    expect(fillPath('/pets/{petId}', { petId: 'a/b?c=d' })).toBe('/pets/a%2Fb%3Fc%3Dd')
  })

  it('leaves a missing parameter visible', () => {
    expect(fillPath('/pets/{petId}', {})).toBe('/pets/{petId}')
  })
})

describe('queryString', () => {
  it('repeats the key for an array and skips what is not set', () => {
    expect(queryString({ tag: ['a b', 'c'], limit: 10, cursor: '', after: null })).toBe('tag=a%20b&tag=c&limit=10')
  })

  it('keeps zero and false, which are values', () => {
    expect(queryString({ offset: 0, archived: false })).toBe('offset=0&archived=false')
  })
})

describe('buildRequest', () => {
  it('puts each value in the part of the request it belongs to', () => {
    const request = buildRequest(
      createPet,
      'https://api.test/v1/',
      { path: { petId: 7 }, query: { draft: true }, header: { 'X-Trace': 'abc' }, body: { text: 'Hi' } },
      [{ type: 'bearer', value: 'secret' }],
    )

    expect(request).toEqual({
      method: 'POST',
      url: 'https://api.test/v1/pets/7/notes?draft=true',
      headers: {
        Accept: 'application/json',
        'X-Trace': 'abc',
        Authorization: 'Bearer secret',
        'Content-Type': 'application/json',
      },
      body: '{"text":"Hi"}',
      files: null,
    })
  })

  it('sends no body and no content type on a GET', () => {
    const request = buildRequest({ method: 'get', path: '/pets', body: { contentType: 'application/json' } }, '', { body: { a: 1 } })

    expect(request.body).toBeNull()
    expect(request.headers['Content-Type']).toBeUndefined()
    expect(request.url).toBe('/pets')
  })

  it('encodes a form body as a form', () => {
    const request = buildRequest(
      { method: 'post', path: '/login', body: { contentType: 'application/x-www-form-urlencoded' } },
      '',
      { body: { email: 'a@b.test', remember: true } },
    )

    expect(request.body).toBe('email=a%40b.test&remember=true')
  })

  it('leaves a multipart body and its content type to the browser', () => {
    const fields = { title: 'Mars', image: { name: 'mars.png' } }
    const request = buildRequest({ method: 'post', path: '/upload', body: { contentType: 'multipart/form-data' } }, '', { body: fields })

    expect(request.body).toBeNull()
    expect(request.files).toBe(fields)
    expect(request.headers['Content-Type']).toBeUndefined()
  })

  it('writes basic auth for names outside ASCII', () => {
    const request = buildRequest({ method: 'get', path: '/' }, '', {}, [{ type: 'basic', username: 'zoë', password: 'pässword' }])

    expect(request.headers.Authorization).toBe(`Basic ${Buffer.from('zoë:pässword', 'utf8').toString('base64')}`)
  })

  it('sends an API key where its scheme says, and never invents a cookie', () => {
    const request = buildRequest({ method: 'get', path: '/' }, '', {}, [
      { type: 'apiKey', in: 'header', name: 'X-Api-Key', value: 'h' },
      { type: 'apiKey', in: 'query', name: 'api_key', value: 'q' },
      { type: 'apiKey', in: 'cookie', name: 'session', value: 'c' },
    ])

    expect(request.headers['X-Api-Key']).toBe('h')
    expect(request.url).toBe('/?api_key=q')
    expect(JSON.stringify(request)).not.toContain('session')
  })

  it('leaves out auth that was not filled in', () => {
    const request = buildRequest({ method: 'get', path: '/' }, '', {}, [{ type: 'bearer', value: '' }])

    expect(request.headers.Authorization).toBeUndefined()
  })
})

describe('toCurl', () => {
  it('quotes every part so a shell reads it back unchanged', () => {
    const curl = toCurl({
      method: 'POST',
      url: 'https://api.test/$(id)',
      headers: { 'X-Note': `it's "fine"` },
      body: `{"name":"O'Brien"}`,
      files: null,
    })

    expect(curl).toBe(
      [
        `curl -X POST 'https://api.test/$(id)' \\`,
        `  -H 'X-Note: it'\\''s "fine"' \\`,
        `  -d '{"name":"O'\\''Brien"}'`,
      ].join('\n'),
    )
  })

  it('writes a file field by its name', () => {
    const curl = toCurl({ method: 'POST', url: '/upload', headers: {}, body: null, files: { title: 'Mars', image: { name: 'mars.png' } } })

    expect(curl).toContain(`-F 'title=Mars'`)
    expect(curl).toContain(`-F 'image=@mars.png'`)
  })
})

describe('reach', () => {
  it('tells the page origin from any other', () => {
    expect(reach('/api/pets', 'https://app.test')).toBe('direct')
    expect(reach('https://app.test/api/pets', 'https://app.test')).toBe('direct')
    expect(reach('https://api.app.test/pets', 'https://app.test')).toBe('cross')
    expect(reach('http://app.test/pets', 'https://app.test')).toBe('cross')
  })
})

describe('xsrfToken', () => {
  it('reads and decodes the token Laravel sets', () => {
    expect(xsrfToken('theme=dark; XSRF-TOKEN=abc%3D%3D; other=1')).toBe('abc==')
    expect(xsrfToken('NOT-XSRF-TOKEN-X=1')).toBeNull()
    expect(xsrfToken('')).toBeNull()
  })
})
