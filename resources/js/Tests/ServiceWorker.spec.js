import { describe, it, expect, vi, beforeEach } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

const swCode = readFileSync(resolve(process.cwd(), 'public/service-worker.js'), 'utf-8')

function createMockEnv() {
  const mockCache = {
    addAll: vi.fn().mockResolvedValue(undefined),
    match: vi.fn().mockResolvedValue(null),
    put: vi.fn().mockResolvedValue(undefined),
  }
  const mockCaches = {
    open: vi.fn().mockResolvedValue(mockCache),
    keys: vi.fn().mockResolvedValue([]),
    delete: vi.fn().mockResolvedValue(true),
  }
  const handlers = {}
  const mockSelf = {
    location: { origin: 'https://among.pongsonotao.org' },
    addEventListener: (event, handler) => {
      handlers[event] = handler
    },
    skipWaiting: vi.fn(),
    clients: { claim: vi.fn() },
  }
  const mockFetch = vi.fn()

  return { mockCache, mockCaches, mockSelf, handlers, mockFetch }
}

function loadSW(env) {
  const { mockSelf, mockCaches, mockFetch } = env
  // eslint-disable-next-line no-new-func
  const fn = new Function('self', 'caches', 'fetch', 'Response', 'URL', swCode)
  fn(mockSelf, mockCaches, mockFetch, Response, URL)
}

function makeRequest(url, { method = 'GET', mode = 'navigate', headers = {} } = {}) {
  return {
    url,
    method,
    mode,
    headers: { get: (name) => headers[name] ?? null },
  }
}

function makeEvent(request) {
  return { request, respondWith: vi.fn() }
}

const ORIGIN = 'https://among.pongsonotao.org'

describe('Service Worker', () => {
  let env

  beforeEach(() => {
    env = createMockEnv()
    loadSW(env)
  })

  describe('EXCLUDE_PATHS 排除規則', () => {
    it('/admin/ 路徑不應呼叫 respondWith（完全不攔截）', () => {
      const event = makeEvent(
        makeRequest(`${ORIGIN}/admin/references/create`, { mode: 'navigate' }),
      )
      env.handlers['fetch'](event)
      expect(event.respondWith).not.toHaveBeenCalled()
    })

    it('/admin/ 子路徑也不應被攔截', () => {
      const event = makeEvent(
        makeRequest(`${ORIGIN}/admin/references`, { mode: 'navigate' }),
      )
      env.handlers['fetch'](event)
      expect(event.respondWith).not.toHaveBeenCalled()
    })

    it('/prefix/api/ 路徑不應呼叫 respondWith', () => {
      const event = makeEvent(makeRequest(`${ORIGIN}/prefix/api/fish`))
      env.handlers['fetch'](event)
      expect(event.respondWith).not.toHaveBeenCalled()
    })

    it('/login 路徑不應呼叫 respondWith', () => {
      const event = makeEvent(makeRequest(`${ORIGIN}/login`))
      env.handlers['fetch'](event)
      expect(event.respondWith).not.toHaveBeenCalled()
    })
  })

  describe('staleWhileRevalidate 容錯', () => {
    it('cache 和 network 都失敗時，應回傳有效 Response 而非 null', async () => {
      env.mockCache.match.mockResolvedValue(null)
      env.mockFetch.mockRejectedValue(new Error('Network error'))

      // 非導航、非靜態資源請求 → 走 staleWhileRevalidate
      const request = makeRequest(`${ORIGIN}/some-dynamic-data`, {
        mode: 'cors',
        headers: { accept: 'application/json' },
      })
      const event = makeEvent(request)
      env.handlers['fetch'](event)

      expect(event.respondWith).toHaveBeenCalled()
      const response = await event.respondWith.mock.calls[0][0]

      expect(response).toBeInstanceOf(Response)
      expect(response.status).toBeGreaterThanOrEqual(200)
    })

    it('cache 有資料時，應立即回傳快取 Response', async () => {
      const cachedResponse = new Response('cached', { status: 200 })
      env.mockCache.match.mockResolvedValue(cachedResponse)
      env.mockFetch.mockResolvedValue(new Response('network', { status: 200 }))

      const request = makeRequest(`${ORIGIN}/some-dynamic-data`, {
        mode: 'cors',
        headers: { accept: 'application/json' },
      })
      const event = makeEvent(request)
      env.handlers['fetch'](event)

      const response = await event.respondWith.mock.calls[0][0]
      expect(response).toBe(cachedResponse)
    })
  })

  describe('非 GET 請求', () => {
    it('POST 請求不應呼叫 respondWith', () => {
      const event = makeEvent(
        makeRequest(`${ORIGIN}/admin/references`, { method: 'POST', mode: 'cors' }),
      )
      env.handlers['fetch'](event)
      expect(event.respondWith).not.toHaveBeenCalled()
    })
  })
})
