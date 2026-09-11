const FOCUSABLE =
  'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])'

/**
 * Minimal Alpine focus-trap plugin exposing `x-trap` for dialogs.
 * Replaces @alpinejs/focus to stay within the JS gzip budget.
 */
export default function focusTrap(Alpine) {
  Alpine.directive('trap', (el, { expression, modifiers }, { evaluateLater, effect, cleanup }) => {
    const getActive = evaluateLater(expression)
    let previouslyFocused = null
    let active = false
    const lockScroll = modifiers.includes('noscroll')
    let previousOverflow = ''

    const focusables = () =>
      [...el.querySelectorAll(FOCUSABLE)].filter(
        (node) => !node.hasAttribute('disabled') && node.getClientRects().length > 0,
      )

    const onKeydown = (event) => {
      if (event.key !== 'Tab' || !active) {
        return
      }

      const items = focusables()
      if (items.length === 0) {
        event.preventDefault()
        return
      }

      const first = items[0]
      const last = items[items.length - 1]

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }

    const activate = () => {
      if (active) {
        return
      }

      active = true
      previouslyFocused = document.activeElement
      if (lockScroll) {
        previousOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
      }
      queueMicrotask(() => {
        const items = focusables()
        ;(items[0] ?? el).focus?.()
      })
      document.addEventListener('keydown', onKeydown)
    }

    const deactivate = () => {
      if (!active) {
        return
      }

      active = false
      document.removeEventListener('keydown', onKeydown)
      if (lockScroll) {
        document.body.style.overflow = previousOverflow
      }
      previouslyFocused?.focus?.()
      previouslyFocused = null
    }

    effect(() => {
      getActive((value) => {
        if (value) {
          activate()
        } else {
          deactivate()
        }
      })
    })

    cleanup(() => {
      deactivate()
    })
  })
}
