const STORAGE_KEY = 'vellum-theme'

/**
 * Resolve light/dark/system preference into a concrete theme.
 */
export function resolveTheme(preference) {
  if (preference === 'dark' || preference === 'light') {
    return preference
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/**
 * Apply the theme class on <html> and optionally persist the preference.
 */
export function applyTheme(preference, { persist = true } = {}) {
  const resolved = resolveTheme(preference)
  document.documentElement.classList.toggle('dark', resolved === 'dark')

  if (persist) {
    try {
      localStorage.setItem(STORAGE_KEY, preference)
    } catch {
      // Ignore private-mode / blocked storage.
    }
  }

  return resolved
}

/**
 * Read the stored preference, falling back to the default.
 */
export function getStoredTheme(fallback = 'system') {
  try {
    return localStorage.getItem(STORAGE_KEY) || fallback
  } catch {
    return fallback
  }
}

/**
 * Apply the stored (or default) theme before interactive UI mounts.
 */
export function initTheme(fallback = 'system') {
  return applyTheme(getStoredTheme(fallback), { persist: false })
}
