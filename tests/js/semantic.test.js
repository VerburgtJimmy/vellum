import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { cosines, readSemanticFile } from '../../resources/js/semantic.js'

const bytes = readFileSync(new URL('../fixtures/semantic/query-parity.bin', import.meta.url))
const fixture = JSON.parse(readFileSync(new URL('../fixtures/semantic/query-parity.json', import.meta.url), 'utf8'))
const file = readSemanticFile(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength))

describe('the semantic file in the browser', () => {
  it('reads what PHP wrote', () => {
    expect(file.ids).toEqual(['gating#', '#', '#dark-mode'])
    expect(file.dims).toBe(8)
    expect(file.vocab.size).toBeGreaterThan(100)
    expect(file.attribution).toContain('MIT')
  })

  it('scores every query exactly as PHP does', () => {
    for (const [query, expected] of Object.entries(fixture.queries)) {
      const scores = cosines(query, file)

      expect([...scores.keys()].sort(), query).toEqual(Object.keys(expected).sort())

      for (const [id, score] of Object.entries(expected)) {
        expect(scores.get(id), `${query} / ${id}`).toBeCloseTo(score, 5)
      }
    }
  })
})
