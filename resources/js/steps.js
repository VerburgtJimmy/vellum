/**
 * Steps follow the reader. The line between the numbers fills down to the
 * reading line, a step's number fills once the reader reaches it, and the
 * table of contents keeps those numbers filled as well. The number of the
 * step being read stays in view (CSS), and clicking it goes back to the
 * step's start.
 *
 * Without this script the steps look as the markup has them, and the
 * numbers still link to their headings.
 */

/** How far down the window the reader is taken to be reading, as a fraction. */
const READING_LINE = 1 / 3

/**
 * Where the reader is in one steps block.
 *
 * The line under a step runs from the bottom of its number to the top of the
 * next step, so it fills by how far the reading line has passed that span,
 * from 0 to 1.
 *
 * @param {number[]} tops  each step's top, in viewport pixels
 * @param {number} end  the bottom of the last step
 * @param {number} circle  the height of a step's number
 * @param {number} line  the reading line
 * @returns {{fill: number, reached: boolean, current: boolean}[]}
 */
export function stepStates(tops, end, circle, line) {
  return tops.map((top, i) => {
    const next = tops[i + 1] ?? end
    const span = next - top - circle

    return {
      fill: i === tops.length - 1 || span <= 0 ? 0 : Math.max(0, Math.min(1, (line - top - circle) / span)),
      reached: line >= top + circle / 2,
      current: line >= top && line < next,
    }
  })
}

export function initSteps() {
  const blocks = [...document.querySelectorAll('[data-vellum-steps]')]
    .map((block) => [...block.children]
      .filter((child) => child.matches('[data-vellum-step]'))
      .map((step) => {
        const number = step.querySelector(':scope > .vellum-step-indicator')
        const id = number?.hash?.slice(1)

        return {
          step,
          number,
          entries: id ? document.querySelectorAll(`[data-vellum-toc-link][data-vellum-toc-id="${CSS.escape(id)}"]`) : [],
        }
      }))
    .filter((steps) => steps.length > 0)

  if (blocks.length === 0) {
    return
  }

  const update = () => {
    const line = window.innerHeight * READING_LINE

    for (const steps of blocks) {
      const tops = steps.map(({ step }) => step.getBoundingClientRect().top)
      const end = steps[steps.length - 1].step.getBoundingClientRect().bottom

      stepStates(tops, end, steps[0].number?.offsetHeight ?? 0, line).forEach((state, i) => {
        const { step, entries } = steps[i]
        const fill = state.fill.toFixed(3)

        // Written only when it changed, so an idle block costs nothing per frame.
        if (step.dataset.vellumStepFill !== fill) {
          step.dataset.vellumStepFill = fill
          step.style.setProperty('--vellum-step-fill', fill)
        }

        step.toggleAttribute('data-vellum-step-reached', state.reached)
        step.toggleAttribute('data-vellum-step-current', state.current)
        entries.forEach((entry) => entry.toggleAttribute('data-vellum-toc-step-reached', state.reached))
      })
    }
  }

  let queued = false
  const schedule = () => {
    if (queued) {
      return
    }

    queued = true
    requestAnimationFrame(() => {
      queued = false
      update()
    })
  }

  window.addEventListener('scroll', schedule, { passive: true })
  window.addEventListener('resize', schedule)
  update()

  document.addEventListener('click', (event) => {
    const number = event.target instanceof Element ? event.target.closest('a.vellum-step-indicator') : null
    const heading = number ? document.getElementById(number.hash.slice(1)) : null

    if (!heading || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return
    }

    event.preventDefault()
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    heading.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' })
    history.replaceState(null, '', number.hash)
  })
}
