import { describe, expect, it } from 'vitest'
import { stepStates } from '../../resources/js/steps.js'

// Three steps 100px apart, the last ending at 300, with 28px numbers.
const tops = [0, 100, 200]
const states = (line) => stepStates(tops, 300, 28, line)

describe('steps following the reader', () => {
  it('fills nothing before the reader reaches the steps', () => {
    expect(states(-50).map((s) => [s.fill, s.reached, s.current])).toEqual([
      [0, false, false],
      [0, false, false],
      [0, false, false],
    ])
  })

  // Each line is 72px long: 100 from one step to the next, less the 28px number.
  it('fills the line under a step down to the reading line, as a share of its length', () => {
    expect(states(60)[0]).toEqual({ fill: 32 / 72, reached: true, current: true })
    expect(states(60)[1]).toEqual({ fill: 0, reached: false, current: false })
  })

  it('fills a passed step\'s line whole, and marks the next as current', () => {
    expect(states(150)[0].fill).toBe(1)
    expect(states(150)[1]).toEqual({ fill: 22 / 72, reached: true, current: true })
    expect(states(150)[0].current).toBe(false)
  })

  it('reaches a step once the reading line passes the middle of its number', () => {
    expect(states(113)[1].reached).toBe(false)
    expect(states(114)[1].reached).toBe(true)
  })

  it('never draws a line under the last step, and lets it go once read', () => {
    expect(states(260)[2]).toEqual({ fill: 0, reached: true, current: true })
    expect(states(400)[2].current).toBe(false)
    expect(states(400).every((s) => s.reached)).toBe(true)
  })
})
