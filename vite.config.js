import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css', 
        'resources/css/fish.css', 
        'resources/js/app.js'
      ],
      refresh: true,
    }),
    vue(),
  ],
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // Separate vendor libraries from app code
          vendor: ['vue', '@inertiajs/vue3'],
          // Bootstrap and axios can be in a separate chunk
          utils: ['axios'],
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': '/resources/js'
    }
  }
})
