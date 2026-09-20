import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { cosines, readSemanticFile } from '../../resources/js/semantic.js'
import { createRanker, breadcrumb, cardContent, highlight } from '../../resources/js/search.js'

const bytes = readFileSync(new URL('../fixtures/semantic/query-parity.bin', import.meta.url))
const fixture = JSON.parse(readFileSync(new URL('../fixtures/semantic/query-parity.json', import.meta.url), 'utf8'))
const file = readSemanticFile(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength))
const ranker = createRanker(fixture.index, (query) => cosines(query, file))

describe('ranking in the browser', () => {
  it('ranks, scores and decides the card exactly as PHP does', () => {
    for (const [query, expected] of Object.entries(fixture.rankings)) {
      const result = ranker.search(query)

      expect(result.results.map((hit) => hit.record.id), query).toEqual(expected.ids)
      expect(result.confidence, query).toBeCloseTo(expected.confidence, 5)
      expect(result.card?.record?.id ?? null, query).toBe(expected.card)
    }
  })

  it('runs on words alone when the vectors did not load', () => {
    const lexical = createRanker(fixture.index, null)
    const result = lexical.search('gating')

    expect(result.results[0].record.id).toBe('gating#')
    expect(result.results[0].cosine).toBe(0)
  })

  it('shows the answer in the shape its type calls for', () => {
    expect(cardContent({ answer: { type: 'command', command: 'php artisan vellum:build' } }))
      .toEqual({ kind: 'code', code: 'php artisan vellum:build', language: 'bash' })
    expect(cardContent({ answer: { type: 'definition', sentence: 'Vellum is a package.' } }))
      .toEqual({ kind: 'text', text: 'Vellum is a package.', code: '', language: '' })
    expect(cardContent({ answer: null, passage: 'Fallback.' })).toEqual({ kind: 'text', text: 'Fallback.' })
    const rows = [{ key: 'checks.strict' }, { key: 'checks.references' }]

    expect(cardContent({ answer: { type: 'config', rows } })).toEqual({ kind: 'config', rows })
    expect(cardContent({ answer: { type: 'config', rows } }, 'what does checks.references do'))
      .toEqual({ kind: 'config', rows: [{ key: 'checks.references' }] })
  })

  it('reads a result as a breadcrumb and highlights the reader\'s words', () => {
    expect(breadcrumb({ title: 'Theming', heading: 'Accent' })).toBe('Theming › Accent')
    expect(breadcrumb({ title: 'Theming', heading: '' })).toBe('Theming')
    expect(highlight('A dark <b>theme</b>', 'dark')).toBe('A <mark class="vellum-search-mark">dark</mark> &lt;b&gt;theme&lt;/b&gt;')
  })
})
