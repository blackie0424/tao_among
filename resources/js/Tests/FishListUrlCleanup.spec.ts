import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Fishs from '../Pages/Fishs.vue'
import { router } from '@inertiajs/vue3'

// 模擬 Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn((url, params, opts) => {
      opts.onSuccess && opts.onSuccess({ props: { items: [
        { id: 5, name: 'Fish5', image_url: 'x' },
        { id: 4, name: 'Fish4', image_url: 'y' },
      ], pageInfo: { hasMore: true, nextCursor: 4 } } })
    })
  },
  Head: { name: 'Head', render: () => null }
}))

describe('Fishs URL cleanup after pagination', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // @ts-ignore
    class IOStub { constructor(cb:any){ this.cb = cb } observe(){} disconnect(){} unobserve(){} fire(isIntersecting=true){ this.cb([{isIntersecting}]) } }
    // @ts-ignore
    global.IntersectionObserver = IOStub
    // 設定初始 URL（含 last_id/perPage）
    window.history.replaceState(null, '', '/fishs?last_id=12&perPage=20')
  })

  it('removes last_id & perPage from URL after successful fetch', async () => {
    const wrapper = mount(Fishs, { props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
    await wrapper.vm.$nextTick()

    // 模擬第二頁載入
    // @ts-ignore
    await wrapper.vm.fetchPage({ last_id: 5 })
    await wrapper.vm.$nextTick()

    expect(window.location.search.includes('last_id')).toBe(false)
    expect(window.location.search.includes('perPage')).toBe(false)
  })

  it('initial mount with last_id cleans URL and triggers fresh search', async () => {
    // Router mock to detect first call after cleanup
    // @ts-ignore
    router.get.mockImplementationOnce((url, params, opts) => {
      opts.onSuccess && opts.onSuccess({ props: { items: [
        { id: 9, name: 'Fish9', image_url: 'a' },
        { id: 8, name: 'Fish8', image_url: 'b' },
      ], pageInfo: { hasMore: true, nextCursor: 8 } } })
    })

    window.history.replaceState(null, '', '/fishs?last_id=99&perPage=20&tribe=iratay')
    const wrapper = mount(Fishs, { props: { items: [], pageInfo: { hasMore: false, nextCursor: null } } })
    await wrapper.vm.$nextTick()

    expect(window.location.search.includes('last_id')).toBe(false)
    expect(window.location.search.includes('perPage')).toBe(false)
    // 保留其他篩選參數（例如 tribe）
    expect(window.location.search.includes('tribe=iratay')).toBe(true)
    expect(router.get).toHaveBeenCalled()
    const firstCallParams = (router.get as any).mock.calls[0][1]
    expect(firstCallParams.last_id).toBeUndefined()
  })
})
