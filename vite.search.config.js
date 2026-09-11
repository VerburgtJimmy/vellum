import { defineConfig } from 'vite'
import { resolve } from 'node:path'

/**
 * Separate build for the lazy MiniSearch chunk so it is never inlined
 * into the main Alpine bundle.
 */
export default defineConfig({
  build: {
    outDir: 'resources/dist',
    emptyOutDir: false,
    minify: 'esbuild',
    target: 'es2020',
    lib: {
      entry: resolve(__dirname, 'resources/js/search.js'),
      formats: ['es'],
      fileName: () => 'vellum-search.js',
    },
    rollupOptions: {
      output: {
        compact: true,
      },
    },
  },
  esbuild: {
    legalComments: 'none',
    minifyIdentifiers: true,
    minifySyntax: true,
    minifyWhitespace: true,
  },
})
