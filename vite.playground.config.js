import { defineConfig } from 'vite'
import { resolve } from 'node:path'

/**
 * Separate build for the API playground, loaded on first use so it is never
 * part of the main bundle.
 */
export default defineConfig({
  build: {
    outDir: 'resources/dist',
    emptyOutDir: false,
    minify: 'esbuild',
    target: 'es2020',
    lib: {
      entry: resolve(__dirname, 'resources/js/playground.js'),
      formats: ['es'],
      fileName: () => 'vellum-playground.js',
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
