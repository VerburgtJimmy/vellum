/**
 * Reading a body that arrives a piece at a time, into the items it is made
 * of. Like request.js and form.js, nothing here touches the page.
 */

const ITEMS = {
  'text/event-stream': 'event',
  'application/jsonl': 'line',
  'application/x-jsonlines': 'line',
  'application/x-ndjson': 'line',
  'application/ndjson': 'line',
  'application/json-seq': 'record',
}

/** What one item of this content type is called, or null when it is not a stream. */
export function streamItem(type) {
  return ITEMS[String(type ?? '').split(';')[0].trim().toLowerCase()] ?? null
}

/**
 * Server-sent events, by the rules browsers follow. A line break may be any
 * of three, and may land between two chunks: a CR at the very end is kept
 * back, since an LF may be about to finish it.
 */
function events() {
  let buffer = ''
  let fields = {}
  let data = []

  const line = (text, out) => {
    if (text === '') {
      if (data.length > 0) {
        out.push({ ...fields, data: data.join('\n') })
      }

      fields = {}
      data = []

      return
    }

    if (text.startsWith(':')) {
      return
    }

    const colon = text.indexOf(':')
    const name = colon === -1 ? text : text.slice(0, colon)
    const value = colon === -1 ? '' : text.slice(colon + 1).replace(/^ /, '')

    if (name === 'data') {
      data.push(value)
    } else if (name === 'event' || name === 'id' || (name === 'retry' && /^\d+$/.test(value))) {
      fields[name] = value
    }
  }

  const take = (rest) => {
    const out = []
    const lines = rest.split(/\r\n|\n|\r/)

    buffer = lines.pop()

    for (const text of lines) {
      line(text, out)
    }

    return out
  }

  return {
    push: (text) => {
      const joined = buffer + text
      const held = joined.endsWith('\r')
      const out = take(held ? joined.slice(0, -1) : joined)

      buffer += held ? '\r' : ''

      return out
    },
    // A stream that stops in the middle of an event never finished it.
    end: () => [],
  }
}

/** One JSON value a line. The last may come without a line break after it. */
function lines() {
  let buffer = ''
  const items = (list) => list.map((text) => text.trim()).filter(Boolean).map((data) => ({ data }))

  return {
    push: (text) => {
      const list = (buffer + text).split('\n')

      buffer = list.pop()

      return items(list)
    },
    end: () => {
      const last = items([buffer])

      buffer = ''

      return last
    },
  }
}

/** JSON text sequences: a record separator before each value, a line break after. */
function records() {
  let buffer = ''
  const items = (list) => list.map((text) => text.trim()).filter(Boolean).map((data) => ({ data }))

  return {
    push: (text) => {
      const list = (buffer + text).split('\x1e')
      const last = list.pop()
      const whole = last.endsWith('\n')

      buffer = whole ? '' : last

      return items(whole ? [...list, last] : list)
    },
    end: () => {
      const last = items([buffer])

      buffer = ''

      return last
    },
  }
}

/** A reader for one kind of stream: push text in, get finished items out. */
export function reader(item) {
  return item === 'event' ? events() : item === 'record' ? records() : lines()
}
