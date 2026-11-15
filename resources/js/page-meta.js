import { t } from './strings.js'

/**
 * The line under a page's title says when the page last changed. The server
 * writes the date; for a recent change this says how long ago instead, which
 * reads faster, and keeps the date in the tooltip.
 */

const DAY = 86_400_000

/**
 * How long ago a date was, in whole calendar days, for the last month only.
 *
 * @param {Date} then
 * @param {Date} now
 * @param {string} [locale]  the page's language
 * @returns {string|null}  null when the date is in the future or a month or more ago
 */
export function relativeDay(then, now, locale = 'en') {
  const midnight = (date) => new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
  const days = Math.round((midnight(now) - midnight(then)) / DAY)

  if (days < 0 || days >= 30) {
    return null
  }

  return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(-days, 'day')
}

export function initPageMeta() {
  const time = document.querySelector('[data-vellum-page-meta] time[datetime]')
  const then = time ? new Date(time.dateTime) : null

  if (!then || Number.isNaN(then.getTime())) {
    return
  }

  const ago = relativeDay(then, new Date(), document.documentElement.lang || 'en')

  if (ago !== null) {
    time.textContent = t('page.updated', { date: ago })
  }
}
