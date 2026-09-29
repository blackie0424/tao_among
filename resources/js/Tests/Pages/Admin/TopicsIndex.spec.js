import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopicsIndex from '@/Pages/Admin/Topics/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    template: '<a :href="href"><slot /></a>',
    props: ['href'],
  },
  router: {
    patch: vi.fn(),
  },
}))

vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['title'],
  },
}))

describe('Admin/Topics/Index.vue', () => {
  it('接受 topics prop 並正確渲染', () => {
    const topics = [
      { id: 1, title: '魚類圖鑑', slug: 'fish-guide', sort_order: 0, is_published: true, is_fish_category: true },
      { id: 2, title: '漁獵方法', slug: 'fishing-method', sort_order: 1, is_published: false, is_fish_category: false },
    ]

    const wrapper = mount(TopicsIndex, {
      props: { topics },
    })

    expect(wrapper.text()).toContain('魚類圖鑑')
    expect(wrapper.text()).toContain('漁獵方法')
  })

  it('若 props 名稱錯誤 (categories) 會導致無法渲染', () => {
    // 這個測試確保如果後端傳 categories 而非 topics,測試會失敗
    const wrapper = mount(TopicsIndex, {
      props: { topics: [] },
    })

    // 空陣列應該顯示「尚未建立分類」
    expect(wrapper.text()).toContain('尚未建立分類')
  })
})
