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
    // Every heading whose section is on screen, not just the one last
    // scrolled past. A reader looking at three short sections at once should
    // see three entries lit, and the rail should cover all of them.
    activeIds: normalized[0] ? [normalized[0].id] : [],
    activeTitle: normalized[0]?.text || pageTitle,
    ids: normalized.map((item) => item.id),
    titles: Object.fromEntries(normalized.map((item) => [item.id, item.text || item.id])),
    progress: 0,
    open: false,
    resizeObserver: null,
    _onScroll: null,
    _frame: 0,
    /** Rail anchor points keyed by heading id, refreshed when layout moves. */
    _anchors: {},
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

      this._onScroll = () => this.schedule()
      window.addEventListener('scroll', this._onScroll, { passive: true })
      window.addEventListener('resize', this._onScroll, { passive: true })
      this.schedule()
    },
    /**
     * Measuring on every scroll event is wasteful; once a frame is both
     * enough and the most often anything on screen can actually change.
     */
    schedule() {
      if (this._frame) {
        return
      }

      this._frame = requestAnimationFrame(() => {
        this._frame = 0
        this.updateProgress()
        this.syncActive()
        // Every frame, not only when the lit set changes: the dot marks where
        // the reader is, which moves continuously while the set does not.
        this.updateDot()
      })
    },
    /**
     * A heading counts as active while any part of the section it introduces
     * is in view. Watching the heading element alone would leave a long
     * section with nothing lit the moment its title scrolled off the top.
     */
    visibleIds() {
      const positions = []

      for (const id of this.ids) {
        const el = document.getElementById(id)
        if (el) {
          positions.push({ id, top: el.getBoundingClientRect().top })
        }
      }

      if (positions.length === 0) {
        return []
      }

      const article = document.querySelector('[data-vellum-article]')
      const end = article ? article.getBoundingClientRect().bottom : Number.MAX_SAFE_INTEGER
      const top = this.viewportTop()
      const bottom = window.innerHeight
      const visible = []

      for (let i = 0; i < positions.length; i++) {
        const start = positions[i].top
        const stop = i + 1 < positions.length ? positions[i + 1].top : end

        if (start < bottom && stop > top) {
          visible.push(positions[i].id)
        }
      }

      if (visible.length > 0) {
        return visible
      }

      // Above the first heading or past the end of the article: keep the
      // nearest heading behind us lit rather than showing nothing at all.
      const behind = positions.filter((item) => item.top <= top).pop()

      return [behind?.id ?? positions[0].id]
    },
    /**
     * The sticky header covers the top of the viewport, so a heading tucked
     * underneath it is not really on screen.
     */
    viewportTop() {
      const header = document.querySelector('[data-vellum-header]')

      if (!header) {
        return 0
      }

      const rect = header.getBoundingClientRect()

      return rect.top <= 0 ? Math.max(0, rect.bottom) : 0
    },
    /**
     * Slide the dot along the rail to match reading position.
     *
     * The rail says which sections are on screen. The dot says where in them
     * you are, which is the thing the rail cannot show: it interpolates
     * between two headings' rail anchors by how far you have read between
     * them in the article.
     */
    updateDot() {
      const nav = this.tocNav()
      const dot = nav?.querySelector('[data-vellum-toc-dot]')

      if (!nav || !dot) {
        return
      }

      const anchors = this.ids.map((id) => this._anchors[id]).filter((value) => value !== undefined)

      if (anchors.length === 0) {
        return
      }

      const tops = []

      for (const id of this.ids) {
        const el = document.getElementById(id)

        if (el && this._anchors[id] !== undefined) {
          tops.push({ top: el.getBoundingClientRect().top, anchor: this._anchors[id] })
        }
      }

      if (tops.length === 0) {
        return
      }

      const here = this.viewportTop()
      let distance = tops[0].anchor

      for (let i = 0; i < tops.length; i++) {
        if (tops[i].top > here) {
          break
        }

        const next = tops[i + 1]

        if (!next) {
          const article = document.querySelector('[data-vellum-article]')
          const end = article ? article.getBoundingClientRect().bottom : tops[i].top + window.innerHeight
          const span = Math.max(1, end - tops[i].top)
          const ratio = Math.min(1, Math.max(0, (here - tops[i].top) / span))

          distance = tops[i].anchor + (this._railEnd - tops[i].anchor) * ratio
          break
        }

        const span = Math.max(1, next.top - tops[i].top)
        const ratio = Math.min(1, Math.max(0, (here - tops[i].top) / span))

        distance = tops[i].anchor + (next.anchor - tops[i].anchor) * ratio
      }

      nav.style.setProperty('--offset-distance', `${distance}px`)
      nav.style.setProperty('--toc-dot-opacity', '1')
    },
    syncActive() {
      const visible = this.visibleIds()
      const changed = visible.length !== this.activeIds.length
        || visible.some((id, index) => id !== this.activeIds[index])

      if (!changed) {
        return
      }

      this.activeIds = visible
      this.activeId = visible[0] ?? ''
      this.updateActiveTitle()
      // After the bold lands: a heavier weight can rewrap a long entry and
      // shift every link below it, and the rail is drawn from those offsets.
      this.updateIndicator()
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

      // The rail covers the whole run of active entries, first to last.
      const lit = positions.filter((item) => this.activeIds.includes(item.id))
      const first = lit[0] ?? positions.find((item) => item.id === this.activeId) ?? positions[0]
      const last = lit[lit.length - 1] ?? first

      nav.style.setProperty('--track-top', `${first.top}px`)
      nav.style.setProperty('--track-bottom', `${last.bottom}px`)

      this._anchors = Object.fromEntries(
        positions.filter((item) => item.id).map((item) => [item.id, (item.top + item.bottom) / 2]),
      )
      this._railEnd = positions[positions.length - 1].bottom

      this.updateDot()
    },
    updateIndicator() {
      this.$nextTick(() => this.updateRail())
    },
    destroy() {
      this.resizeObserver?.disconnect()

      if (this._onScroll) {
        window.removeEventListener('scroll', this._onScroll)
        window.removeEventListener('resize', this._onScroll)
      }
    },
    scrollTo(id) {
      const el = document.getElementById(id)
      if (!el) {
        return
      }

      this.activeId = id
      this.activeIds = [id]
      this.updateActiveTitle()
      this.open = false
      this.updateIndicator()
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
      history.replaceState(null, '', `#${id}`)
    },
  }
}
