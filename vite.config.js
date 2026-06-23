import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [vue()],
  build: {
    emptyOutDir: true,
    outDir: 'src/web/assets/dist',
    cssCodeSplit: false,
    lib: {
      entry: resolve(__dirname, 'resources/js/main.js'),
      name: 'CraftPopupPromoter',
      formats: ['iife'],
      fileName: () => 'popup-promoter.iife.js'
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'popup-promoter.css';
          }

          return '[name].[ext]';
        }
      }
    }
  }
});
