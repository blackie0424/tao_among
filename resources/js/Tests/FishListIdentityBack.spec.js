import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// 目前登入身分：測試中改 pageMock.props.auth.user，模擬登入、登出
const pageMock = vi.hoisted(() => ({
  props: { auth: { user: null }, flash: {}, storageFolders: { image: 'images', webp: 'webp' } },
}))

vi.mock('@inertiajs/vue3', () => ({
  router: { get: vi.fn() },
  usePage: () => pageMock,
  Head: { name: 'Head', render: () => null },
  Link: { name: 'Link', template: '<a><slot /></a>' },
}))

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

import Fishs from '../Pages/Fishs.vue'

const STORAGE_KEY = 'fishs_list_state'
const PAGE_INFO = { hasMore: false, nextCursor: null }
const EDITOR = { id: 7, role: 'editor' }

const showPageAs = async (user, items) => {
  pageMock.props.auth = { user }
  const wrapper = mount(Fishs, { props: { items, pageInfo: PAGE_INFO } })
  await flushPromises()
  return wrapper
}

describe('身分切換後返回清單頁', () => {
  beforeEach(() => {
    sessionStorage.clear()
    pageMock.props.auth = { user: null }
    // @ts-ignore
    global.IntersectionObserver = class {
      observe() {}
      disconnect() {}
      unobserve() {}
    }
    vi.stubGlobal('scrollTo', vi.fn())
  })

  it('訪客看過清單、登入成 editor 後返回：畫面是 editor 的資料，訪客暫存被清掉', async () => {
    const guestPage = await showPageAs(null, [{ id: 1, name: '訪客看到的魚', image_url: 'a' }])
    expect(guestPage.text()).toContain('訪客看到的魚')
    guestPage.unmount() // 離開頁面時存下暫存
    expect(JSON.parse(sessionStorage.getItem(STORAGE_KEY)).identity).toBe('guest')

    // 登入後瀏覽器返回清單頁：伺服器依目前身分給的資料
    const editorPage = await showPageAs(EDITOR, [{ id: 2, name: '編輯者看到的魚', image_url: 'b' }])

    expect(editorPage.text()).toContain('編輯者看到的魚')
    expect(editorPage.text()).not.toContain('訪客看到的魚')
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('editor 登出成訪客後返回：畫面是訪客的資料，editor 暫存被清掉', async () => {
    const editorPage = await showPageAs(EDITOR, [{ id: 2, name: '編輯者看到的魚', image_url: 'b' }])
    editorPage.unmount()
    expect(JSON.parse(sessionStorage.getItem(STORAGE_KEY)).identity).toBe('7:editor')

    const guestPage = await showPageAs(null, [{ id: 1, name: '訪客看到的魚', image_url: 'a' }])

    expect(guestPage.text()).toContain('訪客看到的魚')
    expect(guestPage.text()).not.toContain('編輯者看到的魚')
    expect(sessionStorage.getItem(STORAGE_KEY)).toBeNull()
  })

  it('同一個身分返回時仍可還原暫存', async () => {
    const first = await showPageAs(EDITOR, [{ id: 2, name: '編輯者看到的魚', image_url: 'b' }])
    first.unmount()

    const back = await showPageAs(EDITOR, [])

    expect(back.text()).toContain('編輯者看到的魚')
  })
})
