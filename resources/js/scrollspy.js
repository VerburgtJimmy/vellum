/**
 * Lightweight heading scroll-spy for the docs TOC.
 *
 * @param {string[]} ids
 */
export function vellumScrollSpy(ids = []) {
  return {
    activeId: ids[0] ?? '',
    ids,
    observer: null,
    init() {
      if (this.ids.length === 0 || typeof IntersectionObserver === 'undefined') {
        return
      }

      const ratios = new Map()

      this.observer = new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            ratios.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0)
          }

          let bestId = this.activeId
          let bestRatio = -1

          for (const id of this.ids) {
            const ratio = ratios.get(id) ?? 0
            if (ratio > bestRatio) {
              bestRatio = ratio
              bestId = id
            }
          }

          if (bestRatio > 0) {
            this.activeId = bestId
          }
        },
        {
          rootMargin: '0px 0px -65% 0px',
          threshold: [0, 0.1, 0.25, 0.5, 1],
        },
      )

      for (const id of this.ids) {
        const el = document.getElementById(id)
        if (el) {
          this.observer.observe(el)
        }
      }
    },
    destroy() {
      this.observer?.disconnect()
    },
    scrollTo(id) {
      const el = document.getElementById(id)
      if (!el) {
        return
      }

      this.activeId = id
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      history.replaceState(null, '', `#${id}`)
    },
  }
}
