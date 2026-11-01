import { describe, expect, it } from 'vitest'
import { reader, streamItem } from '../../resources/js/playground/stream.js'

const read = (item, ...chunks) => {
  const parse = reader(item)

  return [...chunks.flatMap((chunk) => parse.push(chunk)), ...parse.end()]
}

describe('reading a stream', () => {
  it('knows a stream by its content type', () => {
    expect(streamItem('text/event-stream; charset=utf-8')).toBe('event')
    expect(streamItem('Application/JSONL')).toBe('line')
    expect(streamItem('application/x-ndjson')).toBe('line')
    expect(streamItem('application/json-seq')).toBe('record')
    expect(streamItem('application/json')).toBeNull()
    expect(streamItem(undefined)).toBeNull()
  })

  it('reads events with their fields', () => {
    expect(read('event', 'event: position\nid: 7\nretry: 1000\ndata: {"lat":1}\n\n')).toEqual([{ event: 'position', id: '7', retry: '1000', data: '{"lat":1}' }])
  })

  it('joins data lines, and ignores comments and fields it does not know', () => {
    expect(read('event', ': keep alive\nfoo: bar\ndata: one\ndata: two\n\n')).toEqual([{ data: 'one\ntwo' }])
  })

  it('takes one leading space off a value, and no more', () => {
    expect(read('event', 'data:  two spaces\n\ndata:none\n\n')).toEqual([{ data: ' two spaces' }, { data: 'none' }])
  })

  it('reads events cut anywhere by the network', () => {
    const text = 'event: a\r\ndata: 1\r\n\r\ndata: 2\r\n\r\n'

    for (let at = 1; at < text.length; at++) {
      expect(read('event', text.slice(0, at), text.slice(at))).toEqual([{ event: 'a', data: '1' }, { data: '2' }])
    }
  })

  it('reads line endings of any kind', () => {
    expect(read('event', 'data: a\r\rdata: b\n\n')).toEqual([{ data: 'a' }, { data: 'b' }])
  })

  it('sends no event for a block with no data, and drops one the stream never finished', () => {
    expect(read('event', 'event: nothing\n\ndata: unfinished\n')).toEqual([])
  })

  it('does not let a bad retry through', () => {
    expect(read('event', 'retry: soon\ndata: a\n\n')).toEqual([{ data: 'a' }])
  })

  it('reads a line at a time, split or not, with or without a last line break', () => {
    expect(read('line', '{"a":1}\n{"b"', ':2}\n\n{"c":3}')).toEqual([{ data: '{"a":1}' }, { data: '{"b":2}' }, { data: '{"c":3}' }])
  })

  it('reads records, each as soon as its line ends', () => {
    const parse = reader('record')

    expect(parse.push('\x1e{"a":1}\n\x1e{"b"')).toEqual([{ data: '{"a":1}' }])
    expect(parse.push(':2}\n')).toEqual([{ data: '{"b":2}' }])
    expect(parse.end()).toEqual([])
  })
})
