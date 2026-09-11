import anchor from '@alpinejs/anchor'

let registered = false

/**
 * Register @alpinejs/anchor (and its @floating-ui/dom dependency) once.
 */
export function registerAnchor(Alpine) {
  if (registered) {
    return
  }

  Alpine.plugin(anchor)
  registered = true
}

/**
 * Popover behaviour: load-safe open/close with click-outside on the root.
 */
export function vellumPopover(initialOpen = false) {
  return {
    open: initialOpen,
    async ensureAnchor() {
      await window.VellumAnchor.load()
    },
    async toggle() {
      await this.ensureAnchor()
      this.open = !this.open
    },
    async openPanel() {
      await this.ensureAnchor()
      this.open = true
    },
    close() {
      this.open = false
    },
  }
}

/**
 * Tooltip behaviour: 300ms delay, load anchor before first show.
 */
export function vellumTooltip(delay = 300) {
  return {
    open: false,
    timer: null,
    async ensureAnchor() {
      await window.VellumAnchor.load()
    },
    async show() {
      await this.ensureAnchor()
      clearTimeout(this.timer)
      this.timer = setTimeout(() => {
        this.open = true
      }, delay)
    },
    hide() {
      clearTimeout(this.timer)
      this.timer = null
      this.open = false
    },
  }
}
