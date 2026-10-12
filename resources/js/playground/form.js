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

/**
 * Every control a form shows for an object schema. An object inside it gives
 * its own fields in its place, each under the path that leads to it, so
 * "author.name" is typed into rather than written as JSON.
 *
 * A field is marked required only when leaving it out is always an error:
 * one inside an optional object is not, since the object can be left out.
 */
export function leaves(schema = {}, path = [], required = true, taken = new Set()) {
  return fields(schema).flatMap((field) => {
    const here = [...path, field.name]
    const needed = required && field.required

    if (opens(field.schema, here.length)) {
      return leaves(field.schema, here, needed, taken)
    }

    // A property that is itself named "a.b" would share a key with b inside a.
    let key = here.join('.')
    key = taken.has(key) ? JSON.stringify(here) : key
    taken.add(key)

    return [{ key, name: field.name, path: here, schema: field.schema, required: needed }]
  })
}

/**
 * The body a form's texts make: each field that was filled in, as its
 * schema's type. An object none of whose fields were filled in is left out.
 * A file picked for a field stands in for its text.
 */
export function formToBody(schema, texts, files) {
  const body = bag()

  for (const leaf of leaves(schema)) {
    const value = files && leaf.key in files ? files[leaf.key] : coerce(leaf.schema, texts[leaf.key])

    if (value === undefined) {
      continue
    }

    let at = body

    for (const name of leaf.path.slice(0, -1)) {
      at = Object.hasOwn(at, name) ? at[name] : (at[name] = bag())
    }

    at[leaf.path.at(-1)] = value
  }

  return body
}

/** The texts a form shows for a body. */
export function bodyToForm(schema, body) {
  const texts = bag()

  for (const leaf of leaves(schema)) {
    let value = body

    for (const name of leaf.path) {
      value = value && typeof value === 'object' && Object.hasOwn(value, name) ? value[name] : undefined
    }

    texts[leaf.key] = display(leaf.schema, value)
  }

  return texts
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
