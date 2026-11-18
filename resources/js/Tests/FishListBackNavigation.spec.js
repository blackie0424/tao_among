import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createApp, h } from 'vue'
import { webcrypto } from 'node:crypto'

// 真的 Inertia 前端 + 真的 Fishs 清單頁 + jsdom 的歷史紀錄（history.back）。
// 只有伺服器用假的：依「目前身分」回傳對應的 Inertia 頁面，格式與 Laravel 一致
// （encryptHistory、clearHistory 旗標由後端測試 PublicFishListTest、LoginRedirectTest 保證會送出）。

vi.mock('@/api/fishApi', () => ({
  getFishCompact: vi.fn(),
  getFishLatestAt: vi.fn().mockResolvedValue(null),
}))

vi.mock('../utils/AnimationOptimizer.js', () => ({
  default: {
    optimizationLevel: 'none',
    animationConfig: { enableTransitions: true, enableAnimations: true },
    shouldEnableAnimation: vi.fn(() => true),
    getOptimalAnimationConfig: vi.fn(() => ({ enableTransitions: true, enableAnimations: true })),
    init: vi.fn(),
  },
}))

const CACHE_KEY = 'fishs_list_state'
const PAGE_INFO = { hasMore: false, nextCursor: null }
const VERSION = 'test-version'

const ITEMS = {
  guest: [{ id: 1, name: '訪客看到的魚', image_url: 'a' }],
  admin: [{ id: 2, name: '管理員看到的魚', image_url: 'b' }],
}
const USERS = { guest: null, admin: { id: 1, role: 'admin' } }

// ── 假伺服器 ────────────────────────────────────────────────
const server = { identity: 'guest', clearNext: false, encrypt: true }

const listPage = () => {
  const page = {
    component: 'Fishs',
    url: '/',
    version: VERSION,
    encryptHistory: server.encrypt,
    clearHistory: server.clearNext,
    props: {
      items: ITEMS[server.identity],
      pageInfo: PAGE_INFO,
      filters: {},
      searchOptions: {},
      searchStats: {},
      auth: { user: USERS[server.identity] },
      flash: {},
      errors: {},
      storageFolders: { image: 'images', webp: 'webp' },
    },
  }
  server.clearNext = false
  return page
}

const otherPage = () => ({
  component: 'Other',
  url: '/other',
  version: VERSION,
  props: { auth: { user: USERS[server.identity] }, flash: {}, errors: {} },
})

const fakeAdapter = async (config) => {
  const path = new URL(config.url, 'http://localhost').pathname
  const page = path === '/other' ? otherPage() : listPage()
  return {
    data: page,
    status: 200,
    statusText: 'OK',
    headers: { 'x-inertia': 'true', 'content-type': 'application/json' },
    config,
    request: {},
  }
}

// ── 啟動 / 輔助 ─────────────────────────────────────────────
const bodyText = () => document.body.textContent

// Inertia 的 router 是單例，每個測試都重新載入模組，避免前一個測試的狀態殘留
let inertia
const startApp = async () => {
  vi.resetModules()
  const axios = (await import('axios')).default
  axios.defaults.adapter = fakeAdapter
  inertia = await import('@inertiajs/vue3')
  const Fishs = (await import('../Pages/Fishs.vue')).default

  await inertia.createInertiaApp({
    id: 'app',
    page: listPage(),
    resolve: (name) => (name === 'Fishs' ? Fishs : { render: () => h('p', '其他頁面') }),
    setup({ el, App, props, plugin }) {
      createApp({ render: () => h(App, props) })
        .use(plugin)
        .mount(el)
    },
  })
}

const cachedIdentity = () => {
  const raw = sessionStorage.getItem(CACHE_KEY)
  return raw ? JSON.parse(raw).identity : null
}

const visit = (url) => inertia.router.visit(url)

describe('身分切換後按上一頁（真的 Inertia 歷史紀錄）', () => {
  beforeEach(() => {
    if (!window.crypto?.subtle) {
      Object.defineProperty(window, 'crypto', { value: webcrypto, configurable: true })
    }
    // @ts-ignore
    global.IntersectionObserver = class {
      observe() {}
      disconnect() {}
      unobserve() {}
    }
    vi.stubGlobal('scrollTo', vi.fn())
    sessionStorage.clear()
    window.history.replaceState(null, '', '/')
    document.body.innerHTML = '<div id="app"></div>'
    Object.assign(server, { identity: 'guest', clearNext: false, encrypt: true })
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  // 歷史：[0] / 訪客 → [1] /other → （登入）→ [2] / 管理員，然後連按兩次上一頁回到 [0]
  const loginThenGoBackToFirstEntry = async () => {
    await startApp()
    expect(bodyText()).toContain('訪客看到的魚')

    await visit('/other')
    await vi.waitFor(() => expect(bodyText()).toContain('其他頁面'))

    server.identity = 'admin'
    server.clearNext = true // 登入完成後的第一個 Inertia 回應帶 clearHistory
    await visit('/')
    await vi.waitFor(() => expect(bodyText()).toContain('管理員看到的魚'))

    window.history.go(-2)
  }

  it('訪客→登入→返回：畫面是管理員的資料，不是訪客的舊頁面', async () => {
    await loginThenGoBackToFirstEntry()

    await vi.waitFor(() => expect(window.location.pathname).toBe('/'))
    await vi.waitFor(() => expect(bodyText()).toContain('管理員看到的魚'), { timeout: 3000 })
    expect(bodyText()).not.toContain('訪客看到的魚')
    expect(['admin'].includes(cachedIdentity()?.split(':')[1]) || cachedIdentity() === null).toBe(
      true
    )
  })

  it('管理員→登出→返回：畫面是訪客的資料，不是管理員的舊頁面', async () => {
    server.identity = 'admin'
    await startApp()
    expect(bodyText()).toContain('管理員看到的魚')

    await visit('/other')
    await vi.waitFor(() => expect(bodyText()).toContain('其他頁面'))

    server.identity = 'guest'
    server.clearNext = true // 登出後的第一個 Inertia 回應帶 clearHistory
    await visit('/')
    await vi.waitFor(() => expect(bodyText()).toContain('訪客看到的魚'))

    window.history.go(-2)

    await vi.waitFor(() => expect(window.location.pathname).toBe('/'))
    await vi.waitFor(() => expect(bodyText()).toContain('訪客看到的魚'), { timeout: 3000 })
    expect(bodyText()).not.toContain('管理員看到的魚')
    expect(cachedIdentity() === null || cachedIdentity() === 'guest').toBe(true)
  })

  it('對照組：歷史紀錄沒加密、也沒清除時，返回會還原舊身分的頁面（證明這個測試抓得到問題）', async () => {
    server.encrypt = false
    await loginThenGoBackToFirstEntry()

    await vi.waitFor(() => expect(bodyText()).toContain('訪客看到的魚'), { timeout: 3000 })
    expect(bodyText()).not.toContain('管理員看到的魚')
  })
})
