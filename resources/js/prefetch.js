const prefetched = new Set()

/**
 * Inject a one-shot <link rel="prefetch"> for a docs URL.
 */
export function prefetch(url) {
  if (!url || typeof url !== 'string' || prefetched.has(url)) {
    return
  }

  prefetched.add(url)

  const link = document.createElement('link')
  link.rel = 'prefetch'
  link.href = url
  document.head.appendChild(link)
}

/**
 * Alpine helper: prefetch the href of the current element on pointerenter.
 */
export function prefetchHover() {
  return {
    onEnter() {
      const href = this.$el.getAttribute('href')
      if (href) {
        prefetch(href)
      }
    },
  }
}
