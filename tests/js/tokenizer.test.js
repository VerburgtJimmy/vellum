import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { tokenize, words, normalize } from '../../resources/js/semantic.js'

const fixture = JSON.parse(readFileSync(new URL('../fixtures/semantic/wordpiece-parity.json', import.meta.url), 'utf8'))
const vocab = new Map(Object.entries(fixture.vocab))
const unknown = fixture.vocab[fixture.unknown]

describe('WordPiece in the browser', () => {
  it('matches the fixture PHP is held to, case by case', () => {
    expect(fixture.cases.length).toBeGreaterThan(200)

    for (const testCase of fixture.cases) {
      // PHP keeps [UNK] in its ids and skips it when pooling; the browser
      // drops it while tokenising. Compare what both actually use.
      const expected = testCase.ids.filter((id) => id !== unknown)

      expect(tokenize(testCase.text, vocab), testCase.text).toEqual(expected)
    }
  })

  it('normalises and splits the way Bert does', () => {
    expect(normalize('Héllo ÅNGSTRÖM')).toBe('hello angstrom')
    expect(words('a.b, c')).toEqual(['a', '.', 'b', ',', 'c'])
    expect(tokenize('', vocab)).toEqual([])
  })
})
