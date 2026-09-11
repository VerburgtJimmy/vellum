import focus from '@alpinejs/focus'

let registered = false

/**
 * Register @alpinejs/focus once for dialog focus traps.
 */
export function registerFocus(Alpine) {
  if (registered) {
    return
  }

  Alpine.plugin(focus)
  registered = true
}
