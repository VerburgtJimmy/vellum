/**
 * An image shown smaller than it is can be opened at full size: it grows from
 * where it sits to the middle of the window, and a click, Escape or the
 * button it becomes puts it back. An image already at full size is left alone.
 */
const SELECTOR = '.vellum-prose img[data-vellum-image-block]'

function zoomable(image) {
  return image.naturalWidth > image.offsetWidth * 1.1
}

function mark(image) {
  if (!zoomable(image) || image.closest('a')) {
    return
  }

  image.setAttribute('data-vellum-zoomable', '')
  image.setAttribute('tabindex', '0')
  image.setAttribute('role', 'button')
  image.setAttribute('aria-label', `View larger: ${image.alt || 'image'}`)
}

function open(image) {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const from = image.getBoundingClientRect()
  const layer = document.createElement('button')
  layer.type = 'button'
  layer.className = 'vellum-zoom'
  layer.setAttribute('aria-label', 'Close the larger image')

  const large = new Image()
  large.src = image.currentSrc || image.src
  large.alt = image.alt
  layer.append(large)
  document.body.append(layer)

  if (!reduced) {
    // Start where the image sits, then let it settle in the middle.
    const to = large.getBoundingClientRect()
    large.style.transition = 'none'
    large.style.transform = `translate(${from.left + from.width / 2 - to.left - to.width / 2}px, ${from.top + from.height / 2 - to.top - to.height / 2}px) scale(${from.width / (to.width || 1)})`
    void large.offsetWidth
    large.style.transition = ''
    large.style.transform = ''
  }

  layer.setAttribute('data-shown', '')
  layer.focus()

  const close = () => {
    document.removeEventListener('keydown', onKey)
    layer.removeAttribute('data-shown')
    window.setTimeout(() => layer.remove(), reduced ? 0 : 200)
    image.focus({ preventScroll: true })
  }
  const onKey = (event) => {
    if (event.key === 'Escape') {
      close()
    }
  }

  layer.addEventListener('click', close)
  document.addEventListener('keydown', onKey)
}

export function initImageZoom() {
  document.querySelectorAll(SELECTOR).forEach((image) => {
    if (image.complete) {
      mark(image)
    } else {
      image.addEventListener('load', () => mark(image), { once: true })
    }
  })

  const activate = (event) => {
    const image = event.target instanceof Element ? event.target.closest('img[data-vellum-zoomable]') : null

    if (!image || (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ')) {
      return
    }

    event.preventDefault()
    open(image)
  }

  document.addEventListener('click', activate)
  document.addEventListener('keydown', activate)
}
