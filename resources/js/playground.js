/**
 * The API playground: a dialog in which a reader fills in an operation's
 * request, sends it from their own browser and reads what comes back.
 *
 * Loaded the first time a reader presses "Try it". What a request is made of
 * lives in playground/request.js and playground/form.js; this file is the
 * dialog around them.
 */
import { bag, buildRequest, reach, toCurl, xsrfToken } from './playground/request.js'
import { authorizeUrl, fromDiscovery, pkce, random, readToken, tokenRequest } from './playground/oauth.js'
import { addItem, bodyToForm, coerce, control, display, failure, formToBody, hasForm, initial, pretty, reason, removeItem, rows, size, tokens } from './playground/form.js'
import { reader, streamItem } from './playground/stream.js'
import { english, t, tc } from './strings.js'
import { playground } from './strings.en.json'

english({ playground })

const STORE = 'vellum-playground'
const TIMEOUT = 30000
const SHOWN = 400000
// Set out piece by piece up to here, and as plain text past it.
const HIGHLIGHTED = 100000
// The latest items of a stream stay on the page. The reader is watching the end of it.
const KEPT = 200

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
      h('option', { value: '', text: t(required ? 'playground.choose' : 'playground.not_set') }),
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
    placeholder: kind === 'list' ? t('playground.list_example') : (schema.format ?? ''),
    value: text,
    oninput: (event) => onInput(event.target.value),
  })
}

/**
 * Sends the request from this browser, and reads back what it may. A stream
 * is handed to `follow` as soon as it begins, which reads it to the end.
 */
async function direct(built, where, token, signal, follow) {
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

  if (follow && response.ok && response.body && streamItem(type)) {
    await follow({ status: response.status, statusText: response.statusText, headers: [...response.headers], type }, response.body)

    return null
  }

  const blob = await response.blob()
  const textual = type === '' || /^text\/|json|xml|javascript|x-www-form-urlencoded|csv|yaml/i.test(type)

  return {
    status: response.status,
    statusText: response.statusText,
    headers: [...response.headers],
    type,
    text: textual ? await blob.text() : null,
    blob: textual ? null : blob,
    size: blob.size,
    elapsed: Math.round(performance.now() - started),
    truncated: false,
  }
}

/**
 * Sends a request the way any playground request goes: directly, or through
 * this app when that is how it is set up. `forward` false keeps it in the
 * browser, for an address the forwarding route was never told about.
 */
function dispatch(built, signal, forward = true) {
  const where = reach(built.url, window.location.origin)
  const proxy = where === 'cross' && forward ? document.documentElement.dataset.vellumPlaygroundProxy : null
  const token = xsrfToken(document.cookie)

  return proxy ? forwarded(proxy, built, token, signal) : direct(built, where, token, signal)
}

/**
 * The reader signing in with the provider, in a window of its own. Resolves
 * with the code the callback page hands back, once its state matches.
 */
function signIn(url, state) {
  return new Promise((resolve, reject) => {
    const popup = window.open(url, 'vellum-oauth', 'popup,width=520,height=720')

    if (!popup) {
      reject(new Error(t('playground.oauth.blocked')))

      return
    }

    const done = (finish) => {
      window.removeEventListener('message', listen)
      clearInterval(watch)
      finish()
    }

    // Only the window this opened, on this origin, with this request's state.
    const listen = (event) => {
      if (event.origin !== window.location.origin || event.source !== popup || event.data?.type !== 'vellum-oauth' || event.data.state !== state) {
        return
      }

      done(() =>
        event.data.error
          ? reject(new Error(`${event.data.error}${event.data.description ? `: ${event.data.description}` : ''}`))
          : resolve(String(event.data.code ?? '')),
      )
    }

    const watch = setInterval(() => {
      if (popup.closed) {
        done(() => reject(new Error(t('playground.oauth.closed'))))
      }
    }, 500)

    window.addEventListener('message', listen)
  })
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
      419: t('playground.errors.expired'),
      429: t('playground.errors.throttled'),
    }

    return { error: data?.error ?? reasons[response.status] ?? t('playground.errors.not_sent') }
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

/** JSON coloured in, or plain text past the size where that stops being worth it. */
function coloured(text) {
  return text.length > HIGHLIGHTED ? [text] : tokens(text).map(([name, piece]) => (name ? h('span', { class: name, text: piece }) : piece))
}

/** The line above a response: how it went, how long it took, how much came. */
function statusLine(answer, ...facts) {
  const kind = answer.status >= 500 ? '5xx' : answer.status >= 400 ? '4xx' : answer.status >= 300 ? '3xx' : '2xx'

  return h(
    'p',
    { class: 'vellum-playground-status' },
    h('span', { class: 'vellum-api-status', 'data-status': kind, text: reason(answer.status, answer.statusText) }),
    ...facts,
  )
}

function responseHeaders(answer) {
  return h(
    'details',
    { class: 'vellum-playground-headers' },
    h('summary', { text: answer.headers.length > 0 ? t('playground.response.headers_count', { count: answer.headers.length }) : t('playground.response.headers') }),
    h('pre', {}, h('code', { text: answer.headers.map(([name, value]) => `${name}: ${value}`).join('\n') || t('playground.response.no_headers') })),
  )
}

/**
 * A stream as a list that grows. `scroller` is what the reader scrolls: one
 * who is at the bottom stays at the bottom, and one who scrolled up to read
 * is left there.
 */
function streamView(answer, item, scroller, { via = null, live = true } = {}) {
  const list = h('ol', { class: 'vellum-playground-stream' })
  const count = h('span', {})
  const amount = h('span', {})
  const state = live ? h('span', { class: 'vellum-playground-live', text: t('playground.stream.live') }) : null
  const started = performance.now()
  const raw = { text: '' }
  let items = 0
  let bytes = 0
  const noun = (count) => tc(`playground.stream.items.${item}`, count)

  const say = () => {
    count.textContent = t('playground.stream.count', { count: items, item: noun(items) })
    amount.textContent = size(bytes)
  }

  const row = (entry) =>
    h(
      'li',
      { class: 'vellum-playground-entry' },
      live ? h('span', { class: 'vellum-playground-entry-at', text: `${((performance.now() - started) / 1000).toFixed(1)} s` }) : null,
      entry.event ? h('span', { class: 'vellum-playground-entry-name', text: entry.event }) : null,
      h('code', {}, ...coloured(entry.data)),
    )

  const empty = h('li', { class: 'vellum-playground-entry-waiting', text: t('playground.stream.waiting', { item: noun(1) }) })

  list.append(empty)
  say()

  return {
    raw,
    nodes: [statusLine(answer, state, count, amount, via), list, responseHeaders(answer)],
    /** Text as it came, the items it finished, and how many bytes it was. */
    add(text, finished, length = text.length) {
      const following = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 48

      bytes += length
      raw.text = (raw.text + text).slice(0, SHOWN)
      items += finished.length
      empty.remove()
      list.append(...finished.map(row))

      while (list.children.length > KEPT) {
        list.firstChild.remove()
      }

      say()

      if (following) {
        scroller.scrollTop = scroller.scrollHeight
      }
    },
    end(words) {
      empty.remove()

      if (state) {
        state.textContent = words
        state.dataset.state = 'ended'
      }

      if (items === 0) {
        list.append(h('li', { class: 'vellum-playground-entry-waiting', text: t('playground.stream.none', { items: noun(2) }) }))
      }
    },
  }
}

/**
 * A response that is not text. A picture is shown, and anything can be saved.
 * Only a raster image goes on the page, and a link only ever saves: the bytes
 * are the API's, and a blob address opened as a page would run as this site.
 */
function binaryView(answer, urls) {
  if (!answer.blob) {
    return h('p', { class: 'vellum-playground-empty', text: t('playground.response.not_text', { type: answer.type || t('playground.response.a_response') }) })
  }

  const address = (blob) => {
    const url = URL.createObjectURL(blob)

    urls.push(url)

    return url
  }

  const disposition = answer.headers.find(([name]) => name.toLowerCase() === 'content-disposition')?.[1] ?? ''
  const name = /filename="?([^";]+)/i.exec(disposition)?.[1] ?? 'response'

  return h(
    'div',
    { class: 'vellum-playground-file' },
    /^image\/(png|jpeg|gif|webp|avif)\b/i.test(answer.type) ? h('img', { src: address(answer.blob), alt: t('playground.response.image') }) : null,
    h('a', { href: address(new Blob([answer.blob], { type: 'application/octet-stream' })), download: name, text: t('playground.response.download', { size: size(answer.size) }) }),
  )
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

const FLOWS = { authorizationCode: 'playground.oauth.sign_in', clientCredentials: 'playground.oauth.client_credentials' }

/**
 * Getting a token for an OAuth scheme: the flow, the client, the scopes,
 * and a button that runs it. What is typed is kept for the tab, as the
 * token is, and the token it gets goes in the field above.
 */
function oauth(scheme, stored, use) {
  const kept = (stored.oauth ??= {})
  const status = h('p', { class: 'vellum-playground-oauth-status', role: 'status' })
  const panel = h('div', { class: 'vellum-playground-oauth' })
  const redirect = new URL(document.documentElement.dataset.vellumOauthCallback ?? '_vellum/oauth', window.location.href).href
  let flows = scheme.oauth.flows ?? null

  const say = (text, failed = false) => {
    status.textContent = text
    status.toggleAttribute('data-failed', failed)
  }

  const text = (key, label, secret = false, placeholder = '') =>
    h(
      'label',
      { class: 'vellum-playground-oauth-field' },
      h('span', { text: label }),
      h('input', {
        class: 'vellum-playground-control',
        type: secret ? 'password' : 'text',
        autocomplete: 'off',
        spellcheck: 'false',
        placeholder,
        value: kept[key] ?? '',
        oninput: (event) => (kept[key] = event.target.value),
      }),
    )

  const draw = () => {
    const kinds = Object.keys(flows ?? { authorizationCode: true })
    const kind = kinds.includes(kept.flow) ? kept.flow : kinds[0]
    const flow = flows?.[kind]
    const offered = Object.keys(flow?.scopes ?? {})
    kept.scopes ??= [...new Set([...(scheme.oauth.required ?? []), ...(scheme.oauth.discovery ? ['openid'] : [])])]
    kept.clientId ??= scheme.oauth.clientId

    panel.replaceChildren(
      kinds.length > 1
        ? h(
            'div',
            { class: 'vellum-playground-modes', role: 'group', 'aria-label': t('playground.oauth.modes') },
            kinds.map((name) =>
              h('button', {
                type: 'button',
                class: 'vellum-playground-mode',
                'aria-pressed': String(name === kind),
                text: FLOWS[name] ? t(FLOWS[name]) : name,
                onclick: () => ((kept.flow = name), say(''), draw()),
              }),
            ),
          )
        : null,
      text('clientId', t('playground.oauth.client_id')),
      text('clientSecret', t(kind === 'clientCredentials' ? 'playground.oauth.client_secret' : 'playground.oauth.client_secret_optional'), true),
      offered.length > 0
        ? h(
            'fieldset',
            { class: 'vellum-playground-oauth-scopes' },
            h('legend', { text: t('playground.oauth.scopes') }),
            offered.map((scope) =>
              h(
                'label',
                { title: flow.scopes[scope] || null },
                h('input', {
                  type: 'checkbox',
                  checked: kept.scopes.includes(scope),
                  onchange: (event) => (kept.scopes = event.target.checked ? [...kept.scopes, scope] : kept.scopes.filter((name) => name !== scope)),
                }),
                h('code', { text: scope }),
              ),
            ),
          )
        : h(
            'label',
            { class: 'vellum-playground-oauth-field' },
            h('span', { text: t('playground.oauth.scopes') }),
            h('input', {
              class: 'vellum-playground-control',
              autocomplete: 'off',
              spellcheck: 'false',
              placeholder: 'read write',
              value: kept.scopes.join(' '),
              oninput: (event) => (kept.scopes = event.target.value.split(/\s+/).filter(Boolean)),
            }),
          ),
      h(
        'div',
        { class: 'vellum-playground-oauth-actions' },
        h('button', { type: 'button', class: 'vellum-playground-oauth-go', text: t(kind === 'clientCredentials' ? 'playground.oauth.get_token' : 'playground.oauth.sign_in'), onclick: () => run(kind) }),
        status,
      ),
      kind === 'clientCredentials'
        ? null
        : h('p', { class: 'vellum-playground-oauth-note' }, `${t('playground.oauth.redirect')} `, h('code', { text: redirect })),
    )
  }

  const run = async (kind) => {
    const clientId = (kept.clientId ?? '').trim()

    if (clientId === '') {
      say(t('playground.oauth.need_client'), true)

      return
    }

    try {
      if (!flows && scheme.oauth.discovery) {
        say(t('playground.oauth.reading'))
        const answer = await dispatch({ method: 'GET', url: scheme.oauth.discovery, headers: { Accept: 'application/json' } }, undefined, false)
        flows = answer.error ? null : fromDiscovery(JSON.parse(answer.text ?? 'null'))

        if (!flows) {
          throw new Error(answer.error ?? t('playground.oauth.unreadable'))
        }
      }

      const flow = flows[kind]
      // The reader picks scopes once for every flow. Those a flow does not
      // list are hidden while it is chosen, and are not sent with it either.
      const offered = Object.keys(flow.scopes ?? {})
      const scopes = offered.length > 0 ? kept.scopes.filter((scope) => offered.includes(scope)) : kept.scopes
      let request

      if (kind === 'clientCredentials') {
        request = tokenRequest(flow.tokenUrl, 'client_credentials', { clientId, clientSecret: kept.clientSecret ?? '', scopes })
      } else {
        if (!window.isSecureContext || !crypto.subtle) {
          throw new Error(t('playground.oauth.https'))
        }

        const { verifier, challenge } = await pkce()
        const state = random()
        say(t('playground.oauth.waiting'))
        const code = await signIn(authorizeUrl(flow, { clientId, redirectUri: redirect, scopes, state, challenge, base: window.location.href }), state)
        request = tokenRequest(flow.tokenUrl, 'authorization_code', { clientId, clientSecret: kept.clientSecret ?? '', code, verifier, redirectUri: redirect })
      }

      say(t('playground.oauth.getting'))
      // A provider found through discovery names its own token address,
      // which the forwarding route does not know, so that one stays here.
      const answer = await dispatch(request, undefined, !scheme.oauth.discovery)

      if (answer.error) {
        throw new Error(answer.error)
      }

      const result = readToken(answer.status, answer.text)

      if (result.error) {
        throw new Error(result.error)
      }

      use(result.token)
      const minutes = result.expiresIn ? Math.round(result.expiresIn / 60) : null

      if (kind === 'clientCredentials') {
        say(minutes ? t('playground.oauth.got_for', { minutes }) : t('playground.oauth.got'))
      } else {
        say(minutes ? t('playground.oauth.signed_in_for', { minutes }) : t('playground.oauth.signed_in'))
      }
    } catch (error) {
      say(error instanceof TypeError ? t('playground.oauth.unreachable') : error.message, true)
    }
  }

  draw()

  return panel
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

    return buildRequest({ method: operation.method, path: operation.path, body: body ?? null, accept: operation.accept }, state.server, values, auth)
  }

  const curl = h('code', {})
  const result = h('div', { class: 'vellum-playground-response', 'aria-live': 'polite' })
  const send = h('button', { type: 'submit', class: 'vellum-playground-send' }, h('span', { text: t('playground.send') }), h('kbd', { text: '⌘↵', 'aria-hidden': 'true' }))

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
      'aria-label': t('playground.server'),
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
        t('playground.sections.authorization'),
        operation.security.flatMap((scheme) => {
          const stored = (state.auth[scheme.name] ??= bag())
          const inputs = bag()
          const field = (key, label, secret) =>
            row({
              name: label,
              type: scheme.type === 'apiKey' ? scheme.in : 'header',
              description: scheme.description,
              child: (id) =>
                (inputs[key] = h('input', {
                  id,
                  class: 'vellum-playground-control',
                  type: secret ? 'password' : 'text',
                  autocomplete: 'off',
                  spellcheck: 'false',
                  placeholder: scheme.type === 'bearer' ? t(scheme.oauth ? 'playground.auth.token_or_sign_in' : 'playground.auth.token') : '',
                  value: stored[key] ?? '',
                  oninput: (event) => {
                    stored[key] = event.target.value
                    remember(state)
                    refresh()
                  },
                })),
            })

          if (scheme.type === 'basic') {
            return [field('username', t('playground.auth.username'), false), field('password', t('playground.auth.password'), true)]
          }

          const token = field('value', scheme.type === 'apiKey' ? scheme.key : scheme.name, true)

          return scheme.oauth ? [token, oauth(scheme, stored, (value) => ((inputs.value.value = value), (stored.value = value), remember(state), refresh()))] : token
        }),
      ),
    )
  }

  for (const location of ['path', 'query', 'header']) {
    const title = t(`playground.sections.${location}`)
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
          'aria-label': t('playground.body.label'),
          rows: Math.min(18, Math.max(4, state.json[index].split('\n').length)),
          spellcheck: 'false',
          value: state.json[index],
          oninput: (event) => {
            state.json[index] = event.target.value
            refresh()
          },
        })

      // A list of objects changes how many rows the form has, so it is drawn again.
      const relist = (texts) => {
        state.form[index] = texts
        drawBody()
        refresh()
      }

      const byField = () =>
        rows(body.schema, state.form[index]).map((leaf) => {
          if (leaf.kind === 'list') {
            return h(
              'div',
              { class: 'vellum-playground-list' },
              h(
                'span',
                { class: 'vellum-playground-name' },
                h('span', {}, leaf.within.length > 0 ? h('span', { class: 'vellum-playground-within', text: `${leaf.within.join('.')}.` }) : null, leaf.name),
                leaf.required ? h('span', { class: 'vellum-playground-required', 'aria-hidden': 'true', text: '*' }) : null,
                h('span', { class: 'vellum-playground-type', text: typeName(leaf.schema) }),
              ),
              h('button', { type: 'button', class: 'vellum-playground-item-action', text: t('playground.body.add_item'), onclick: () => relist(addItem(state.form[index], leaf.key)) }),
            )
          }

          if (leaf.kind === 'item') {
            return h(
              'div',
              { class: 'vellum-playground-item' },
              h('span', { text: t('playground.body.item', { number: leaf.index + 1 }) }),
              h('button', {
                type: 'button',
                class: 'vellum-playground-item-action',
                text: t('playground.body.remove'),
                'aria-label': t('playground.body.remove_item', { number: leaf.index + 1 }),
                onclick: () => relist(removeItem(state.form[index], leaf.list, leaf.index)),
              }),
            )
          }

          return row({
            name: leaf.name,
            within: leaf.within,
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
          })
        })

      holder.replaceChildren(...(mode === 'form' ? byField() : [whole()]))

      // Both ways of writing the body, when it can be written both ways.
      modes.replaceChildren(
        ...(hasForm(body) && !multipart && /json/i.test(body.contentType)
          ? ['form', 'json'].map((name) =>
              h('button', {
                type: 'button',
                class: 'vellum-playground-mode',
                'aria-pressed': String(mode === name),
                text: name === 'form' ? t('playground.body.form') : 'JSON',
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

    const modes = h('div', { class: 'vellum-playground-modes', role: 'group', 'aria-label': t('playground.body.modes') })
    const types =
      bodies.length > 1
        ? h(
            'select',
            {
              class: 'vellum-playground-control vellum-playground-types',
              'aria-label': t('playground.body.content_type'),
              onchange: (event) => {
                state.body = Number(event.target.value)
                drawBody()
                refresh()
              },
            },
            bodies.map((body, index) => h('option', { value: String(index), text: body.contentType })),
          )
        : h('span', { class: 'vellum-playground-type', text: bodies[0].contentType })

    const heading = h('h3', { class: 'vellum-playground-heading' }, h('span', { text: t('playground.sections.body') }), types, modes)
    sections.push(h('section', { class: 'vellum-playground-section' }, heading, holder))
    drawBody()
  }

  if (sections.length === 0) {
    sections.push(h('p', { class: 'vellum-playground-empty', text: t('playground.body.nothing') }))
  }

  // The command and the response share one place, a tab each. There is no
  // Response tab until there is something to put under it.
  let tab = 'curl'
  let answered = false
  // A stream's text as it came, which is what copying it should give.
  let raw = null
  const urls = []
  const command = h('pre', {}, curl)
  const tabs = h('div', { class: 'vellum-playground-modes' })
  const shown = () => (tab === 'curl' ? curl : result.querySelector('.vellum-playground-output, .vellum-playground-stream'))

  const copy = h('button', {
    type: 'button',
    class: 'vellum-playground-copy',
    text: t('playground.tabs.copy'),
    onclick: () => {
      navigator.clipboard?.writeText(tab === 'response' && raw ? raw.text : (shown()?.textContent ?? '')).then(() => {
        copy.textContent = t('playground.tabs.copied')
        setTimeout(() => (copy.textContent = t('playground.tabs.copy')), 1500)
      })
    },
  })

  const output = h('div', { class: 'vellum-playground-result' }, h('div', { class: 'vellum-playground-heading' }, tabs, copy), command, result)

  // Made once and kept: a tab that is drawn again would cut its own entrance short.
  const choices = ['curl', 'response'].map((name) =>
    h('button', {
      type: 'button',
      class: 'vellum-playground-mode',
      'data-tab': name,
      text: name === 'curl' ? 'cURL' : t('playground.tabs.response'),
      onclick: () => {
        tab = name
        drawTabs()
      },
    }),
  )

  tabs.append(...choices)

  const drawTabs = () => {
    for (const choice of choices) {
      const name = choice.dataset.tab

      // The Response tab jumps in the once, when the first answer brings it.
      if (name === 'response' && answered && choice.hidden) {
        choice.classList.add('vellum-playground-mode-arriving')
      }

      choice.hidden = name === 'response' && !answered
      choice.setAttribute('aria-pressed', String(tab === name))
    }

    command.hidden = tab !== 'curl'
    result.hidden = tab !== 'response'
    copy.hidden = !shown()
  }

  const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches

  // An answer takes the tab and is brought into view, rather than left for
  // the reader to find under a long form.
  const show = (...nodes) => {
    raw = null
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
      show(h('p', { class: 'vellum-playground-error', text: t('playground.errors.path') }))

      return
    }

    const where = reach(built.url, window.location.origin)

    if (where === 'invalid') {
      show(h('p', { class: 'vellum-playground-error', text: t('playground.errors.server') }))

      return
    }

    // Another origin goes through this site's own server when it offers to,
    // and only that way: a request the browser sent and could not read back
    // has still been sent, and sending it twice is not a retry.
    const proxy = where === 'cross' ? document.documentElement.dataset.vellumPlaygroundProxy : null
    const token = xsrfToken(document.cookie)

    if (proxy && built.files) {
      show(h('p', { class: 'vellum-playground-error', text: t('playground.errors.file') }))

      return
    }

    urls.splice(0).forEach((url) => URL.revokeObjectURL(url))

    // Reads a stream from this browser as it comes, until it ends or the
    // reader stops it. What has arrived by then stays on the page.
    const follow = async (head, body) => {
      const item = streamItem(head.type)
      const view = streamView(head, item, scroller)
      const parse = reader(item)
      const text = new TextDecoder()
      const chunks = body.getReader()

      clearTimeout(timer)
      show(...view.nodes)
      raw = view.raw
      // Announced once it ends, not an item at a time.
      result.setAttribute('aria-live', 'off')

      try {
        for (;;) {
          const { done, value } = await chunks.read()

          if (done) {
            break
          }

          const piece = text.decode(value, { stream: true })

          view.add(piece, parse.push(piece), value.length)
        }

        view.add('', parse.end())
        view.end(t('playground.stream.ended'))
      } catch (error) {
        view.end(t(error?.name === 'AbortError' ? 'playground.stream.stopped' : 'playground.stream.cut_off'))
      } finally {
        result.setAttribute('aria-live', 'polite')
      }
    }

    controller = new AbortController()
    const timer = setTimeout(() => controller?.abort(), TIMEOUT)

    send.firstChild.textContent = t('playground.stop')
    form.setAttribute('aria-busy', 'true')
    show(h('p', { class: 'vellum-playground-empty', text: t('playground.sending') }))

    try {
      const answer = proxy ? await forwarded(proxy, built, token, controller.signal) : await direct(built, where, token, controller.signal, follow)

      // A stream read to its end has already been shown.
      if (!answer) {
        return
      }

      if (answer.error) {
        show(h('p', { class: 'vellum-playground-error', text: answer.error }))

        return
      }

      const via = proxy ? h('span', { class: 'vellum-playground-via', text: t('playground.response.via') }) : null
      const item = answer.status < 300 && answer.text !== null ? streamItem(answer.type) : null

      // This site's server hands over a stream in one piece, when it is done.
      if (item) {
        const view = streamView(answer, item, scroller, { via, live: false })
        const parse = reader(item)

        show(...view.nodes)
        raw = view.raw
        view.add(answer.text, [...parse.push(answer.text), ...parse.end()], answer.size)
        view.end()
        result.insertBefore(
          h('p', { class: 'vellum-playground-empty', text: t(answer.truncated ? 'playground.response.via_truncated' : 'playground.response.via_whole') }),
          result.lastChild,
        )

        return
      }

      show(
        statusLine(answer, h('span', { text: `${answer.elapsed} ms` }), h('span', { text: size(answer.size) }), via),
        // As text, always. A response is someone else's content, and this
        // page is not the place it gets to run.
        answer.text === null
          ? binaryView(answer, urls)
          : answer.text === ''
            ? h('p', { class: 'vellum-playground-empty', text: t('playground.response.no_body') })
            : h('pre', { class: 'vellum-playground-output' }, h('code', {}, ...coloured(pretty(answer.text, answer.type).slice(0, SHOWN)))),
        answer.truncated || (answer.text ?? '').length > SHOWN
          ? h('p', { class: 'vellum-playground-empty', text: t('playground.response.start_only') })
          : null,
        responseHeaders(answer),
      )
    } catch (error) {
      show(h('p', { class: 'vellum-playground-error', text: failure(error, proxy ? 'direct' : where) }))
    } finally {
      clearTimeout(timer)
      controller = null
      send.firstChild.textContent = t('playground.send')
      form.removeAttribute('aria-busy')
    }
  }

  const scroller = h('div', { class: 'vellum-playground-columns' }, h('div', { class: 'vellum-playground-request' }, ...sections), output)
  const form = h('form', { class: 'vellum-playground-form', onsubmit: submit, novalidate: true }, bar, scroller)

  const dialog = h(
    'dialog',
    {
      class: 'vellum-playground',
      'aria-label': t('playground.open', { operation: operation.title }),
      onclose: () => {
        controller?.abort()
        urls.splice(0).forEach((url) => URL.revokeObjectURL(url))
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
        h('button', { type: 'button', class: 'vellum-playground-close', 'aria-label': t('playground.close'), text: '×', onclick: () => dialog.close() }),
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
