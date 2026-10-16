import { describe, expect, it } from 'vitest'
import { bodyToForm, coerce, control, display, failure, fields, formToBody, hasForm, initial, leaves, pretty, size, rows, addItem, removeItem } from '../../resources/js/playground/form.js'

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

describe('names from a spec as keys', () => {
  it('keeps a field called __proto__ as a field', () => {
    const schema = { type: 'object', properties: JSON.parse('{"__proto__": {"type": "object"}, "name": {"type": "string"}}') }
    const body = formToBody(schema, JSON.parse('{"__proto__": "{\\"polluted\\": true}", "name": "x"}'))

    expect(Object.keys(body)).toEqual(['__proto__', 'name'])
    expect(JSON.stringify(body)).toBe('{"__proto__":{"polluted":true},"name":"x"}')
    expect({}.polluted).toBeUndefined()
    expect(Object.getPrototypeOf(bodyToForm(schema, {}))).toBeNull()
  })

  it('opens with nothing inherited for a parameter of that name', () => {
    const values = initial({ parameters: [{ in: 'query', name: '__proto__', required: false, schema: { type: 'string' } }] })

    expect(Object.keys(values.query)).toEqual(['__proto__'])
    expect(Object.getPrototypeOf(values.query)).toBeNull()
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

    expect(JSON.parse(JSON.stringify(values))).toEqual({ path: { shipId: '42' }, query: { limit: '' }, header: { 'X-Trace': '' }, cookie: {} })
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

describe('an object inside the body', () => {
  const note = {
    type: 'object',
    required: ['title', 'author'],
    properties: {
      title: { type: 'string' },
      author: {
        type: 'object',
        required: ['name'],
        properties: { name: { type: 'string' }, age: { type: 'integer' } },
      },
      editor: {
        type: 'object',
        required: ['name'],
        properties: { name: { type: 'string' } },
      },
      meta: { type: 'object' },
    },
  }

  it('gives its fields in its place, under the path that leads to them', () => {
    expect(leaves(note).map((leaf) => [leaf.key, leaf.required])).toEqual([
      ['title', true],
      ['author.name', true],
      ['author.age', false],
      // Required inside an object that can itself be left out.
      ['editor.name', false],
      ['meta', false],
    ])
  })

  it('is built from what was typed into them, and left out when nothing was', () => {
    expect(formToBody(note, { title: 'A', 'author.name': 'Ada', 'author.age': '36', 'editor.name': '' })).toEqual({
      title: 'A',
      author: { name: 'Ada', age: 36 },
    })
  })

  it('comes back as the same texts', () => {
    const body = { title: 'A', author: { name: 'Ada', age: 36 }, meta: { a: 1 } }

    expect(formToBody(note, bodyToForm(note, body))).toEqual(body)
    expect(bodyToForm(note, { author: 'not an object' })['author.name']).toBe('')
  })

  it('is written as JSON once it is nested too deep to lay out', () => {
    const wrap = (inner) => ({ type: 'object', properties: { in: inner } })
    const deep = wrap(wrap(wrap(wrap(wrap({ type: 'string' })))))

    expect(leaves(deep).map((leaf) => [leaf.key, control(leaf.schema)])).toEqual([['in.in.in.in', 'json']])
  })

  it('keeps a field named with a dot apart from the one it would be mistaken for', () => {
    const schema = {
      type: 'object',
      properties: { a: { type: 'object', properties: { b: { type: 'string' } } }, 'a.b': { type: 'string' } },
    }
    const keys = leaves(schema).map((leaf) => leaf.key)

    expect(new Set(keys).size).toBe(2)
    expect(formToBody(schema, { [keys[0]]: 'in', [keys[1]]: 'out' })).toEqual({ a: { b: 'in' }, 'a.b': 'out' })
  })

  it('puts a picked file where its field is', () => {
    const schema = { type: 'object', properties: { doc: { type: 'object', properties: { scan: { type: 'string', format: 'binary' } } } } }
    const file = { name: 'scan.png' }

    expect(formToBody(schema, {}, { 'doc.scan': file })).toEqual({ doc: { scan: file } })
  })
})

describe('a list of objects in the body', () => {
  const ship = {
    type: 'object',
    properties: {
      name: { type: 'string' },
      crew: { type: 'array', items: { type: 'object', required: ['name'], properties: { name: { type: 'string' }, age: { type: 'integer' } } } },
      tags: { type: 'array', items: { type: 'string' } },
    },
  }

  it('is laid out an item at a time, each with its own fields', () => {
    const texts = bodyToForm(ship, { crew: [{ name: 'Ada', age: 36 }, { name: 'Tom' }] })

    expect(rows(ship, texts).map((row) => row.kind ?? row.key)).toEqual(['name', 'list', 'item', 'crew.0.name', 'crew.0.age', 'item', 'crew.1.name', 'crew.1.age', 'tags'])
    expect(texts['crew.#']).toBe('2')
    expect(texts['crew.1.name']).toBe('Tom')
  })

  it('starts with one empty item when there is nothing to count', () => {
    expect(rows(ship, {}).filter((row) => row.kind === 'item')).toHaveLength(1)
  })

  it('does not mark a field required inside an item, which can be left out', () => {
    expect(rows(ship, {}).find((row) => row.key === 'crew.0.name').required).toBe(false)
  })

  it('is built from the items that were filled in, and left out when none were', () => {
    const texts = { 'crew.#': '3', 'crew.0.name': 'Ada', 'crew.1.name': '', 'crew.2.name': 'Tom', 'crew.2.age': '40' }

    expect(formToBody(ship, texts)).toEqual({ crew: [{ name: 'Ada' }, { name: 'Tom', age: 40 }] })
    expect(formToBody(ship, { 'crew.#': '2', name: 'Aurora' })).toEqual({ name: 'Aurora' })
  })

  it('comes back as the same texts', () => {
    const body = { name: 'Aurora', crew: [{ name: 'Ada', age: 36 }, { name: 'Tom' }], tags: ['a', 'b'] }

    expect(formToBody(ship, bodyToForm(ship, body))).toEqual(body)
  })

  it('gains an item at the end, and loses one with the rest moving up', () => {
    const texts = bodyToForm(ship, { crew: [{ name: 'Ada' }, { name: 'Bo' }, { name: 'Cy' }] })

    expect(addItem(texts, 'crew')['crew.#']).toBe('4')

    const fewer = removeItem(texts, 'crew', 1)

    expect(fewer['crew.#']).toBe('2')
    expect(formToBody(ship, fewer)).toEqual({ crew: [{ name: 'Ada' }, { name: 'Cy' }] })
    expect(Object.getPrototypeOf(fewer)).toBeNull()
  })
})
