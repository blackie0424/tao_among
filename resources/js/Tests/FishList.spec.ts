import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Fishs from '../Pages/Fishs.vue'
import { router } from '@inertiajs/vue3'

// 模擬 Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn((url, params, opts) => {
      // 簡化：依參數決定回傳資料
      if (params.last_id === 999) {
        // 模擬游標錯誤
        opts.onError && opts.onError({ error: 'INVALID_CURSOR' })
        return
      }
      const base = [
        { id: 3, name: 'AAA', image_url: 'x' },
        { id: 2, name: 'BBB', image_url: 'y' },
        { id: 1, name: 'CCC', image_url: 'z' }
      ]
      opts.onSuccess && opts.onSuccess({ props: { items: base.slice(0, 2), pageInfo: { hasMore: true, nextCursor: 1 } } })
    })
  },
  Head: { name: 'Head', render: () => null },
  Link: { name: 'Link', template: '<a><slot /></a>' }
}))

describe('Fishs infinite list', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // 提供 IntersectionObserver mock
    // @ts-ignore
    class IOStub {
      /** @type {(entries: Array<{isIntersecting: boolean}>) => void} */
      cb
  /** @param {(entries: Array<{isIntersecting: boolean}>) => void} cb */
  constructor(cb: any) { this.cb = cb }
      /** @param {Element} el */
  observe(el: any) { /* 初始不觸發，測試可手動 */ }
      disconnect() {}
      unobserve() {}
      // 測試手動觸發
      fire(isIntersecting = true) { this.cb([{ isIntersecting }]) }
    }
    // @ts-ignore
    global.IntersectionObserver = IOStub
  })

  it('renders initial items and loading component hidden when not loading', () => {
    const wrapper = mount(Fishs, {
      props: { items: [{ id: 1, name: 'Test', image_url: 'a' }], pageInfo: { hasMore: false, nextCursor: null } }
    })
    expect(wrapper.findAll('li').length).toBe(1)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('shows cursor error banner on INVALID_CURSOR', async () => {
    const wrapper = mount(Fishs)
    // 直接呼叫內部方法模擬錯誤
    // @ts-ignore
    wrapper.vm.fetchPage({ last_id: 999 })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
  })

  it('shows loading indicator during fetch', async () => {
    // 暫時改寫 router.get 讓 onSuccess 延遲
    const original = router.get
    // @ts-ignore
    router.get = vi.fn((url, params, opts) => {
      setTimeout(() => {
        opts.onSuccess && opts.onSuccess({ props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
      }, 20)
    })

    const wrapper = mount(Fishs, { props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
    // onMounted 會呼叫 fetchPage → 顯示 loading
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="status"]').exists()).toBe(true)

    // 等待回應
    await new Promise((r) => setTimeout(r, 30))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="status"]').exists()).toBe(false)

    // 還原 router.get
    router.get = original
  })

  it('appends second page using nextCursor', async () => {
    // 模擬兩頁：首次回傳 2 筆，hasMore=true，nextCursor=2；第二次帶 last_id=2 回傳 1 筆並 hasMore=false
    const original = router.get
    // @ts-ignore
    router.get = vi.fn((url, params, opts) => {
      if (!params.last_id) {
        opts.onSuccess && opts.onSuccess({ props: { items: [
          { id: 3, name: 'A', image_url: 'a' },
          { id: 2, name: 'B', image_url: 'b' },
        ], pageInfo: { hasMore: true, nextCursor: 2 } } })
      } else if (params.last_id === 2) {
        opts.onSuccess && opts.onSuccess({ props: { items: [
          { id: 1, name: 'C', image_url: 'c' },
        ], pageInfo: { hasMore: false, nextCursor: null } } })
      }
    })

    const wrapper = mount(Fishs, { props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
    await wrapper.vm.$nextTick()

    // 初次 fetchPage 會抓第一頁
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('li').length).toBe(2)

    // 以 nextCursor 續載第二頁
    // @ts-ignore
    await wrapper.vm.fetchPage({ last_id: 2 })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('li').length).toBe(3)

    router.get = original
  })
})
