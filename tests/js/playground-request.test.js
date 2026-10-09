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
      body: '{\n  "text": "Hi"\n}',
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
    const fields = { title: 'Mars', image: new File(['x'], 'mars.png') }
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
        `  --data-raw '{"name":"O'\\''Brien"}'`,
      ].join('\n'),
    )
  })

  it('writes a file field by its name', () => {
    const curl = toCurl({ method: 'POST', url: '/upload', headers: {}, body: null, files: { title: 'Mars', image: new File(['x'], 'mars.png') } })

    expect(curl).toContain(`--form-string 'title=Mars'`)
    expect(curl).toContain(`-F 'image=@"mars.png"'`)
  })
})

describe('what curl itself would read into an argument', () => {
  it('never lets a value name a file for curl to send', () => {
    const curl = toCurl({
      method: 'POST',
      url: 'https://api.test/upload',
      headers: {},
      body: '@/etc/passwd',
      files: {
        note: '@/etc/passwd',
        'a=b;type': '<~/.ssh/id_rsa',
        image: new File(['x'], 'x";type=text/html'),
        // A value that has a name in it is still a value, not a file to send.
        owner: { name: '/etc/passwd' },
      },
    })

    // --data-raw and --form-string take what follows as it is written.
    expect(curl).toContain(`--data-raw '@/etc/passwd'`)
    expect(curl).toContain(`--form-string 'note=@/etc/passwd'`)
    expect(curl).toContain(`--form-string 'a_b_type=<~/.ssh/id_rsa'`)
    expect(curl).toContain(`-F 'image=@"x\\";type=text/html"'`)
    expect(curl).toContain(`--form-string 'owner={"name":"/etc/passwd"}'`)
    expect(curl.match(/@"/g)).toHaveLength(1)
    expect(curl).not.toMatch(/ -d /)
  })

  it('does not let an address be read as an option', () => {
    expect(toCurl({ method: 'GET', url: '-o /tmp/x', headers: {}, body: null, files: null })).toBe(`curl -X GET --url '-o /tmp/x'`)
  })

  it('sends no header whose name is not a header name', () => {
    const request = buildRequest({ method: 'get', path: '/' }, '', { header: { '@/etc/passwd': 'x', 'X Bad': 'y', 'X-Good': 'z' } }, [
      { type: 'apiKey', in: 'header', name: '@secrets', value: 'k' },
    ])

    expect(Object.keys(request.headers)).toEqual(['Accept', 'X-Good'])
  })
})

describe('a header value', () => {
  it('stays on one line, so it cannot begin a header of its own', () => {
    const request = buildRequest({ method: 'get', path: '/' }, '', { header: { 'X-Note': 'a\r\nX-Admin: 1' } }, [
      { type: 'bearer', value: 't\nX-Admin: 1' },
      { type: 'apiKey', in: 'header', name: 'X-Key', value: 'k\r\nX-Admin: 1' },
    ])

    expect(request.headers['X-Note']).toBe('a X-Admin: 1')
    expect(request.headers.Authorization).toBe('Bearer t X-Admin: 1')
    expect(request.headers['X-Key']).toBe('k X-Admin: 1')
    expect(toCurl(request)).not.toMatch(/\n(?!  -)/)
  })
})

describe('names from a spec as keys', () => {
  it('treats __proto__ as a name like any other', () => {
    const values = { query: JSON.parse('{"__proto__": "q", "constructor": "c"}'), header: JSON.parse('{"__proto__": "h"}') }
    const request = buildRequest({ method: 'get', path: '/' }, '', values)

    expect(request.url).toBe('/?__proto__=q&constructor=c')
    expect({}.q).toBeUndefined()
    expect(Object.getPrototypeOf(request.headers)).toBeNull()
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
