import { describe, expect, it } from 'vitest'
import { relativeDay } from '../../resources/js/page-meta.js'

const now = new Date(2026, 8, 28, 15, 0)

describe('when a page last changed', () => {
  it('counts calendar days, not 24-hour spans', () => {
    expect(relativeDay(new Date(2026, 8, 28, 1, 0), now)).toBe('today')
    expect(relativeDay(new Date(2026, 8, 27, 23, 0), now)).toBe('yesterday')
    expect(relativeDay(new Date(2026, 8, 25, 12, 0), now)).toBe('3 days ago')
  })

  it('leaves older and future dates to the written date', () => {
    expect(relativeDay(new Date(2026, 7, 30), now)).toBe('29 days ago')
    expect(relativeDay(new Date(2026, 7, 29), now)).toBeNull()
    expect(relativeDay(new Date(2026, 8, 29), now)).toBeNull()
  })
})
