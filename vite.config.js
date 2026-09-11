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
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    minify: 'esbuild',
    // App-mode build (not lib) so the ES bundle is minified.
    rollupOptions: {
      input: resolve(__dirname, 'resources/js/vellum.js'),
      output: {
        format: 'es',
        entryFileNames: 'vellum.js',
        inlineDynamicImports: true,
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
