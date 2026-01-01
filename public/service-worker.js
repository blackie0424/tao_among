// Service Worker 版本（修改此版本號會觸發更新）
const SW_VERSION = 'v1.3.0-exclude-inertia'
const CACHE_NAME = `tao-among-${SW_VERSION}`

self.addEventListener('install', (event) => {
  console.log(`[SW ${SW_VERSION}] Installing...`)
  self.skipWaiting()
})

// 監聽來自頁面的訊息
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    console.log(`[SW ${SW_VERSION}] Received SKIP_WAITING message`)
    self.skipWaiting()
  }
})

self.addEventListener('activate', (event) => {
  console.log(`[SW ${SW_VERSION}] Activating...`)

  // 清除舊版本的快取
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => {
            console.log(`[SW ${SW_VERSION}] Deleting old cache: ${name}`)
            return caches.delete(name)
          })
      )
    })
  )

  clients.claim()
})

self.addEventListener('fetch', (event) => {
  // iOS Safari 對非 GET 請求的 Service Worker 處理有嚴格限制
  // 只快取 GET 請求，讓 POST/PUT/DELETE 直接通過
  if (event.request.method !== 'GET') {
    // 不攔截非 GET 請求，直接發送到網路
    return
  }

  // 不攔截 Inertia.js 的 XHR 請求（帶有 X-Inertia header）
  // 這些請求返回 JSON，不應該被快取
  if (event.request.headers.get('X-Inertia')) {
    return
  }

  // 只快取同源的 GET 請求
  if (!event.request.url.startsWith(self.location.origin)) {
    return
  }

  event.respondWith(
    caches.match(event.request).then((response) => {
      if (response) {
        return response
      }

      return fetch(event.request).then((response) => {
        // 只快取成功的回應
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response
        }

        const responseToCache = response.clone()
        caches.open(CACHE_NAME).then((cache) => {
          cache.put(event.request, responseToCache)
        })

        return response
      })
    })
  )
})
