import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { gzipSync } from 'node:zlib'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')

const limits = {
  'resources/dist/vellum.css': 25 * 1024,
  'resources/dist/vellum.js': 28 * 1024,
  // Lazy chunks, loaded only when someone searches.
  'resources/dist/vellum-search.js': 10 * 1024,
}

const reported = [
  'resources/dist/vellum-anchor.js',
  'resources/dist/vellum-focus.js',
]

let failed = false

for (const [relative, limit] of Object.entries(limits)) {
  const absolute = join(root, relative)
  let buffer

  try {
    buffer = readFileSync(absolute)
  } catch {
    console.error(`FAIL: missing ${relative}`)
    failed = true
    continue
  }

  const gzipped = gzipSync(buffer).length
  const kb = (gzipped / 1024).toFixed(2)
  const limitKb = (limit / 1024).toFixed(0)
  const status = gzipped <= limit ? 'ok' : 'FAIL'

  console.log(`${relative}: ${kb} KB gzipped (limit ${limitKb} KB) [${status}]`)

  if (gzipped > limit) {
    failed = true
  }
}

for (const relative of reported) {
  const absolute = join(root, relative)
  try {
    const gzipped = gzipSync(readFileSync(absolute)).length
    console.log(`${relative}: ${(gzipped / 1024).toFixed(2)} KB gzipped [lazy chunk, not gated]`)
  } catch {
    console.error(`FAIL: missing ${relative}`)
    failed = true
  }
}

process.exit(failed ? 1 : 0)
