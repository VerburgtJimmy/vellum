/**
 * Lightweight heading scroll-spy for the docs TOC.
 *
 * @param {Array<string|{id: string, text?: string}>} items
 * @param {string} pageTitle
 */
export function vellumScrollSpy(items = [], pageTitle = 'On this page') {
  const normalized = items.map((item) => (
    typeof item === 'string' ? { id: item, text: item } : item
  ))

  return {
    pageTitle,
    activeId: normalized[0]?.id ?? '',
    activeTitle: normalized[0]?.text || pageTitle,
    ids: normalized.map((item) => item.id),
    titles: Object.fromEntries(normalized.map((item) => [item.id, item.text || item.id])),
    progress: 0,
    open: false,
    observer: null,
    resizeObserver: null,
    _onScroll: null,
    init() {
      this.updateActiveTitle()
      this.updateProgress()
      this.updateIndicator()

      if (typeof ResizeObserver !== 'undefined') {
        const nav = this.tocNav()
        if (nav) {
          this.resizeObserver = new ResizeObserver(() => this.updateRail())
          this.resizeObserver.observe(nav)
        }
      }

      this._onScroll = () => this.updateProgress()
      window.addEventListener('scroll', this._onScroll, { passive: true })

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
            this.updateActiveTitle()
            this.updateIndicator()
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
    tocNav() {
      return this.$el.querySelector('[data-vellum-toc-nav]')
    },
    toggleOpen() {
      this.open = !this.open
      this.$nextTick(() => this.updateRail())
    },
    updateActiveTitle() {
      this.activeTitle = this.titles[this.activeId] || 'On this page'
    },
    updateProgress() {
      const article = document.querySelector('[data-vellum-article]')
      if (!article) {
        this.progress = 0
        return
      }

      const start = 80
      const total = Math.max(1, article.offsetHeight - window.innerHeight + start)
      const top = article.getBoundingClientRect().top
      const scrolled = Math.min(total, Math.max(0, start - top))
      this.progress = scrolled / total
    },
    lineOffset(level) {
      if (level <= 0) {
        return 8
      }

      if (level === 1) {
        return 16
      }

      return 24
    },
    updateRail() {
      const nav = this.tocNav()
      if (!nav || nav.clientHeight === 0) {
        return
      }

      const track = nav.querySelector('[data-vellum-toc-track]')
      const thumb = nav.querySelector('[data-vellum-toc-thumb]')
      const trackPath = nav.querySelector('[data-vellum-toc-track-path]')
      const thumbPath = nav.querySelector('[data-vellum-toc-thumb-path]')
      const links = [...nav.querySelectorAll('[data-vellum-toc-link]')]

      if (!track || !thumb || !trackPath || !thumbPath || links.length === 0) {
        return
      }

      let width = 0
      let height = 0
      let d = ''
      const positions = []

      for (let i = 0; i < links.length; i++) {
        const link = links[i]
        const level = Number.parseInt(link.dataset.vellumTocLevel ?? '0', 10) || 0
        const styles = getComputedStyle(link)
        const x = this.lineOffset(level) + 0.5
        const top = link.offsetTop + Number.parseFloat(styles.paddingTop)
        const bottom = link.offsetTop + link.clientHeight - Number.parseFloat(styles.paddingBottom)

        width = Math.max(x + 8, width)
        height = Math.max(height, bottom)

        if (i === 0) {
          d += `M${x} ${top} L${x} ${bottom}`
        } else {
          const prev = positions[i - 1]
          d += ` C ${prev.x} ${prev.bottom + 8} ${x} ${top - 8} ${x} ${top} L${x} ${bottom}`
        }

        positions.push({ top, bottom, x, id: link.dataset.vellumTocId })
      }

      const viewBox = `0 0 ${width} ${height}`
      for (const svg of [track, thumb]) {
        svg.setAttribute('viewBox', viewBox)
        svg.style.width = `${width}px`
        svg.style.height = `${height}px`
      }

      trackPath.setAttribute('d', d)
      thumbPath.setAttribute('d', d)

      const dot = nav.querySelector('[data-vellum-toc-dot]')
      if (dot) {
        dot.style.offsetPath = `path("${d}")`
      }

      const active = positions.find((item) => item.id === this.activeId) ?? positions[0]
      nav.style.setProperty('--track-top', `${active.top}px`)
      nav.style.setProperty('--track-bottom', `${active.bottom}px`)
      nav.style.setProperty('--offset-distance', `${(active.top + active.bottom) / 2}px`)
      nav.style.setProperty('--toc-dot-opacity', '1')
    },
    updateIndicator() {
      this.$nextTick(() => this.updateRail())
    },
    destroy() {
      this.observer?.disconnect()
      this.resizeObserver?.disconnect()
      if (this._onScroll) {
        window.removeEventListener('scroll', this._onScroll)
      }
    },
    scrollTo(id) {
      const el = document.getElementById(id)
      if (!el) {
        return
      }

      this.activeId = id
      this.updateActiveTitle()
      this.open = false
      this.updateIndicator()
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      history.replaceState(null, '', `#${id}`)
    },
  }
}
