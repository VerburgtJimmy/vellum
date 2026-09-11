import { readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { build } from 'esbuild'

const dist = join(dirname(fileURLToPath(import.meta.url)), '..', 'resources', 'dist')

const files = readdirSync(dist).filter((name) => name.endsWith('.js'))

await Promise.all(
  files.map((name) =>
    build({
      entryPoints: [join(dist, name)],
      outfile: join(dist, name),
      allowOverwrite: true,
      minify: true,
      legalComments: 'none',
      target: 'es2020',
      format: 'esm',
    }),
  ),
)

console.log(`minified ${files.join(', ')}`)
