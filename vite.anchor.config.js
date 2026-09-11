import { defineConfig } from 'vite'
import { resolve } from 'node:path'

/**
 * Separate build for the lazy @alpinejs/anchor + floating-ui chunk.
 */
export default defineConfig({
  build: {
    outDir: 'resources/dist',
    emptyOutDir: false,
    minify: 'esbuild',
    target: 'es2020',
    lib: {
      entry: resolve(__dirname, 'resources/js/anchor.js'),
      formats: ['es'],
      fileName: () => 'vellum-anchor.js',
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
