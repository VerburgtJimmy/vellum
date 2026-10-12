/**
 * The API playground: a dialog in which a reader fills in an operation's
 * request, sends it from their own browser and reads what comes back.
 *
 * Loaded the first time a reader presses "Try it". What a request is made of
 * lives in playground/request.js and playground/form.js; this file is the
 * dialog around them.
 */
import { bag, buildRequest, reach, toCurl, xsrfToken } from './playground/request.js'
import { bodyToForm, coerce, control, display, failure, formToBody, hasForm, initial, leaves, pretty, size } from './playground/form.js'

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
    sessionStorage.setItem(STORE, JSON.stringify({ auth: state.auth, servers: state.servers }))
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

/** Sends the request from this browser, and reads back what it may. */
async function direct(built, where, token, signal) {
  const headers = { ...built.headers }

  // Laravel refuses a state-changing request from a signed-in browser
  // without this, and the reader on the app's own origin is exactly that.
  if (where === 'direct' && token && !['GET', 'HEAD'].includes(built.method)) {
    headers['X-XSRF-TOKEN'] = token
  }

  let body = built.body

  if (built.files) {
    body = new FormData()

    for (const [name, value] of Object.entries(built.files)) {
      body.append(name, value instanceof Blob ? value : typeof value === 'object' ? JSON.stringify(value) : String(value))
    }
  }

  const started = performance.now()
  const response = await fetch(built.url, {
    method: built.method,
    headers,
    body,
    // The reader's own session on the app's own origin, and nothing of
    // theirs anywhere else.
    credentials: where === 'direct' ? 'same-origin' : 'omit',
    cache: 'no-store',
    signal,
  })

  const type = response.headers.get('content-type') ?? ''
  const blob = await response.blob()

  return {
    status: response.status,
    statusText: response.statusText,
    headers: [...response.headers],
    type,
    text: type === '' || /^text\/|json|xml|javascript|x-www-form-urlencoded|csv|yaml/i.test(type) ? await blob.text() : null,
    size: blob.size,
    elapsed: Math.round(performance.now() - started),
    truncated: false,
  }
}

/** Has this site's server send the request, and reads its account of the answer. */
async function forwarded(proxy, built, token, signal) {
  const response = await fetch(proxy, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { 'X-XSRF-TOKEN': token } : {}) },
    body: JSON.stringify({ method: built.method, url: built.url, headers: built.headers, body: built.body }),
    credentials: 'same-origin',
    cache: 'no-store',
    signal,
  })

  const data = await response.json().catch(() => null)

  if (!response.ok || !data || typeof data.status !== 'number') {
    const reasons = {
      419: 'This page has been open too long to send from. Reload it and try again.',
      429: 'Too many requests from here for now. Wait a minute and try again.',
    }

    return { error: data?.error ?? reasons[response.status] ?? 'This site could not send the request.' }
  }

  return {
    status: data.status,
    statusText: String(data.statusText ?? ''),
    headers: Array.isArray(data.headers) ? data.headers.filter((pair) => Array.isArray(pair)).map(([name, value]) => [String(name), String(value)]) : [],
    type: String(data.type ?? ''),
    text: typeof data.body === 'string' ? data.body : null,
    size: Number(data.size) || 0,
    elapsed: Number(data.elapsed) || 0,
    truncated: data.truncated === true,
  }
}

let fieldCount = 0

/** A labelled row: the name and type on one side, the control on the other. */
function row({ name, within = [], type, required, description, child }) {
  const id = `vellum-playground-field-${++fieldCount}`

  return h(
    'div',
    { class: 'vellum-playground-row' },
    h(
      'label',
      { class: 'vellum-playground-name', for: id, title: description || null },
      // The objects a field sits in, said before its name: author.name
      h('span', {}, within.length > 0 ? h('span', { class: 'vellum-playground-within', text: `${within.join('.')}.` }) : null, name),
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
  const first = operation.servers[0] ?? window.location.origin

  // Everything kept by a name from the spec is kept in an object without a
  // prototype. What was saved is copied in key by key for the same reason.
  const auth = bag()
  const servers = bag()

  for (const [name, value] of Object.entries(saved.auth ?? {})) {
    if (value && typeof value === 'object') {
      auth[name] = Object.assign(bag(), value)
    }
  }

  for (const [name, value] of Object.entries(saved.servers ?? {})) {
    if (typeof value === 'string') {
      servers[name] = value
    }
  }

  const state = {
    // The server last used in place of the spec's first, whatever it was.
    server: servers[first] ?? first,
    servers,
    texts: initial(operation),
    auth,
    body: 0,
    mode: [],
    form: bodies.map((body) => bodyToForm(body.schema, body.example)),
    json: bodies.map((body) => display({ type: 'json' }, body.example)),
    files: bodies.map(() => bag()),
  }

  bodies.forEach((body, index) => {
    const structured = /json/i.test(body.contentType)
    state.mode[index] = hasForm(body) ? 'form' : structured ? 'json' : 'raw'
  })

  let controller = null

  /** The request as it stands, from everything typed so far. */
  const request = () => {
    const values = { path: bag(), query: bag(), header: bag() }

    for (const parameter of operation.parameters) {
      if (values[parameter.in]) {
        values[parameter.in][parameter.name] = coerce(parameter.schema, state.texts[parameter.in][parameter.name])
      }
    }

    const body = bodies[state.body]

    if (body) {
      const multipart = body.contentType === 'multipart/form-data'

      if (state.mode[state.body] === 'form') {
        const filled = formToBody(body.schema, state.form[state.body], multipart ? state.files[state.body] : undefined)
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
        state.servers[first] = state.server
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
          const stored = (state.auth[scheme.name] ??= bag())
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
        leaves(body.schema).map((leaf) =>
          row({
            name: leaf.name,
            within: leaf.path.slice(0, -1),
            type: leaf.schema.format === 'binary' ? 'file' : typeName(leaf.schema),
            required: leaf.required,
            description: leaf.schema.description,
            child: (id) =>
              input(leaf.schema, state.form[index][leaf.key] ?? '', {
                id,
                required: leaf.required,
                onInput: edit((value) => {
                  if (control(leaf.schema) === 'file') {
                    value ? (state.files[index][leaf.key] = value) : delete state.files[index][leaf.key]
                  } else {
                    state.form[index][leaf.key] = value
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
                    state.form[index] = bodyToForm(body.schema, parsed && typeof parsed === 'object' ? parsed : {})
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

  // The command and the response share one place, a tab each. There is no
  // Response tab until there is something to put under it.
  let tab = 'curl'
  let answered = false
  const command = h('pre', {}, curl)
  const tabs = h('div', { class: 'vellum-playground-modes' })
  const shown = () => (tab === 'curl' ? curl : result.querySelector('.vellum-playground-output'))

  const copy = h('button', {
    type: 'button',
    class: 'vellum-playground-copy',
    text: 'Copy',
    onclick: () => {
      navigator.clipboard?.writeText(shown()?.textContent ?? '').then(() => {
        copy.textContent = 'Copied'
        setTimeout(() => (copy.textContent = 'Copy'), 1500)
      })
    },
  })

  const output = h('div', { class: 'vellum-playground-result' }, h('div', { class: 'vellum-playground-heading' }, tabs, copy), command, result)

  const drawTabs = () => {
    tabs.replaceChildren(
      ...(answered ? ['curl', 'response'] : ['curl']).map((name) =>
        h('button', {
          type: 'button',
          class: 'vellum-playground-mode',
          'aria-pressed': String(tab === name),
          text: name === 'curl' ? 'cURL' : 'Response',
          onclick: () => {
            tab = name
            drawTabs()
          },
        }),
      ),
    )

    command.hidden = tab !== 'curl'
    result.hidden = tab !== 'response'
    copy.hidden = !shown()
  }

  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  // An answer takes the tab and is brought into view, rather than left for
  // the reader to find under a long form.
  const show = (...nodes) => {
    result.replaceChildren(...nodes.filter(Boolean))
    answered = true
    tab = 'response'
    drawTabs()
    output.scrollIntoView({ block: 'nearest', behavior: calm ? 'auto' : 'smooth' })
  }

  drawTabs()

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

    // Another origin goes through this site's own server when it offers to,
    // and only that way: a request the browser sent and could not read back
    // has still been sent, and sending it twice is not a retry.
    const proxy = where === 'cross' ? document.documentElement.dataset.vellumPlaygroundProxy : null
    const token = xsrfToken(document.cookie)

    if (proxy && built.files) {
      show(h('p', { class: 'vellum-playground-error', text: 'A file cannot be sent to another origin through this site. Use the command above.' }))

      return
    }

    controller = new AbortController()
    const timer = setTimeout(() => controller?.abort(), TIMEOUT)

    send.firstChild.textContent = 'Stop'
    form.setAttribute('aria-busy', 'true')
    show(h('p', { class: 'vellum-playground-empty', text: 'Sending.' }))

    try {
      const answer = proxy ? await forwarded(proxy, built, token, controller.signal) : await direct(built, where, token, controller.signal)

      if (answer.error) {
        show(h('p', { class: 'vellum-playground-error', text: answer.error }))

        return
      }

      const kind = answer.status >= 500 ? '5xx' : answer.status >= 400 ? '4xx' : answer.status >= 300 ? '3xx' : '2xx'

      show(
        h(
          'p',
          { class: 'vellum-playground-status' },
          h('span', { class: 'vellum-api-status', 'data-status': kind, text: `${answer.status} ${answer.statusText}`.trim() }),
          h('span', { text: `${answer.elapsed} ms` }),
          h('span', { text: size(answer.size) }),
          proxy ? h('span', { class: 'vellum-playground-via', text: 'Sent through this site' }) : null,
        ),
        // As text, always. A response is someone else's content, and this
        // page is not the place it gets to run.
        answer.text !== null
          ? h('pre', { class: 'vellum-playground-output' }, h('code', { text: pretty(answer.text, answer.type).slice(0, SHOWN) }))
          : h('p', { class: 'vellum-playground-empty', text: `${answer.type || 'A response'} that is not text, so it is not shown.` }),
        answer.truncated || (answer.text ?? '').length > SHOWN
          ? h('p', { class: 'vellum-playground-empty', text: 'Only the start of the response is shown.' })
          : null,
        h(
          'details',
          { class: 'vellum-playground-headers' },
          h('summary', { text: 'Response headers' }),
          h('pre', {}, h('code', { text: answer.headers.map(([name, value]) => `${name}: ${value}`).join('\n') || 'None the browser may show.' })),
        ),
      )
    } catch (error) {
      show(h('p', { class: 'vellum-playground-error', text: failure(error, proxy ? 'direct' : where) }))
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
      output,
    ),
  )

  const dialog = h(
    'dialog',
    {
      class: 'vellum-playground',
      'aria-label': `Try ${operation.title}`,
      onclose: () => {
        controller?.abort()
        // Gone once it has slid out of view, not before.
        Promise.allSettled(dialog.getAnimations().map((animation) => animation.finished)).then(() => dialog.remove())
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
