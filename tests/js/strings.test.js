import { afterEach, describe, expect, it } from 'vitest'
import { english, setStrings, t, tc } from '../../resources/js/strings.js'
import { search } from '../../resources/js/strings.en.json'

const lines = {
  search: {
    status: { none: 'Geen resultaten', count: ':count resultaat|:count resultaten' },
  },
  page: { zoom: { open: 'Groter bekijken: :name' } },
}

describe('the page strings', () => {
  afterEach(() => setStrings(undefined))

  it('reads a line by its dotted key', () => {
    setStrings(lines)

    expect(t('search.status.none')).toBe('Geen resultaten')
  })

  it('fills in each placeholder and leaves an unknown one', () => {
    setStrings(lines)

    expect(t('page.zoom.open', { name: 'kaart' })).toBe('Groter bekijken: kaart')
    expect(t('page.zoom.open')).toBe('Groter bekijken: :name')
  })

  it('picks the form for a count', () => {
    setStrings(lines)

    expect(tc('search.status.count', 1)).toBe('1 resultaat')
    expect(tc('search.status.count', 4)).toBe('4 resultaten')
  })

  it('gives the key back when the page has no line for it', () => {
    expect(t('search.status.none')).toBe('search.status.none')
    setStrings(lines)
    expect(t('search.status.missing')).toBe('search.status.missing')
    expect(t('search.status')).toBe('search.status')
  })

  it('reads the English a bundle carries when the page has no block, as under a layout from 0.9', () => {
    setStrings({})
    english({ search })

    expect(t('search.status.none')).toBe('No results')
    expect(tc('search.status.count', 3)).toBe('3 results')
    expect(t('search.status.missing')).toBe('search.status.missing')
  })

  it('prefers the page line to the English one', () => {
    english({ search })
    setStrings(lines)

    expect(t('search.status.none')).toBe('Geen resultaten')
    expect(t('search.status.ready')).toBe('Search ready')
  })
})
