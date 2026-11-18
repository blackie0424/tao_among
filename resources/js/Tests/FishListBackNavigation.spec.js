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

const LABELS = { guest: '訪客看到的魚', admin: '管理員看到的魚' }
const USERS = { guest: null, admin: { id: 1, role: 'admin' } }

// ── 假伺服器 ────────────────────────────────────────────────
// loads：清單頁被伺服器產生了幾次；每次產生的魚名都帶流水號，才分得出「舊頁面」與「重新取得的頁面」
const server = { identity: 'guest', clearNext: false, encrypt: true, loads: 0 }

const listPage = () => {
  server.loads += 1
  const page = {
    component: 'Fishs',
    url: '/',
    version: VERSION,
    encryptHistory: server.encrypt,
    clearHistory: server.clearNext,
    props: {
      items: [
        { id: 1, name: `${LABELS[server.identity]}（載入 ${server.loads}）`, image_url: 'a' },
      ],
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
    Object.assign(server, { identity: 'guest', clearNext: false, encrypt: true, loads: 0 })
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  // 歷史：[0] / 訪客 → [1] /other → （登入）→ [2] / 管理員
  const buildHistoryAfterLogin = async () => {
    await startApp()
    expect(bodyText()).toContain('訪客看到的魚（載入 1）')

    await visit('/other')
    await vi.waitFor(() => expect(bodyText()).toContain('其他頁面'))

    server.identity = 'admin'
    server.clearNext = true // 登入完成後的第一個 Inertia 回應帶 clearHistory
    await visit('/')
    await vi.waitFor(() => expect(bodyText()).toContain('管理員看到的魚（載入 2）'))
  }

  // 連按兩次上一頁回到 [0]（popstate 由 jsdom 非同步送出，由各測試等待可觀察的結果）
  const goBackToFirstEntry = () => window.history.go(-2)

  it('訪客→登入→返回：重新向伺服器取得，畫面是管理員的資料，不是訪客的舊頁面', async () => {
    await buildHistoryAfterLogin()
    const loadsBeforeBack = server.loads

    goBackToFirstEntry()

    // 舊頁面解不開，必須重新取得：伺服器多產生一次清單，畫面是那一次的內容
    await vi.waitFor(() => expect(server.loads).toBeGreaterThan(loadsBeforeBack), { timeout: 3000 })
    await vi.waitFor(() => expect(bodyText()).toContain(`管理員看到的魚（載入 ${server.loads}）`))
    expect(bodyText()).not.toContain('訪客看到的魚')
    const identity = cachedIdentity()
    expect(identity === null || identity === '1:admin').toBe(true)
  })

  it('管理員→登出→返回：重新向伺服器取得，畫面是訪客的資料，不是管理員的舊頁面', async () => {
    server.identity = 'admin'
    await startApp()
    expect(bodyText()).toContain('管理員看到的魚（載入 1）')

    await visit('/other')
    await vi.waitFor(() => expect(bodyText()).toContain('其他頁面'))

    server.identity = 'guest'
    server.clearNext = true // 登出後的第一個 Inertia 回應帶 clearHistory
    await visit('/')
    await vi.waitFor(() => expect(bodyText()).toContain('訪客看到的魚（載入 2）'))
    const loadsBeforeBack = server.loads

    goBackToFirstEntry()

    await vi.waitFor(() => expect(server.loads).toBeGreaterThan(loadsBeforeBack), { timeout: 3000 })
    await vi.waitFor(() => expect(bodyText()).toContain(`訪客看到的魚（載入 ${server.loads}）`))
    expect(bodyText()).not.toContain('管理員看到的魚')
    expect(cachedIdentity() === null || cachedIdentity() === 'guest').toBe(true)
  })

  it('對照組：歷史紀錄沒加密時，返回不會重新取得，直接還原舊身分的頁面', async () => {
    server.encrypt = false
    await buildHistoryAfterLogin()
    const loadsBeforeBack = server.loads

    goBackToFirstEntry()

    // 畫面換成舊的訪客頁面，代表上一頁已處理完；伺服器沒被再問，因為舊頁面直接還原
    await vi.waitFor(() => expect(bodyText()).toContain('訪客看到的魚（載入 1）'), {
      timeout: 3000,
    })
    expect(server.loads).toBe(loadsBeforeBack)
    expect(bodyText()).not.toContain('管理員看到的魚')
  })
})
