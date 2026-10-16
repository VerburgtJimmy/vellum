/**
 * What sits between a playground's inputs and the request: which control a
 * schema gets, what a typed string means, and how a response is shown.
 *
 * Like request.js, nothing here touches the page.
 */

import { bag } from './request.js'

const blank = (value) => value === undefined || value === null || value === ''

/** The control a schema is edited with. */
export function control(schema = {}) {
  if (Array.isArray(schema.enum) && schema.enum.length > 0) {
    return 'select'
  }

  if (schema.type === 'boolean') {
    return 'boolean'
  }

  if (schema.type === 'integer' || schema.type === 'number') {
    return 'number'
  }

  if (schema.type === 'string') {
    return schema.format === 'binary' ? 'file' : 'text'
  }

  // A list of plain values is typed with commas. Anything with structure of
  // its own is written as JSON.
  if (schema.type === 'array' && ['string', 'integer', 'number'].includes(schema.items?.type) && !schema.items?.enum) {
    return 'list'
  }

  return 'json'
}

/**
 * What a typed string means for a schema. Nothing typed is no value. A value
 * that cannot be what the schema asks for is sent as typed, so the API can
 * say what is wrong with it: that answer is what a playground is for.
 */
export function coerce(schema = {}, raw) {
  if (typeof raw !== 'string') {
    return blank(raw) ? undefined : raw
  }

  const text = raw.trim()

  if (text === '') {
    return undefined
  }

  switch (control(schema)) {
    case 'number': {
      const number = Number(text)

      return Number.isNaN(number) ? raw : number
    }
    case 'boolean':
      return text === 'true' ? true : text === 'false' ? false : raw
    case 'select': {
      const match = schema.enum.find((option) => String(option) === text)

      return match === undefined ? raw : match
    }
    case 'list':
      return text
        .split(',')
        .map((item) => coerce(schema.items, item.trim()))
        .filter((item) => item !== undefined)
    case 'json':
      try {
        return JSON.parse(text)
      } catch {
        return raw
      }
    default:
      return raw
  }
}

/** A value as the text its control shows. */
export function display(schema = {}, value) {
  if (blank(value)) {
    return ''
  }

  if (control(schema) === 'list' && Array.isArray(value)) {
    return value.join(', ')
  }

  return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)
}

/** The fields of an object schema, required ones first. */
export function fields(schema = {}) {
  const required = new Set(schema.required ?? [])

  return Object.entries(schema.properties ?? {})
    .map(([name, property]) => ({ name, schema: property, required: required.has(name) }))
    .sort((a, b) => Number(b.required) - Number(a.required))
}

/** Whether a body can be filled in field by field rather than as one text. */
export function hasForm(body) {
  return body?.schema?.type === 'object' && Object.keys(body.schema.properties ?? {}).length > 0
}

// How far down an object is opened into fields of its own. Past this it is
// written as JSON, like anything else a form has no control for.
const DEPTH = 4

const opens = (schema, depth) => depth < DEPTH && schema?.type === 'object' && Object.keys(schema.properties ?? {}).length > 0

// A list of objects is filled in an item at a time, each with its fields.
const lists = (schema, depth) => schema?.type === 'array' && opens(schema.items, depth)

// More than a reader adds by hand, fewer than a page can draw.
const MAX_ITEMS = 50

const at = (value, path) => path.reduce((here, name) => (here !== null && typeof here === 'object' && Object.hasOwn(here, name) ? here[name] : undefined), value)

/**
 * Everything a form shows for an object schema, in order: a field for each
 * value, and for a list of objects a heading, then each item with its own
 * fields. An object inside gives its fields in its place, under the path
 * that leads to them, so "author.name" is typed into rather than written as
 * JSON. How many items a list has is kept with the texts, under "crew.#".
 *
 * A field is marked required only when leaving it out is always an error:
 * one inside an optional object, or in a list item, is not.
 *
 * `from` is a body to count a list's items by, before the texts say.
 */
export function rows(schema = {}, texts = {}, from = undefined, path = [], required = true, taken = new Set(), within = []) {
  return fields(schema).flatMap((field) => {
    const here = [...path, field.name]
    const needed = required && field.required

    if (opens(field.schema, here.length)) {
      return rows(field.schema, texts, from, here, needed, taken, [...within, field.name])
    }

    // A property that is itself named "a.b" would share a key with b inside a.
    let key = here.join('.')
    key = taken.has(key) ? JSON.stringify(here) : key
    taken.add(key)

    if (lists(field.schema, here.length)) {
      const given = Number(texts[`${key}.#`])
      const found = at(from, here)
      const count = Math.min(MAX_ITEMS, Math.max(0, Number.isInteger(given) && Object.hasOwn(texts, `${key}.#`) ? given : Array.isArray(found) ? found.length : 1))
      const out = [{ kind: 'list', key, name: field.name, path: here, within, required: needed, schema: field.schema, count }]

      for (let index = 0; index < count; index++) {
        out.push({ kind: 'item', list: key, index })
        out.push(...rows(field.schema.items, texts, from, [...here, index], false, taken, []))
      }

      return out
    }

    return [{ key, name: field.name, path: here, within, schema: field.schema, required: needed }]
  })
}

/** The fields alone, without the headings and items lists are laid out with. */
export function leaves(schema = {}, texts = {}) {
  return rows(schema, texts).filter((row) => row.kind === undefined)
}

// A list item none of whose fields were filled in is left out, and so is a
// list that ends up with no items.
const compact = (value) => {
  if (Array.isArray(value)) {
    const items = value.map(compact).filter((item) => item !== undefined)

    return items.length > 0 ? items : undefined
  }

  if (value !== null && typeof value === 'object' && Object.getPrototypeOf(value) === null) {
    for (const name of Object.keys(value)) {
      const kept = compact(value[name])
      kept === undefined ? delete value[name] : (value[name] = kept)
    }

    return Object.keys(value).length > 0 ? value : undefined
  }

  return value
}

/**
 * The body a form's texts make: each field that was filled in, as its
 * schema's type. An object none of whose fields were filled in is left out.
 * A file picked for a field stands in for its text.
 */
export function formToBody(schema, texts, files) {
  const body = bag()

  for (const leaf of leaves(schema, texts)) {
    const value = files && leaf.key in files ? files[leaf.key] : coerce(leaf.schema, texts[leaf.key])

    if (value === undefined) {
      continue
    }

    let here = body

    leaf.path.slice(0, -1).forEach((name, step) => {
      if (!Object.hasOwn(here, name)) {
        here[name] = typeof leaf.path[step + 1] === 'number' ? [] : bag()
      }

      here = here[name]
    })

    here[leaf.path.at(-1)] = value
  }

  return compact(body) ?? bag()
}

/** The texts a form shows for a body. */
export function bodyToForm(schema, body) {
  const texts = bag()

  for (const row of rows(schema, texts, body)) {
    if (row.kind === 'list') {
      texts[`${row.key}.#`] = String(row.count)
    } else if (row.kind === undefined) {
      texts[row.key] = display(row.schema, at(body, row.path))
    }
  }

  return texts
}

/** The texts with one more item at the end of a list. */
export function addItem(texts, key) {
  const next = Object.assign(bag(), texts)
  next[`${key}.#`] = String(Math.min(MAX_ITEMS, Number(texts[`${key}.#`] ?? 0) + 1))

  return next
}

/** The texts without one item of a list, the ones after it moved up. */
export function removeItem(texts, key, index) {
  const count = Number(texts[`${key}.#`] ?? 0)
  const next = bag()
  const prefix = `${key}.`

  for (const [name, value] of Object.entries(texts)) {
    const match = name.startsWith(prefix) ? /^(\d+)(\..*)$/.exec(name.slice(prefix.length)) : null

    if (!match) {
      next[name] = value
    } else if (Number(match[1]) < index) {
      next[name] = value
    } else if (Number(match[1]) > index) {
      next[`${prefix}${Number(match[1]) - 1}${match[2]}`] = value
    }
  }

  next[`${key}.#`] = String(Math.max(0, count - 1))

  return next
}

/** The values a playground opens with: every parameter and the body, from their examples. */
export function initial(operation) {
  const values = { path: bag(), query: bag(), header: bag(), cookie: bag() }

  for (const parameter of operation.parameters ?? []) {
    if (values[parameter.in]) {
      values[parameter.in][parameter.name] = display(parameter.schema, parameter.required ? parameter.example : undefined)
    }
  }

  return values
}

/** Bytes as a reader says them. */
export function size(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`
  }

  return bytes < 1024 * 1024 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** A response body as text to show: JSON set out, anything else as it came. */
export function pretty(text, contentType = '') {
  if (/\bjson\b/i.test(contentType) || /^\s*[[{]/.test(text)) {
    try {
      return JSON.stringify(JSON.parse(text), null, 2)
    } catch {
      return text
    }
  }

  return text
}

/**
 * What to tell a reader whose request got no response. The browser gives one
 * error for a refused connection and for a response it was not allowed to
 * show, so the message says which is likely from where the request went.
 */
export function failure(error, where) {
  if (error?.name === 'AbortError') {
    return 'The request took too long and was stopped.'
  }

  if (where === 'cross') {
    return 'No response the browser could show. The request went to another origin, which has to allow this page to read its answer (CORS). If it does, the server may be down or the address wrong.'
  }

  return 'No response. The server may be down, or the address wrong.'
}
