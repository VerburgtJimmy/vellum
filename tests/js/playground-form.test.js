import { describe, expect, it } from 'vitest'
import { bodyToForm, coerce, control, display, failure, fields, formToBody, hasForm, initial, pretty, size } from '../../resources/js/playground/form.js'

describe('control', () => {
  it('picks a control by what the schema allows', () => {
    expect(control({ type: 'string', enum: ['a', 'b'] })).toBe('select')
    expect(control({ type: 'boolean' })).toBe('boolean')
    expect(control({ type: 'integer' })).toBe('number')
    expect(control({ type: 'string' })).toBe('text')
    expect(control({ type: 'string', format: 'binary' })).toBe('file')
    expect(control({ type: 'array', items: { type: 'string' } })).toBe('list')
    expect(control({ type: 'array', items: { type: 'object' } })).toBe('json')
    expect(control({ type: 'object' })).toBe('json')
    expect(control({ type: 'json' })).toBe('json')
    expect(control()).toBe('json')
  })
})

describe('coerce', () => {
  it('reads a typed string as the schema type', () => {
    expect(coerce({ type: 'integer' }, ' 42 ')).toBe(42)
    expect(coerce({ type: 'number' }, '0.5')).toBe(0.5)
    expect(coerce({ type: 'boolean' }, 'false')).toBe(false)
    expect(coerce({ type: 'string' }, '42')).toBe('42')
    expect(coerce({ type: 'integer', enum: [1, 2] }, '2')).toBe(2)
    expect(coerce({ type: 'array', items: { type: 'integer' } }, '1, 2,,3')).toEqual([1, 2, 3])
    expect(coerce({ type: 'object' }, '{"a":1}')).toEqual({ a: 1 })
  })

  it('treats nothing typed as no value, and keeps zero', () => {
    expect(coerce({ type: 'string' }, '   ')).toBeUndefined()
    expect(coerce({ type: 'integer' }, '')).toBeUndefined()
    expect(coerce({ type: 'integer' }, '0')).toBe(0)
  })

  it('sends what was typed when it cannot be the type, so the API can answer', () => {
    expect(coerce({ type: 'integer' }, 'abc')).toBe('abc')
    expect(coerce({ type: 'object' }, '{oops')).toBe('{oops')
    expect(coerce({ type: 'boolean' }, 'maybe')).toBe('maybe')
  })
})

describe('display', () => {
  it('shows a value as the text its control holds', () => {
    expect(display({ type: 'integer' }, 0)).toBe('0')
    expect(display({ type: 'boolean' }, false)).toBe('false')
    expect(display({ type: 'array', items: { type: 'string' } }, ['a', 'b'])).toBe('a, b')
    expect(display({ type: 'object' }, { a: 1 })).toBe('{\n  "a": 1\n}')
    expect(display({ type: 'string' }, null)).toBe('')
  })
})

const ship = {
  type: 'object',
  required: ['name'],
  properties: {
    tonnage: { type: 'number' },
    name: { type: 'string' },
    captain: { type: 'object', properties: { name: { type: 'string' } } },
    tags: { type: 'array', items: { type: 'string' } },
  },
}

describe('a body as a form', () => {
  it('lists required fields first', () => {
    expect(fields(ship).map((field) => field.name)).toEqual(['name', 'tonnage', 'captain', 'tags'])
  })

  it('goes to texts and back without changing', () => {
    const body = { name: 'Aurora', tonnage: 0, captain: { name: 'Ada' }, tags: ['a', 'b'] }

    expect(formToBody(ship, bodyToForm(ship, body))).toEqual(body)
  })

  it('leaves out what was not filled in', () => {
    expect(formToBody(ship, { name: 'Aurora', tonnage: '', captain: ' ' })).toEqual({ name: 'Aurora' })
  })

  it('is offered only for an object with named fields', () => {
    expect(hasForm({ schema: ship })).toBe(true)
    expect(hasForm({ schema: { type: 'array', items: ship } })).toBe(false)
    expect(hasForm({ schema: { type: 'object' } })).toBe(false)
    expect(hasForm(null)).toBe(false)
  })
})

describe('initial', () => {
  it('fills in the example of a required parameter and leaves an optional one empty', () => {
    const values = initial({
      parameters: [
        { in: 'path', name: 'shipId', required: true, schema: { type: 'integer' }, example: 42 },
        { in: 'query', name: 'limit', required: false, schema: { type: 'integer' }, example: 25 },
        { in: 'header', name: 'X-Trace', required: true, schema: { type: 'string' }, example: null },
      ],
    })

    expect(values).toEqual({ path: { shipId: '42' }, query: { limit: '' }, header: { 'X-Trace': '' }, cookie: {} })
  })
})

describe('what a response shows', () => {
  it('sets out JSON and leaves the rest as it came', () => {
    expect(pretty('{"a":[1,2]}', 'application/json')).toBe('{\n  "a": [\n    1,\n    2\n  ]\n}')
    expect(pretty('{"a":1}', 'text/plain')).toBe('{\n  "a": 1\n}')
    expect(pretty('{not json', 'application/problem+json')).toBe('{not json')
    expect(pretty('<h1>Hi</h1>', 'text/html')).toBe('<h1>Hi</h1>')
  })

  it('says a size the way a reader would', () => {
    expect(size(512)).toBe('512 B')
    expect(size(2048)).toBe('2.0 KB')
    expect(size(3 * 1024 * 1024)).toBe('3.0 MB')
  })

  it('explains a request that got no response by where it went', () => {
    expect(failure(new TypeError('Failed to fetch'), 'cross')).toContain('CORS')
    expect(failure(new TypeError('Failed to fetch'), 'direct')).not.toContain('CORS')
    expect(failure({ name: 'AbortError' }, 'direct')).toContain('too long')
  })
})
