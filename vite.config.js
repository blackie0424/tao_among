import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/css/fish.css', 'resources/js/app.js'],
      refresh: true,
    }),
    vue(),
  ],
  test: {
    environment: 'jsdom', // 這行很重要，讓測試有瀏覽器文件環境
  },
})
