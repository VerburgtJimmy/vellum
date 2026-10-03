/**
 * The desktop sidebar's details: a marker under the current page's entry that
 * slides over from the previous page's entry, a hover highlight that glides
 * between entries, reading progress on the marker, a kept scroll position, and
 * a dot on pages that changed since this reader last opened them. The page
 * itself says so too, under its title.
 *
 * All of it is progressive: without this script the sidebar highlights the
 * current page with a plain background, as the markup already does.
 */

const MARKER_KEY = 'vellum-sidebar-marker'
const SCROLL_KEY = 'vellum-sidebar-scroll'
const READ_PREFIX = 'vellum-read:'

/**
 * The part of an entry still showing inside the folders that contain it, so
 * the marker is clipped the way the entry is while a folder opens or closes.
 * Null when nothing of it shows.
 *
 * @param {{x: number, y: number, w: number, h: number}} rect
 * @param {Array<{y: number, h: number}>} clips
 */
export function visiblePart(rect, clips) {
  let top = rect.y
  let bottom = rect.y + rect.h

  for (const clip of clips) {
    top = Math.max(top, clip.y)
    bottom = Math.min(bottom, clip.y + clip.h)
  }

  return bottom - top < 1 ? null : { x: rect.x, y: top, w: rect.w, h: bottom - top }
}

/**
 * Whether a page changed after the reader last read it. A page they have
 * never opened is not marked: there is nothing it changed from.
 *
 * @param {string} updated  the page's last-updated date, as an ISO string
 * @param {number} readAt  when the reader last opened it, in milliseconds, or 0
 */
export function changedSince(updated, readAt) {
  const changed = Date.parse(updated)

  return readAt > 0 && Number.isFinite(changed) && changed > readAt
}

function storage(kind) {
  try {
    return kind === 'session' ? window.sessionStorage : window.localStorage
  } catch {
    return null
  }
}

function markChangedPages(active) {
  const local = storage('local')

  if (!local) {
    return
  }

  document.querySelectorAll('[data-vellum-nav-updated]').forEach((link) => {
    const path = new URL(link.href, window.location.href).pathname

    if (link.hasAttribute('data-vellum-nav-active') || path === window.location.pathname) {
      return
    }

    if (changedSince(link.dataset.vellumNavUpdated, Number(local.getItem(READ_PREFIX + path) || 0))) {
      link.setAttribute('data-vellum-nav-changed', '')
      const note = document.createElement('span')
      note.className = 'sr-only'
      note.textContent = ' (changed since you last read it)'
      link.append(note)
    }
  })

  const updated = document.querySelector('[data-vellum-page-meta] time[datetime]')
  const readAt = Number(local.getItem(READ_PREFIX + window.location.pathname) || 0)

  if (updated && changedSince(updated.dateTime, readAt)) {
    document.querySelector('[data-vellum-page-changed]')?.removeAttribute('hidden')
  }

  local.setItem(READ_PREFIX + window.location.pathname, String(Date.now()))
}

export function initSidebar() {
  const active = document.querySelector('[data-vellum-sidebar-wrap] [data-vellum-nav-active]')
  markChangedPages(active)

  const wrap = document.querySelector('[data-vellum-sidebar-wrap]')
  const nav = wrap?.querySelector('[data-vellum-nav]')
  const scroller = wrap?.querySelector('[data-vellum-scroll-area]')
  const session = storage('session')

  if (!nav || nav.offsetParent === null) {
    return
  }

  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const rectOf = (element) => {
    const outer = nav.getBoundingClientRect()
    const inner = element.getBoundingClientRect()

    return { x: inner.left - outer.left, y: inner.top - outer.top, w: inner.width, h: inner.height }
  }
  const place = (element, rect) => {
    element.style.transform = `translate(${rect.x}px, ${rect.y}px)`
    element.style.width = `${rect.w}px`
    element.style.height = `${rect.h}px`
  }

  // Stay where the reader left the sidebar, and keep the current page in view.
  if (scroller && session) {
    const kept = Number(session.getItem(SCROLL_KEY) ?? -1)

    if (kept >= 0) {
      scroller.scrollTop = kept
    }

    if (active) {
      const box = scroller.getBoundingClientRect()
      const entry = active.getBoundingClientRect()

      if (entry.top < box.top || entry.bottom > box.bottom) {
        active.scrollIntoView({ block: 'center' })
      }
    }

    window.addEventListener('pagehide', () => session.setItem(SCROLL_KEY, String(scroller.scrollTop)))
  }

  if (!active) {
    return
  }

  const marker = document.createElement('span')
  marker.className = 'vellum-nav-marker'
  marker.setAttribute('aria-hidden', 'true')
  marker.toggleAttribute('data-nested', active.closest('[data-vellum-collapsible-content]') !== null)
  nav.prepend(marker)
  document.documentElement.setAttribute('data-vellum-nav-marker', '')

  // The folders the entry sits in clip it while they open and close.
  const folders = []
  for (let folder = active.closest('[data-vellum-collapsible-content]'); folder; folder = folder.parentElement?.closest('[data-vellum-collapsible-content]')) {
    folders.push(folder)
  }

  const follow = () => {
    const shown = active.offsetParent === null ? null : visiblePart(rectOf(active), folders.map(rectOf))
    marker.style.visibility = shown ? '' : 'hidden'

    if (shown) {
      place(marker, shown)
    }
  }

  // Arriving from another page: start at its entry, slide to this one.
  let sliding = false
  let from = null

  try {
    from = JSON.parse(session?.getItem(MARKER_KEY) ?? 'null')
  } catch {
    from = null
  }

  const to = rectOf(active)

  if (from && Date.now() - from.t < 10000 && !reduced && (from.x !== to.x || from.y !== to.y)) {
    sliding = true
    place(marker, from)
    marker.getBoundingClientRect()
    requestAnimationFrame(() => {
      marker.setAttribute('data-sliding', '')
      place(marker, to)
      const done = () => {
        sliding = false
        marker.removeAttribute('data-sliding')
        follow()
      }
      marker.addEventListener('transitionend', done, { once: true })
      window.setTimeout(done, 700)
    })
  } else {
    follow()
  }

  window.addEventListener('pagehide', () => session?.setItem(MARKER_KEY, JSON.stringify({ ...rectOf(active), t: Date.now() })))
  new ResizeObserver(() => {
    if (!sliding) {
      follow()
    }
  }).observe(nav)

  // Reading progress: the marker fills as the reader moves down the page.
  let shown = ''
  const read = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight
    const progress = (max > 0 ? Math.min(1, window.scrollY / max) : 1).toFixed(3)

    if (progress !== shown) {
      shown = progress
      marker.style.setProperty('--vellum-read', progress)
    }
  }
  window.addEventListener('scroll', read, { passive: true })
  read()

  // Hover: a lighter highlight glides to the entry under the pointer.
  const hover = document.createElement('span')
  hover.className = 'vellum-nav-hover'
  hover.setAttribute('aria-hidden', 'true')
  nav.prepend(hover)

  nav.addEventListener('pointerover', (event) => {
    const entry = event.target.closest('[data-vellum-nav-link], [data-vellum-collapsible-trigger] > div')

    if (!entry || entry === active) {
      hover.removeAttribute('data-shown')

      return
    }

    hover.toggleAttribute('data-gliding', hover.hasAttribute('data-shown') && !reduced)
    place(hover, rectOf(entry))
    hover.setAttribute('data-shown', '')
  })
  nav.addEventListener('pointerleave', () => {
    hover.removeAttribute('data-shown')
    hover.removeAttribute('data-gliding')
  })
}
