import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Fishs from '../Pages/Fishs.vue'
import { router } from '@inertiajs/vue3'

// 模擬 Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn((url, params, opts) => {
      opts.onSuccess &&
        opts.onSuccess({ props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
    }),
  },
  Head: { name: 'Head', render: () => null },
  Link: { name: 'Link', template: '<a><slot /></a>' },
}))

describe('Fishs filters wiring', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // 提供 IntersectionObserver mock
    // @ts-ignore
    class IOStub {
      cb: any
      constructor(cb: any) {
        this.cb = cb
      }
      observe(el: any) {}
      disconnect() {}
      unobserve() {}
      fire(isIntersecting = true) {
        this.cb([{ isIntersecting }])
      }
    }
    // @ts-ignore
    global.IntersectionObserver = IOStub
  })

  it('performSearch includes all filter params', async () => {
    const wrapper = mount(Fishs, {
      props: {
        items: [{ id: 999, name: 'Seed', image_url: 'x' }],
        pageInfo: { hasMore: false, nextCursor: null },
      },
    })

    // 設定多條件
    // @ts-ignore
    wrapper.vm.currentFilters = {
      name: 'tuna',
      tribe: 'ivalino',
      food_category: 'oyod',
      processing_method: '去魚鱗',
      capture_location: '朗島',
      capture_method: '網捕',
    }
    // @ts-ignore
    wrapper.vm.nameQuery = 'tuna'

    // @ts-ignore
    wrapper.vm.performSearch()
    await wrapper.vm.$nextTick()

    expect(router.get).toHaveBeenCalled()
    const call = (router.get as any).mock.calls[0]
    expect(call[0]).toBe('/fishs')
    expect(call[1]).toEqual(
      expect.objectContaining({
        name: 'tuna',
        tribe: 'ivalino',
        food_category: 'oyod',
        processing_method: '去魚鱗',
        capture_location: '朗島',
        capture_method: '網捕',
        perPage: 20,
      })
    )
  })

  it('pagination preserves filters across pages', async () => {
    const wrapper = mount(Fishs, {
      props: {
        items: [{ id: 100, name: 'Seed', image_url: 'x' }],
        pageInfo: { hasMore: true, nextCursor: 100 },
      },
    })

    // 設定多條件
    // @ts-ignore
    wrapper.vm.currentFilters = {
      name: 'mackerel',
      tribe: 'iratay',
      food_category: 'rahet',
      processing_method: '剝皮',
      capture_location: '東清',
      capture_method: '釣魚',
    }
    // @ts-ignore
    wrapper.vm.nameQuery = 'mackerel'

    // 模擬載入下一頁
    // @ts-ignore
    await wrapper.vm.fetchPage({ last_id: 42 })
    await wrapper.vm.$nextTick()

    expect(router.get).toHaveBeenCalled()
    const call = (router.get as any).mock.calls[(router.get as any).mock.calls.length - 1]
    expect(call[0]).toBe('/fishs')
    expect(call[1]).toEqual(
      expect.objectContaining({
        last_id: 42,
        name: 'mackerel',
        tribe: 'iratay',
        food_category: 'rahet',
        processing_method: '剝皮',
        capture_location: '東清',
        capture_method: '釣魚',
        perPage: 20,
      })
    )
  })
})
