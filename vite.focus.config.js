import { defineConfig } from 'vite'
import { resolve } from 'node:path'

/**
 * Separate build for the lazy @alpinejs/focus chunk used by dialogs.
 */
export default defineConfig({
  resolve: {
    alias: {
      '@alpinejs/focus': resolve(__dirname, 'node_modules/@alpinejs/focus/dist/module.esm.min.js'),
    },
  },
  build: {
    outDir: 'resources/dist',
    emptyOutDir: false,
    minify: 'esbuild',
    target: 'es2020',
    lib: {
      entry: resolve(__dirname, 'resources/js/focus.js'),
      formats: ['es'],
      fileName: () => 'vellum-focus.js',
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
