/**
 * The API playground: a dialog in which a reader fills in an operation's
 * request, sends it from their own browser and reads what comes back.
 *
 * Loaded the first time a reader presses "Try it". What a request is made of
 * lives in playground/request.js and playground/form.js; this file is the
 * dialog around them.
 */
import { buildRequest, reach, toCurl, xsrfToken } from './playground/request.js'
import { bodyToForm, coerce, control, display, failure, fields, formToBody, hasForm, initial, pretty, size } from './playground/form.js'

const STORE = 'vellum-playground'
const TIMEOUT = 30000
const SHOWN = 400000

/** An element. Text goes in as text, never as markup: much of it is from a spec or a response. */
function h(tag, props = {}, ...children) {
  const el = document.createElement(tag)

  for (const [key, value] of Object.entries(props)) {
    if (value === false || value === null || value === undefined) {
      continue
    }

    if (key === 'text') {
      el.textContent = value
    } else if (key === 'value') {
      el.value = value
    } else if (key.startsWith('on')) {
      el.addEventListener(key.slice(2), value)
    } else {
      el.setAttribute(key, value === true ? '' : value)
    }
  }

  el.append(...children.flat().filter(Boolean))

  return el
}

function remembered() {
  try {
    return JSON.parse(sessionStorage.getItem(STORE) ?? '{}') ?? {}
  } catch {
    return {}
  }
}

function remember(state) {
  try {
    // For this tab only, and gone when it closes: these are credentials.
    sessionStorage.setItem(STORE, JSON.stringify({ auth: state.auth, server: state.server }))
  } catch {
    // Private mode, or storage is full. The playground works without it.
  }
}

/** One control for one schema, reporting what is typed into it. */
function input(schema, text, { id, onInput, required = false, label }) {
  const common = { id, class: 'vellum-playground-control', 'aria-label': label, 'aria-required': required ? 'true' : null }
  const kind = control(schema)

  if (kind === 'select' || kind === 'boolean') {
    const options = kind === 'boolean' ? ['true', 'false'] : schema.enum.map(String)

    return h(
      'select',
      { ...common, onchange: (event) => onInput(event.target.value) },
      h('option', { value: '', text: required ? 'Choose' : 'Not set' }),
      options.map((option) => h('option', { value: option, text: option, selected: option === text })),
    )
  }

  if (kind === 'file') {
    return h('input', { ...common, type: 'file', onchange: (event) => onInput(event.target.files?.[0] ?? null) })
  }

  if (kind === 'json') {
    return h('textarea', {
      ...common,
      rows: Math.min(8, Math.max(2, text.split('\n').length)),
      spellcheck: 'false',
      placeholder: schema.type === 'array' ? '[ ]' : '{ }',
      value: text,
      oninput: (event) => onInput(event.target.value),
    })
  }

  return h('input', {
    ...common,
    type: schema.format === 'password' ? 'password' : 'text',
    inputmode: kind === 'number' ? 'decimal' : null,
    autocomplete: 'off',
    spellcheck: 'false',
    placeholder: kind === 'list' ? 'one, two' : (schema.format ?? ''),
    value: text,
    oninput: (event) => onInput(event.target.value),
  })
}

let fieldCount = 0

/** A labelled row: the name and type on one side, the control on the other. */
function row({ name, type, required, description, child }) {
  const id = `vellum-playground-field-${++fieldCount}`

  return h(
    'div',
    { class: 'vellum-playground-row' },
    h(
      'label',
      { class: 'vellum-playground-name', for: id, title: description || null },
      h('span', { text: name }),
      required ? h('span', { class: 'vellum-playground-required', 'aria-hidden': 'true', text: '*' }) : null,
      h('span', { class: 'vellum-playground-type', text: type }),
    ),
    child(id),
  )
}

function section(title, ...rows) {
  return h('section', { class: 'vellum-playground-section' }, h('h3', { class: 'vellum-playground-heading', text: title }), ...rows)
}

function typeName(schema = {}) {
  if (schema.type === 'array') {
    return `${schema.items?.type ?? 'value'}[]`
  }

  return schema.type === 'json' ? 'JSON' : (schema.type ?? 'value')
}

export function open(button) {
  let operation

  try {
    operation = JSON.parse(button.dataset.vellumPlayground)
  } catch {
    return
  }

  const saved = remembered()
  const bodies = operation.bodies ?? []
  const state = {
    server: operation.servers.includes(saved.server) ? saved.server : (operation.servers[0] ?? window.location.origin),
    texts: initial(operation),
    auth: saved.auth ?? {},
    body: 0,
    mode: {},
    form: bodies.map((body) => bodyToForm(body.schema, body.example)),
    json: bodies.map((body) => display({ type: 'json' }, body.example)),
    files: bodies.map(() => ({})),
  }

  bodies.forEach((body, index) => {
    const structured = /json/i.test(body.contentType)
    state.mode[index] = hasForm(body) ? 'form' : structured ? 'json' : 'raw'
  })

  let controller = null

  /** The request as it stands, from everything typed so far. */
  const request = () => {
    const values = { path: {}, query: {}, header: {} }

    for (const parameter of operation.parameters) {
      if (values[parameter.in]) {
        values[parameter.in][parameter.name] = coerce(parameter.schema, state.texts[parameter.in][parameter.name])
      }
    }

    const body = bodies[state.body]

    if (body) {
      const multipart = body.contentType === 'multipart/form-data'

      if (state.mode[state.body] === 'form') {
        const filled = formToBody(body.schema, state.form[state.body])
        Object.assign(filled, multipart ? state.files[state.body] : {})
        values.body = Object.keys(filled).length > 0 ? filled : undefined
      } else {
        values.body = state.mode[state.body] === 'json' ? coerce({ type: 'json' }, state.json[state.body]) : state.json[state.body]
      }
    }

    const auth = operation.security.map((scheme) => ({
      type: scheme.type,
      in: scheme.in,
      name: scheme.key,
      ...(state.auth[scheme.name] ?? {}),
    }))

    return buildRequest({ method: operation.method, path: operation.path, body: body ?? null }, state.server, values, auth)
  }

  const curl = h('code', {})
  const result = h('div', { class: 'vellum-playground-response', 'aria-live': 'polite' })
  const send = h('button', { type: 'submit', class: 'vellum-playground-send' }, h('span', { text: 'Send' }), h('kbd', { text: '⌘↵', 'aria-hidden': 'true' }))

  const refresh = () => {
    curl.textContent = toCurl(request())
  }

  const edit = (apply) => (value) => {
    apply(value)
    refresh()
  }

  // The request line: where it goes.
  const bar = h(
    'div',
    { class: 'vellum-playground-bar', 'data-method': operation.method.toLowerCase() },
    h('span', { class: 'vellum-api-method', 'data-method': operation.method.toLowerCase(), text: operation.method }),
    h('input', {
      class: 'vellum-playground-server',
      'aria-label': 'Server',
      list: 'vellum-playground-servers',
      spellcheck: 'false',
      autocomplete: 'off',
      value: state.server,
      size: Math.max(12, state.server.length),
      oninput: (event) => {
        state.server = event.target.value.trim()
        event.target.size = Math.max(12, state.server.length)
        remember(state)
        refresh()
      },
    }),
    h('datalist', { id: 'vellum-playground-servers' }, operation.servers.map((server) => h('option', { value: server }))),
    h('code', { class: 'vellum-playground-path', text: operation.path }),
    send,
  )

  const sections = []

  if (operation.security.length > 0) {
    sections.push(
      section(
        'Authorization',
        operation.security.map((scheme) => {
          const stored = (state.auth[scheme.name] ??= {})
          const field = (key, label, secret) =>
            row({
              name: label,
              type: scheme.type === 'apiKey' ? scheme.in : 'header',
              description: scheme.description,
              child: (id) =>
                h('input', {
                  id,
                  class: 'vellum-playground-control',
                  type: secret ? 'password' : 'text',
                  autocomplete: 'off',
                  spellcheck: 'false',
                  placeholder: scheme.type === 'bearer' ? 'Token' : '',
                  value: stored[key] ?? '',
                  oninput: (event) => {
                    stored[key] = event.target.value
                    remember(state)
                    refresh()
                  },
                }),
            })

          return scheme.type === 'basic'
            ? [field('username', 'Username', false), field('password', 'Password', true)]
            : field('value', scheme.type === 'apiKey' ? scheme.key : scheme.name, true)
        }),
      ),
    )
  }

  for (const [location, title] of [['path', 'Path'], ['query', 'Query'], ['header', 'Headers']]) {
    const parameters = operation.parameters.filter((parameter) => parameter.in === location)

    if (parameters.length > 0) {
      sections.push(
        section(
          title,
          parameters.map((parameter) =>
            row({
              name: parameter.name,
              type: typeName(parameter.schema),
              required: parameter.required,
              description: parameter.description,
              child: (id) =>
                input(parameter.schema, state.texts[location][parameter.name], {
                  id,
                  required: parameter.required,
                  onInput: edit((value) => (state.texts[location][parameter.name] = value)),
                }),
            }),
          ),
        ),
      )
    }
  }

  if (bodies.length > 0) {
    const holder = h('div', {})

    const drawBody = () => {
      const index = state.body
      const body = bodies[index]
      const multipart = body.contentType === 'multipart/form-data'
      const mode = state.mode[index]

      const whole = () =>
        h('textarea', {
          class: 'vellum-playground-control vellum-playground-json',
          'aria-label': 'Request body',
          rows: Math.min(18, Math.max(4, state.json[index].split('\n').length)),
          spellcheck: 'false',
          value: state.json[index],
          oninput: (event) => {
            state.json[index] = event.target.value
            refresh()
          },
        })

      const byField = () =>
        fields(body.schema).map((field) =>
          row({
            name: field.name,
            type: field.schema.format === 'binary' ? 'file' : typeName(field.schema),
            required: field.required,
            description: field.schema.description,
            child: (id) =>
              input(field.schema, state.form[index][field.name] ?? '', {
                id,
                required: field.required,
                onInput: edit((value) => {
                  if (control(field.schema) === 'file') {
                    value ? (state.files[index][field.name] = value) : delete state.files[index][field.name]
                  } else {
                    state.form[index][field.name] = value
                  }
                }),
              }),
          }),
        )

      holder.replaceChildren(...(mode === 'form' ? byField() : [whole()]))

      // Both ways of writing the body, when it can be written both ways.
      modes.replaceChildren(
        ...(hasForm(body) && !multipart && /json/i.test(body.contentType)
          ? ['form', 'json'].map((name) =>
              h('button', {
                type: 'button',
                class: 'vellum-playground-mode',
                'aria-pressed': String(mode === name),
                text: name === 'form' ? 'Form' : 'JSON',
                onclick: () => {
                  if (mode === name) {
                    return
                  }

                  // Carry what was written across, so switching loses nothing.
                  if (name === 'json') {
                    state.json[index] = display({ type: 'json' }, formToBody(body.schema, state.form[index]))
                  } else {
                    const parsed = coerce({ type: 'json' }, state.json[index])
                    state.form[index] = bodyToForm(body.schema, typeof parsed === 'object' ? parsed : {})
                  }

                  state.mode[index] = name
                  drawBody()
                  refresh()
                },
              }),
            )
          : []),
      )
    }

    const modes = h('div', { class: 'vellum-playground-modes', role: 'group', 'aria-label': 'How to write the body' })
    const types =
      bodies.length > 1
        ? h(
            'select',
            {
              class: 'vellum-playground-control vellum-playground-types',
              'aria-label': 'Content type',
              onchange: (event) => {
                state.body = Number(event.target.value)
                drawBody()
                refresh()
              },
            },
            bodies.map((body, index) => h('option', { value: String(index), text: body.contentType })),
          )
        : h('span', { class: 'vellum-playground-type', text: bodies[0].contentType })

    const heading = h('h3', { class: 'vellum-playground-heading' }, h('span', { text: 'Body' }), types, modes)
    sections.push(h('section', { class: 'vellum-playground-section' }, heading, holder))
    drawBody()
  }

  if (sections.length === 0) {
    sections.push(h('p', { class: 'vellum-playground-empty', text: 'This request takes nothing. Send it as it is.' }))
  }

  const copy = h('button', {
    type: 'button',
    class: 'vellum-playground-copy',
    text: 'Copy',
    onclick: () => {
      navigator.clipboard?.writeText(curl.textContent).then(() => {
        copy.textContent = 'Copied'
        setTimeout(() => (copy.textContent = 'Copy'), 1500)
      })
    },
  })

  const show = (...nodes) => result.replaceChildren(...nodes.filter(Boolean))

  show(h('p', { class: 'vellum-playground-empty', text: 'The response shows here once you send the request.' }))

  const submit = async (event) => {
    event.preventDefault()

    if (controller) {
      controller.abort()

      return
    }

    const missing = form.querySelector('[aria-required="true"]:placeholder-shown, select[aria-required="true"]:has(option[value=""]:checked)')
    const built = request()

    // A path with a gap in it is not an address. Everything else the API can
    // refuse for itself, and its refusal is worth seeing.
    if (/\{[^}]+\}/.test(built.url.split('?')[0]) && missing) {
      missing.focus()
      show(h('p', { class: 'vellum-playground-error', text: 'Fill in the path first.' }))

      return
    }

    const where = reach(built.url, window.location.origin)

    if (where === 'invalid') {
      show(h('p', { class: 'vellum-playground-error', text: 'That server is not an address the browser can reach.' }))

      return
    }

    const headers = { ...built.headers }
    const token = where === 'direct' ? xsrfToken(document.cookie) : null

    // Laravel refuses a state-changing request from a signed-in browser
    // without this, and the reader on the app's own origin is exactly that.
    if (token && !['GET', 'HEAD'].includes(built.method)) {
      headers['X-XSRF-TOKEN'] = token
    }

    let body = built.body

    if (built.files) {
      body = new FormData()

      for (const [name, value] of Object.entries(built.files)) {
        body.append(name, value instanceof Blob ? value : typeof value === 'object' ? JSON.stringify(value) : String(value))
      }
    }

    controller = new AbortController()
    const timer = setTimeout(() => controller?.abort(), TIMEOUT)
    const started = performance.now()

    send.firstChild.textContent = 'Stop'
    form.setAttribute('aria-busy', 'true')
    show(h('p', { class: 'vellum-playground-empty', text: 'Sending.' }))

    try {
      const response = await fetch(built.url, {
        method: built.method,
        headers,
        body,
        // The reader's own session on the app's own origin, and nothing of
        // theirs anywhere else.
        credentials: where === 'direct' ? 'same-origin' : 'omit',
        cache: 'no-store',
        signal: controller.signal,
      })

      const type = response.headers.get('content-type') ?? ''
      const textual = type === '' || /^text\/|json|xml|javascript|x-www-form-urlencoded|csv|yaml/i.test(type)
      const blob = await response.blob()
      const elapsed = Math.round(performance.now() - started)
      const text = textual ? await blob.text() : ''
      const kind = response.status >= 500 ? '5xx' : response.status >= 400 ? '4xx' : response.status >= 300 ? '3xx' : '2xx'

      show(
        h(
          'p',
          { class: 'vellum-playground-status' },
          h('span', { class: 'vellum-api-status', 'data-status': kind, text: `${response.status} ${response.statusText}`.trim() }),
          h('span', { text: `${elapsed} ms` }),
          h('span', { text: size(blob.size) }),
        ),
        // As text, always. A response is someone else's content, and this
        // page is not the place it gets to run.
        textual
          ? h('pre', { class: 'vellum-playground-output', tabindex: '0' }, h('code', { text: pretty(text, type).slice(0, SHOWN) }))
          : h('p', { class: 'vellum-playground-empty', text: `${type || 'A response'} that is not text, so it is not shown.` }),
        text.length > SHOWN ? h('p', { class: 'vellum-playground-empty', text: 'Only the start of the response is shown.' }) : null,
        h(
          'details',
          { class: 'vellum-playground-headers' },
          h('summary', { text: 'Response headers' }),
          h('pre', {}, h('code', { text: [...response.headers].map(([name, value]) => `${name}: ${value}`).join('\n') || 'None the browser may show.' })),
        ),
      )
    } catch (error) {
      show(h('p', { class: 'vellum-playground-error', text: failure(error, where) }))
    } finally {
      clearTimeout(timer)
      controller = null
      send.firstChild.textContent = 'Send'
      form.removeAttribute('aria-busy')
    }
  }

  const form = h(
    'form',
    { class: 'vellum-playground-form', onsubmit: submit, novalidate: true },
    bar,
    h(
      'div',
      { class: 'vellum-playground-columns' },
      h('div', { class: 'vellum-playground-request' }, ...sections),
      h(
        'div',
        { class: 'vellum-playground-result' },
        h('div', { class: 'vellum-playground-curl' }, h('div', { class: 'vellum-playground-heading' }, h('span', { text: 'cURL' }), copy), h('pre', { tabindex: '0' }, curl)),
        h('div', { class: 'vellum-playground-heading' }, h('span', { text: 'Response' })),
        result,
      ),
    ),
  )

  const dialog = h(
    'dialog',
    {
      class: 'vellum-playground',
      'aria-label': `Try ${operation.title}`,
      onclose: () => {
        controller?.abort()
        dialog.remove()
      },
      // A press on the backdrop lands on the dialog itself.
      onclick: (event) => event.target === dialog && dialog.close(),
      onkeydown: (event) => {
        if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
          event.preventDefault()
          form.requestSubmit()
        }
      },
    },
    h(
      'div',
      { class: 'vellum-playground-panel' },
      h(
        'header',
        { class: 'vellum-playground-header' },
        h('h2', { class: 'vellum-playground-title', text: operation.title }),
        h('button', { type: 'button', class: 'vellum-playground-close', 'aria-label': 'Close', text: '×', onclick: () => dialog.close() }),
      ),
      form,
    ),
  )

  refresh()
  document.body.append(dialog)
  dialog.showModal()

  // Start where the reader starts: the first thing to fill in, or Send.
  ;(form.querySelector('.vellum-playground-request .vellum-playground-control') ?? send).focus()
}
