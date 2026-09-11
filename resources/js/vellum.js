import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'
import anchor from '@alpinejs/anchor'
import MiniSearch from 'minisearch'
import '../css/vellum.css'
import { applyTheme, getStoredTheme, initTheme, resolveTheme } from './theme.js'
import focusTrap from './focus-trap.js'

window.MiniSearch = MiniSearch
window.VellumTheme = { applyTheme, getStoredTheme, initTheme, resolveTheme }

/**
 * Tabs root state: roving tabindex and arrow-key navigation.
 */
function vellumTabs(initial = '') {
  return {
    active: initial,
    init() {
      if (!this.active) {
        const first = this.$el.querySelector('[role="tab"]')
        this.active = first?.getAttribute('data-value') ?? ''
      }
    },
    select(value) {
      this.active = value
    },
    onListKeydown(event) {
      const tabs = [...this.$el.querySelectorAll('[role="tab"]')]
      if (tabs.length === 0) {
        return
      }

      const index = tabs.findIndex((tab) => tab.getAttribute('data-value') === this.active)
      let next = index

      if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
        event.preventDefault()
        next = (index + 1) % tabs.length
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
        event.preventDefault()
        next = (index - 1 + tabs.length) % tabs.length
      } else if (event.key === 'Home') {
        event.preventDefault()
        next = 0
      } else if (event.key === 'End') {
        event.preventDefault()
        next = tabs.length - 1
      } else {
        return
      }

      const value = tabs[next].getAttribute('data-value')
      this.select(value)
      tabs[next].focus()
    },
  }
}

if (!window.Alpine) {
  Alpine.plugin(collapse)
  Alpine.plugin(focusTrap)
  Alpine.plugin(anchor)
  Alpine.data('vellumTabs', vellumTabs)
  window.Alpine = Alpine
  Alpine.start()
}
