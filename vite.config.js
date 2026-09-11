import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'node:path'

/**
 * Strip CSS imports from the ES bundle so browsers can load vellum.js
 * without a CSS module loader. CSS is shipped as a separate file.
 */
function stripCssImports() {
  return {
    name: 'vellum-strip-css-imports',
    generateBundle(_options, bundle) {
      for (const chunk of Object.values(bundle)) {
        if (chunk.type === 'chunk') {
          chunk.code = chunk.code.replace(/import\s*["'][^"']+\.css["'];?\n?/g, '')
        }
      }
    },
  }
}

export default defineConfig({
  plugins: [tailwindcss(), stripCssImports()],
  resolve: {
    alias: {
      // Prefer pre-minified ESM entry points so the main gzip budget stays lean.
      alpinejs: resolve(__dirname, 'node_modules/alpinejs/dist/module.esm.min.js'),
      '@alpinejs/collapse': resolve(__dirname, 'node_modules/@alpinejs/collapse/dist/module.esm.min.js'),
    },
  },
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    minify: 'esbuild',
    target: 'es2020',
    cssMinify: true,
    rollupOptions: {
      input: resolve(__dirname, 'resources/js/vellum.js'),
      output: {
        format: 'es',
        entryFileNames: 'vellum.js',
        chunkFileNames: 'chunk-[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'vellum.css'
          }

          return assetInfo.name ?? 'asset-[name][extname]'
        },
      },
    },
  },
})
