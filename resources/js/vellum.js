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
    entered: false,
    closeTimer: null,
    motionToken: 0,
    async ensureFocus() {
      await window.VellumFocus.load()
    },
    isSheet() {
      return this.$el?.getAttribute('data-vellum-dialog-variant') === 'sheet'
    },
    motionMs() {
      if (!this.isSheet()) {
        return 0
      }

      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return 0
      }

      return 280
    },
    async show() {
      await this.ensureFocus()
      clearTimeout(this.closeTimer)
      this.closeTimer = null
      this.open = true
      const wait = this.motionMs()
      this.entered = wait === 0
      if (this.entered) {
        return
      }

      const token = ++this.motionToken
      this.$nextTick(() => {
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            if (this.motionToken !== token || !this.open) {
              return
            }
            this.entered = true
          })
        })
      })
    },
    close() {
      this.motionToken += 1
      this.entered = false
      const wait = this.motionMs()
      if (wait === 0) {
        this.open = false
        return
      }

      clearTimeout(this.closeTimer)
      this.closeTimer = setTimeout(() => {
        this.open = false
        this.closeTimer = null
      }, wait)
    },
    destroy() {
      clearTimeout(this.closeTimer)
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
    openSearch() {
      const dialog = this.$el.querySelector('[data-vellum-dialog]')
      if (!dialog || !window.Alpine) {
        return
      }

      const data = window.Alpine.$data(dialog)
      if (!data) {
        return
      }

      if (typeof data.show === 'function') {
        data.show()
      }
    },
    closeSearch() {
      const dialog = this.$el.querySelector('[data-vellum-dialog]')
      if (!dialog || !window.Alpine) {
        return
      }

      const data = window.Alpine.$data(dialog)
      data?.close?.()
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

function isApplePlatform() {
  const uaData = navigator.userAgentData
  if (uaData && typeof uaData.platform === 'string' && uaData.platform !== '') {
    return /mac|iphone|ipad|ipod/i.test(uaData.platform)
  }

  return /mac|iphone|ipad|ipod/i.test(navigator.platform || navigator.userAgent || '')
}

function vellumHotkeyChip() {
  return {
    mod: 'Ctrl',
    init() {
      this.mod = isApplePlatform() ? '⌘' : 'Ctrl'
    },
  }
}

function vellumChrome() {
  return {
    collapsed: false,
    peek: false,
    peekTimer: null,
    peekLockedUntil: 0,
    init() {
      this.collapsed = document.documentElement.getAttribute('data-vellum-sidebar') === 'collapsed'
    },
    destroy() {
      clearTimeout(this.peekTimer)
    },
    setCollapsed(value) {
      this.collapsed = value
      this.peek = false
      this.peekLockedUntil = value ? Date.now() + 400 : 0
      clearTimeout(this.peekTimer)
      this.peekTimer = null
      document.documentElement.removeAttribute('data-vellum-sidebar-peek')
      try {
        if (value) {
          document.documentElement.setAttribute('data-vellum-sidebar', 'collapsed')
          localStorage.setItem('vellum-sidebar', 'collapsed')
        } else {
          document.documentElement.removeAttribute('data-vellum-sidebar')
          localStorage.setItem('vellum-sidebar', 'open')
        }
      } catch (e) {
        // ignore storage failures
      }
    },
    toggleSidebar() {
      this.setCollapsed(!this.collapsed)
    },
    showPeek() {
      if (!this.collapsed || Date.now() < this.peekLockedUntil) {
        return
      }
      clearTimeout(this.peekTimer)
      this.peekTimer = null
      this.peek = true
      document.documentElement.setAttribute('data-vellum-sidebar-peek', '')
    },
    scheduleHidePeek() {
      if (!this.collapsed || !this.peek || this.peekTimer) {
        return
      }
      this.peekTimer = setTimeout(() => {
        this.peek = false
        this.peekTimer = null
        document.documentElement.removeAttribute('data-vellum-sidebar-peek')
      }, 280)
    },
  }
}

function vellumHeadingCopy() {
  return {
    copied: false,
    copy() {
      const heading = this.$el.closest('h1, h2, h3, h4, h5, h6')
      const id = heading?.id
      if (!id) {
        return
      }

      const url = new URL(`#${id}`, window.location.href)
      navigator.clipboard.writeText(url.href)
      this.copied = true
      setTimeout(() => {
        this.copied = false
      }, 1500)
    },
  }
}

function vellumCopyMarkdown(source = '') {
  return {
    source,
    copiedMarkdown: false,
    copyMarkdown() {
      navigator.clipboard.writeText(this.source)
      this.copiedMarkdown = true
      setTimeout(() => {
        this.copiedMarkdown = false
      }, 1500)
    },
  }
}

function vellumPageActions(source = '') {
  return vellumCopyMarkdown(source)
}

function vellumOpenMenu() {
  return {
    open: false,
    active: 0,
    items() {
      return [...(this.$refs.menu?.querySelectorAll('[role=menuitem]') ?? [])]
    },
    openMenu() {
      this.open = true
      this.active = 0
      this.$nextTick(() => this.items()[0]?.focus())
    },
    closeMenu() {
      this.open = false
      this.$nextTick(() => this.$refs.trigger?.focus())
    },
    toggle() {
      if (this.open) {
        this.closeMenu()
      } else {
        this.openMenu()
      }
    },
    onTriggerKeydown(event) {
      if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault()
        this.openMenu()
      }
    },
    onMenuKeydown(event) {
      const items = this.items()
      if (event.key === 'Escape') {
        event.preventDefault()
        this.closeMenu()
        return
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault()
        this.active = (this.active + 1) % items.length
        items[this.active]?.focus()
        return
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault()
        this.active = (this.active - 1 + items.length) % items.length
        items[this.active]?.focus()
        return
      }
      if (event.key === 'Home') {
        event.preventDefault()
        this.active = 0
        items[0]?.focus()
        return
      }
      if (event.key === 'End') {
        event.preventDefault()
        this.active = items.length - 1
        items[this.active]?.focus()
      }
    },
  }
}

/**
 * Tabs root state: roving tabindex, arrow-key navigation, optional persist.
 */
function vellumTabs(initial = '', persist = null) {
  return {
    active: initial,
    persist,
    init() {
      if (this.persist) {
        try {
          const stored = localStorage.getItem('vellum-tabs-' + this.persist)
          const tabs = [...this.$el.querySelectorAll('[role="tab"]')]
          if (stored && tabs.some((tab) => tab.getAttribute('data-value') === stored)) {
            this.active = stored
          }
        } catch (e) {}
      }
      if (!this.active) {
        const first = this.$el.querySelector('[role="tab"]')
        this.active = first?.getAttribute('data-value') ?? ''
      }
    },
    select(value) {
      this.active = value
      if (!this.persist) {
        return
      }
      try {
        localStorage.setItem('vellum-tabs-' + this.persist, value)
      } catch (e) {}
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
  Alpine.data('vellumHotkeyChip', vellumHotkeyChip)
  Alpine.data('vellumChrome', vellumChrome)
  Alpine.data('vellumHeadingCopy', vellumHeadingCopy)
  Alpine.data('vellumPageActions', vellumPageActions)
  Alpine.data('vellumOpenMenu', vellumOpenMenu)
  Alpine.data('vellumScrollSpy', vellumScrollSpy)
  Alpine.data('vellumPrefetchHover', prefetchHover)
  window.Alpine = Alpine
  Alpine.start()

  function warmupLazyChunksOnce() {
    const warmup = () => {
      window.removeEventListener('pointerdown', warmup, true)
      window.removeEventListener('keydown', warmup, true)
      window.removeEventListener('touchstart', warmup, true)
      window.VellumFocus?.load()?.catch(() => {})
      window.VellumAnchor?.load()?.catch(() => {})
      window.VellumSearch?.load()?.catch(() => {})
    }

    window.addEventListener('pointerdown', warmup, true)
    window.addEventListener('keydown', warmup, true)
    window.addEventListener('touchstart', warmup, true)
  }

  warmupLazyChunksOnce()
}
