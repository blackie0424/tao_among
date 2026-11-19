import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { createApp, h } from 'vue'
import { webcrypto } from 'node:crypto'

// 真的 Inertia 前端 + 真的 Fishs 清單頁 + jsdom 的歷史紀錄。只有伺服器（axios adapter）是假的，
// 用來驗證：網路中斷、伺服器 5xx 的實際失敗路徑，以及「選看法 → 翻頁 → 詳細頁 → 返回」時選項計算失敗。

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

const VERSION = 'test-version'
const VIEWER = { id: 5, role: 'viewer' }
const NIGHT_LABEL = '晚上釣魚會遇到的魚'
const OPTIONS = [
  { key: 'night', label: NIGHT_LABEL, hint: '有人記錄在晚上用釣的方式捕到', count: 2 },
  { key: 'all', label: '全部都看（不分類）', hint: '', count: 5 },
]
const NIGHT_META = { key: 'night', label: NIGHT_LABEL, hint: '有人記錄在晚上用釣的方式捕到' }

// ── 假伺服器 ────────────────────────────────────────────────
// failure：none 正常、network 網路中斷（沒有回應）、http500 伺服器錯誤（非 Inertia 的網頁）
// optionsFailed：第一頁選項計算失敗（viewOptions 為 null）
const server = { failure: 'none', optionsFailed: false, loads: 0 }

const baseProps = () => ({
  filters: {},
  searchOptions: {},
  searchStats: {},
  auth: { user: VIEWER },
  flash: {},
  errors: {},
  storageFolders: { image: 'images', webp: 'webp' },
})

const listProps = (params) => {
  const firstPage = !params.has('last_id')
  const options = server.optionsFailed ? null : OPTIONS
  if (params.get('view') === 'night') {
    return firstPage
      ? {
          items: [{ id: 11, name: '夜釣魚一', image_url: 'a' }],
          pageInfo: { hasMore: true, nextCursor: 11 },
          view: 'night',
          viewMeta: NIGHT_META,
          viewOptions: options,
        }
      : {
          // 往下載入的回應：沒有 viewOptions 欄位
          items: [{ id: 10, name: '夜釣魚二', image_url: 'b' }],
          pageInfo: { hasMore: false, nextCursor: null },
          view: 'night',
          viewMeta: NIGHT_META,
        }
  }
  return {
    items: [{ id: 1, name: '全部魚一', image_url: 'c' }],
    pageInfo: { hasMore: false, nextCursor: null },
    view: 'all',
    viewMeta: null,
    viewOptions: options,
  }
}

const inertiaPage = (url, props) => {
  server.loads += 1
  return {
    component: url.pathname === '/other' ? 'Other' : 'Fishs',
    url: url.pathname + url.search,
    version: VERSION,
    props: { ...baseProps(), ...props },
  }
}

const fakeAdapter = async (config) => {
  const url = new URL(config.url, 'http://localhost')
  const isView = url.searchParams.has('view')
  if (isView && server.failure === 'network') {
    throw Object.assign(new Error('Network Error'), { isAxiosError: true, code: 'ERR_NETWORK' })
  }
  if (isView && server.failure === 'http500') {
    throw Object.assign(new Error('Request failed with status code 500'), {
      isAxiosError: true,
      response: {
        status: 500,
        statusText: 'Server Error',
        data: '<html><body>Server Error</body></html>',
        headers: { 'content-type': 'text/html' },
        config,
      },
    })
  }
  const props = url.pathname === '/other' ? {} : listProps(url.searchParams)
  return {
    data: inertiaPage(url, props),
    status: 200,
    statusText: 'OK',
    headers: { 'x-inertia': 'true', 'content-type': 'application/json' },
    config,
    request: {},
  }
}

// ── 啟動 / 輔助 ─────────────────────────────────────────────
const bodyText = () => document.body.textContent
const find = (id) => document.body.querySelector(`[data-testid="${id}"]`)

let inertia
const startApp = async () => {
  vi.resetModules()
  const axios = (await import('axios')).default
  axios.defaults.adapter = fakeAdapter
  inertia = await import('@inertiajs/vue3')
  const Fishs = (await import('../Pages/Fishs.vue')).default

  await inertia.createInertiaApp({
    id: 'app',
    page: inertiaPage(new URL('http://localhost/'), listProps(new URLSearchParams())),
    resolve: (name) => (name === 'Fishs' ? Fishs : { render: () => h('p', '其他頁面') }),
    setup({ el, App, props, plugin }) {
      createApp({ render: () => h(App, props) })
        .use(plugin)
        .mount(el)
    },
  })
}

// 失敗時不要讓 Inertia 跳出自己的錯誤視窗、也不要丟出未處理的例外：驗證的是清單上的畫面
const swallowInertiaFailureEvents = () => {
  const prevent = (event) => event.preventDefault()
  document.addEventListener('inertia:invalid', prevent)
  document.addEventListener('inertia:exception', prevent)
  return () => {
    document.removeEventListener('inertia:invalid', prevent)
    document.removeEventListener('inertia:exception', prevent)
  }
}

describe('清單看法：實際請求失敗與歷史返回（真的 Inertia）', () => {
  let stopSwallowing

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
    localStorage.clear()
    window.history.replaceState(null, '', '/')
    document.body.innerHTML = '<div id="app"></div>'
    Object.assign(server, { failure: 'none', optionsFailed: false, loads: 0 })
    stopSwallowing = swallowInertiaFailureEvents()
  })

  afterEach(() => {
    stopSwallowing()
    document.body.innerHTML = ''
  })

  it.each([
    ['網路中斷', 'network'],
    ['伺服器 500 錯誤', 'http500'],
  ])('選看法時遇到%s：解除載入並顯示重試與看全部，重試成功後顯示該看法', async (_name, failure) => {
    await startApp()
    await vi.waitFor(() => expect(find('view-picker')).not.toBeNull())
    server.failure = failure

    find('view-option-night').click()

    await vi.waitFor(() => expect(find('view-error')).not.toBeNull())
    expect(find('view-error').textContent).toContain('載入失敗')
    expect(find('view-error-all')).not.toBeNull()

    server.failure = 'none'
    find('view-error-retry').click()

    await vi.waitFor(() => expect(bodyText()).toContain('夜釣魚一'))
    expect(find('view-error')).toBeNull()
    expect(find('view-bar').textContent).toContain(`${NIGHT_LABEL}（2）`)
  })

  it('選看法 → 翻頁 → 詳細頁 → 返回，重取時選項計算失敗：橫條仍有名稱、沒有數字與換一個', async () => {
    stopSwallowing() // 這個情境不該有失敗事件；有的話要看得到
    await startApp()
    await vi.waitFor(() => expect(find('view-picker')).not.toBeNull())
    find('view-option-night').click()
    await vi.waitFor(() => expect(bodyText()).toContain('夜釣魚一'))

    // 往下載入第二頁：回應沒有 viewOptions 欄位
    const secondPage = await new Promise((resolve) =>
      inertia.router.get(
        '/',
        { view: 'night', perPage: 20, last_id: 11 },
        { preserveState: true, preserveScroll: true, replace: true, onSuccess: resolve }
      )
    )
    expect(secondPage.props.items[0].name).toBe('夜釣魚二')
    expect(secondPage.props).not.toHaveProperty('viewOptions')
    expect(secondPage.props.viewMeta.label).toBe(NIGHT_LABEL)

    await new Promise((resolve) => setTimeout(resolve, 100)) // 讓上一個請求先收尾
    inertia.router.visit('/other')
    await vi.waitFor(() => expect(bodyText()).toContain('其他頁面'))

    // 返回時重取第一頁，這次選項計算失敗
    server.optionsFailed = true
    window.history.back()

    await vi.waitFor(() => expect(bodyText()).toContain('夜釣魚一'), { timeout: 3000 })
    const bar = find('view-bar')
    expect(bar.textContent).toContain(NIGHT_LABEL)
    expect(bar.textContent).not.toContain('（')
    expect(find('view-bar-change')).toBeNull()
    expect(find('floating-view')).toBeNull()

    // 沒有小視窗可用時，仍能用「看全部」回去
    find('view-bar-all').click()
    await vi.waitFor(() => expect(bodyText()).toContain('全部魚一'))
    expect(find('view-bar')).toBeNull()
  })
})
