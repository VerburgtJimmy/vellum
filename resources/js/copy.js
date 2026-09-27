/**
 * The copy buttons in rendered Markdown: a code block copies its code, a
 * heading copies a link to itself. The Markdown marks them with data
 * attributes only, so one listener serves every button on the page.
 */

const COPIED_FOR = 1500

function textFor(button) {
  if (button.hasAttribute('data-vellum-copy-code')) {
    return button.closest('[data-vellum-code]')?.querySelector('code')?.textContent ?? null
  }

  const id = button.closest('h1, h2, h3, h4, h5, h6')?.id

  return id ? new URL(`#${id}`, window.location.href).href : null
}

export function initCopyButtons() {
  const timers = new WeakMap()
  const labels = new WeakMap()

  document.addEventListener('click', (event) => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-vellum-copy-code], [data-vellum-heading-copy]')
      : null
    const text = button ? textFor(button) : null

    if (text === null) {
      return
    }

    navigator.clipboard.writeText(text)

    if (!labels.has(button)) {
      labels.set(button, button.getAttribute('aria-label') ?? '')
    }

    button.setAttribute('data-copied', '')
    button.setAttribute('aria-label', 'Copied')
    window.clearTimeout(timers.get(button))
    timers.set(button, window.setTimeout(() => {
      button.removeAttribute('data-copied')
      button.setAttribute('aria-label', labels.get(button))
    }, COPIED_FOR))
  })
}
