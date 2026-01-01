// Service Worker 版本（用於 PWA 安裝檢測）
const SW_VERSION = 'v2.0.0-pwa-only'

self.addEventListener('install', (event) => {
  console.log(`[SW ${SW_VERSION}] Installing...`)
  // 立即啟用新版本
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  console.log(`[SW ${SW_VERSION}] Activating...`)

  // 清除所有舊的快取（如果有的話）
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            console.log(`[SW ${SW_VERSION}] Clearing old cache: ${cacheName}`)
            return caches.delete(cacheName)
          })
        )
      })
      .then(() => {
        // 立即控制所有頁面
        return self.clients.claim()
      })
  )
})

// 不攔截任何請求，完全由瀏覽器原生處理
// Service Worker 僅用於 PWA 安裝功能
