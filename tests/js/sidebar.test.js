import { describe, expect, it } from 'vitest'
import { changedSince, visiblePart } from '../../resources/js/sidebar.js'

describe('the sidebar marker', () => {
  const entry = { x: 0, y: 100, w: 200, h: 32 }

  it('covers the whole entry when no folder clips it', () => {
    expect(visiblePart(entry, [])).toEqual(entry)
  })

  it('shrinks with a closing folder, and disappears once the entry is gone', () => {
    expect(visiblePart(entry, [{ y: 0, h: 116 }])).toEqual({ x: 0, y: 100, w: 200, h: 16 })
    expect(visiblePart(entry, [{ y: 0, h: 100 }])).toBeNull()
  })

  it('takes the tightest of nested folders', () => {
    expect(visiblePart(entry, [{ y: 0, h: 500 }, { y: 110, h: 10 }])).toEqual({ x: 0, y: 110, w: 200, h: 10 })
  })
})

describe('changed since last read', () => {
  const readAt = Date.parse('2026-09-01T00:00:00Z')

  it('marks a page updated after the reader last opened it', () => {
    expect(changedSince('2026-09-20', readAt)).toBe(true)
    expect(changedSince('2026-08-20', readAt)).toBe(false)
  })

  it('leaves pages alone the reader never opened, or without a date', () => {
    expect(changedSince('2026-09-20', 0)).toBe(false)
    expect(changedSince('', readAt)).toBe(false)
  })
})
