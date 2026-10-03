/**
 * A table that fits its column pins its header to the top of the page while
 * the reader scrolls through it. That needs the table's wrapper to stop
 * clipping, which a table too wide for the page cannot do: it has to keep
 * scrolling sideways. So each table is measured, and marked when it fits.
 *
 * --vellum-sticky-top is how far down the pinned header sits: below the
 * header bar and the mobile table of contents, whichever are showing.
 */
function stickyTop() {
  let top = 0

  document.querySelectorAll('[data-vellum-sticky-bar]').forEach((bar) => {
    if (bar.offsetParent !== null) {
      top = Math.max(top, (parseFloat(getComputedStyle(bar).top) || 0) + bar.offsetHeight)
    }
  })

  return top
}

export function initTables() {
  const wraps = [...document.querySelectorAll('.vellum-table')]

  if (wraps.length === 0) {
    return
  }

  const measure = () => {
    document.documentElement.style.setProperty('--vellum-sticky-top', `${stickyTop()}px`)

    for (const wrap of wraps) {
      const table = wrap.querySelector('table')
      wrap.toggleAttribute('data-vellum-table-fits', table !== null && table.offsetWidth <= wrap.offsetWidth)
    }
  }

  const observer = new ResizeObserver(measure)
  wraps.forEach((wrap) => observer.observe(wrap))
  measure()
}
