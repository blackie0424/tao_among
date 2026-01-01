// Service Worker 版本（修改此版本號會觸發更新）
const SW_VERSION = 'v1.4.0-protocol-fix'
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

  // 排除 Inertia.js 的 XHR 請求（帶有 X-Inertia header）
  if (event.request.headers.get('X-Inertia')) {
    return
  }

  // 只快取同源的 GET 請求
  if (!event.request.url.startsWith(self.location.origin)) {
    return
  }

  // 排除 HTML 導航請求，只快取靜態資源
  const url = new URL(event.request.url)
  const isStaticAsset = /\.(js|css|png|jpg|jpeg|gif|svg|woff|woff2|ttf|ico|webp)$/i.test(
    url.pathname
  )

  // 只快取靜態資源，不快取 HTML 頁面
  if (!isStaticAsset) {
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
