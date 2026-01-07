/**
 * Tao Among PWA Service Worker
 * 採用 Stale-While-Revalidate 策略優化使用者體驗
 */

const CACHE_VERSION = 'v1.0.0';
const CACHE_NAME = `tao-among-${CACHE_VERSION}`;

// 預快取的核心資源
const PRECACHE_ASSETS = [
  '/',
  '/manifest.json',
  '/icons/icon-192x192.png',
  '/icons/icon-512x512.png',
  '/images/header-m.png',
  '/images/header-s.png'
];

// 需要快取的靜態資源類型
const CACHEABLE_EXTENSIONS = [
  '.css',
  '.js',
  '.png',
  '.jpg',
  '.jpeg',
  '.webp',
  '.svg',
  '.woff',
  '.woff2',
  '.ttf'
];

// 不應該快取的路徑
const EXCLUDE_PATHS = [
  '/api/',
  '/sanctum/',
  '/login',
  '/logout',
  '/register'
];

/**
 * 安裝事件：預快取核心資源
 */
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[SW] 預快取核心資源');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
      .catch((error) => {
        console.error('[SW] 預快取失敗:', error);
      })
  );
});

/**
 * 啟動事件：清理舊版快取
 */
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((name) => name.startsWith('tao-among-') && name !== CACHE_NAME)
            .map((name) => {
              console.log('[SW] 清理舊快取:', name);
              return caches.delete(name);
            })
        );
      })
      .then(() => self.clients.claim())
  );
});

/**
 * 判斷請求是否應該被快取
 */
function shouldCache(request) {
  const url = new URL(request.url);
  
  // 只快取同源請求
  if (url.origin !== self.location.origin) {
    return false;
  }
  
  // 排除 API 和認證相關路徑
  if (EXCLUDE_PATHS.some((path) => url.pathname.startsWith(path))) {
    return false;
  }
  
  // 只快取 GET 請求
  if (request.method !== 'GET') {
    return false;
  }
  
  return true;
}

/**
 * 判斷是否為靜態資源
 */
function isStaticAsset(url) {
  return CACHEABLE_EXTENSIONS.some((ext) => url.pathname.endsWith(ext));
}

/**
 * 判斷是否為導航請求（HTML 頁面）
 */
function isNavigationRequest(request) {
  return request.mode === 'navigate' || 
         (request.method === 'GET' && request.headers.get('accept')?.includes('text/html'));
}

/**
 * Stale-While-Revalidate 策略
 * 先回傳快取，同時在背景更新
 */
async function staleWhileRevalidate(request) {
  const cache = await caches.open(CACHE_NAME);
  const cachedResponse = await cache.match(request);
  
  const fetchPromise = fetch(request)
    .then((networkResponse) => {
      if (networkResponse && networkResponse.ok) {
        cache.put(request, networkResponse.clone());
      }
      return networkResponse;
    })
    .catch(() => cachedResponse);
  
  return cachedResponse || fetchPromise;
}

/**
 * Cache-First 策略（用於靜態資源）
 */
async function cacheFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  const cachedResponse = await cache.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    console.error('[SW] 網路請求失敗:', error);
    return new Response('Offline', { status: 503 });
  }
}

/**
 * Network-First 策略（用於導航請求）
 */
async function networkFirst(request) {
  const cache = await caches.open(CACHE_NAME);
  
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.ok) {
      cache.put(request, networkResponse.clone());
    }
    return networkResponse;
  } catch (error) {
    const cachedResponse = await cache.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // 離線時顯示首頁作為 fallback
    const fallback = await cache.match('/');
    return fallback || new Response('Offline', { status: 503 });
  }
}

/**
 * Fetch 事件處理
 */
self.addEventListener('fetch', (event) => {
  const request = event.request;
  
  if (!shouldCache(request)) {
    return;
  }
  
  const url = new URL(request.url);
  
  // 根據請求類型選擇快取策略
  if (isNavigationRequest(request)) {
    // 頁面導航：Network-First（確保取得最新內容）
    event.respondWith(networkFirst(request));
  } else if (isStaticAsset(url)) {
    // 靜態資源：Cache-First（優先使用快取）
    event.respondWith(cacheFirst(request));
  } else {
    // 其他請求：Stale-While-Revalidate
    event.respondWith(staleWhileRevalidate(request));
  }
});

/**
 * 接收來自主執行緒的訊息
 */
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.delete(CACHE_NAME).then(() => {
      console.log('[SW] 快取已清除');
    });
  }
});
