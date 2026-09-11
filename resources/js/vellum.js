import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'
import '../css/vellum.css'
import { prefetch, prefetchHover } from './prefetch.js'
import { vellumScrollSpy } from './scrollspy.js'
import { applyTheme, getStoredTheme, initTheme, resolveTheme } from './theme.js'

window.VellumTheme = { applyTheme, getStoredTheme, initTheme, resolveTheme }
window.VellumPrefetch = { prefetch }

let searchModulePromise = null
let anchorModulePromise = null
let focusModulePromise = null

/**
 * Lazily load MiniSearch (and Phase 5 search helpers) on first open.
 */
window.VellumSearch = {
  load() {
    if (searchModulePromise) {
      return searchModulePromise
    }

    const url = document.documentElement.dataset.vellumSearch
    if (!url) {
      return Promise.reject(new Error('Missing data-vellum-search URL on <html>'))
    }

    searchModulePromise = import(/* @vite-ignore */ url)
    return searchModulePromise
  },
}

/**
 * Lazily load @alpinejs/anchor (+ floating-ui) on first popover/tooltip use.
 */
window.VellumAnchor = {
  load() {
    if (anchorModulePromise) {
      return anchorModulePromise
    }

    const url = document.documentElement.dataset.vellumAnchor
    if (!url) {
      return Promise.reject(new Error('Missing data-vellum-anchor URL on <html>'))
    }

    anchorModulePromise = import(/* @vite-ignore */ url).then((mod) => {
      mod.registerAnchor(window.Alpine)
      return mod
    })

    return anchorModulePromise
  },
}

/**
 * Lazily load @alpinejs/focus on first dialog open.
 */
window.VellumFocus = {
  load() {
    if (focusModulePromise) {
      return focusModulePromise
    }

    const url = document.documentElement.dataset.vellumFocus
    if (!url) {
      return Promise.reject(new Error('Missing data-vellum-focus URL on <html>'))
    }

    focusModulePromise = import(/* @vite-ignore */ url).then((mod) => {
      mod.registerFocus(window.Alpine)
      return mod
    })

    return focusModulePromise
  },
}

/**
 * Thin popover stub; real positioning runs after the anchor chunk loads.
 */
function vellumPopover(initialOpen = false) {
  return {
    open: initialOpen,
    async ensureAnchor() {
      await window.VellumAnchor.load()
    },
    async toggle() {
      await this.ensureAnchor()
      this.open = !this.open
    },
    close() {
      this.open = false
    },
  }
}

/**
 * Thin tooltip stub; loads the anchor chunk on first pointer/focus.
 */
function vellumTooltip(delay = 300) {
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

/**
 * Dialog stub; loads focus trap before mounting the teleported panel.
 */
function vellumDialog(initialOpen = false) {
  return {
    open: initialOpen,
    async ensureFocus() {
      await window.VellumFocus.load()
    },
    async show() {
      await this.ensureFocus()
      this.open = true
    },
    close() {
      this.open = false
    },
  }
}

/**
 * Search hotkey root: Cmd/Ctrl+K in capture phase so the browser does not steal it.
 */
function vellumSearchHotkey(hotkey = 'k') {
  return {
    hotkey: String(hotkey || 'k'),
    _onKey: null,
    init() {
      this._onKey = (event) => this.onHotkey(event)
      window.addEventListener('keydown', this._onKey, true)
    },
    destroy() {
      if (this._onKey) {
        window.removeEventListener('keydown', this._onKey, true)
      }
    },
    onHotkey(event) {
      if (!(event.metaKey || event.ctrlKey)) {
        return
      }

      if (event.key.toLowerCase() !== this.hotkey.toLowerCase()) {
        return
      }

      event.preventDefault()
      event.stopPropagation()

      const dialog = this.$el.querySelector('[data-vellum-dialog]')
      if (!dialog || !window.Alpine) {
        return
      }

      const data = window.Alpine.$data(dialog)
      if (!data) {
        return
      }

      if (data.open) {
        data.close()
      } else {
        data.show()
      }
    },
  }
}

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
  Alpine.data('vellumTabs', vellumTabs)
  Alpine.data('vellumPopover', vellumPopover)
  Alpine.data('vellumTooltip', vellumTooltip)
  Alpine.data('vellumDialog', vellumDialog)
  Alpine.data('vellumSearchHotkey', vellumSearchHotkey)
  Alpine.data('vellumScrollSpy', vellumScrollSpy)
  Alpine.data('vellumPrefetchHover', prefetchHover)
  window.Alpine = Alpine
  Alpine.start()
}
