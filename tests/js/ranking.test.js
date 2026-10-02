import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { createRanker, groupByPage, highlight, spread, stem } from '../../resources/js/search.js'

const fixture = JSON.parse(readFileSync(new URL('../fixtures/ranking-parity.json', import.meta.url), 'utf8'))
const ranker = createRanker(fixture.index)

describe('ranking in the browser', () => {
  it('ranks and scores exactly as PHP does', () => {
    for (const [query, expected] of Object.entries(fixture.rankings)) {
      const found = ranker.search(query).map((hit) => ({ id: hit.record.id, score: hit.score }))

      expect(found.map((hit) => hit.id), query).toEqual(expected.map((hit) => hit.id))

      found.forEach((hit, i) => expect(hit.score, `${query} #${i}`).toBeCloseTo(expected[i].score, 5))
    }
  })

  it('stems words exactly as PHP does', () => {
    for (const [word, expected] of Object.entries(fixture.stems)) {
      expect(stem(word), word).toBe(expected)
    }
  })

  it('keeps at most two sections of one page ahead of other pages', () => {
    const hit = (page) => ({ record: { page } })
    const ordered = spread([hit('a'), hit('a'), hit('a'), hit('b')], 4).map((result) => result.record.page)

    expect(ordered).toEqual(['a', 'a', 'b', 'a'])
  })

  it('puts results of one page together, pages in the order of their best result', () => {
    const hits = ['a#1', 'b#1', 'a#2', 'c#1', 'b#2'].map((id) => ({ id, page: id.split('#')[0] }))

    expect(groupByPage(hits).map((hit) => hit.id)).toEqual(['a#1', 'a#2', 'b#1', 'b#2', 'c#1'])
  })

  it('marks the reader\'s words in a passage, escaping the rest', () => {
    expect(highlight('Install <b>it</b> now', 'install')).toBe(
      '<mark class="vellum-search-mark">Install</mark> &lt;b&gt;it&lt;/b&gt; now',
    )
  })
})
