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

  it('渲染四張分類卡並依分類類型產生正確入口', () => {
    const topics = [
      { id: 1, title: '魚類圖鑑', sort_order: 0, is_published: true, is_fish_category: true, image_path: null, image_url: null, items_count: 0 },
      { id: 2, title: '漁獵方法', sort_order: 1, is_published: true, is_fish_category: false, image_path: 'topics/method.jpg', image_url: '/storage/method.jpg', items_count: 3 },
      { id: 3, title: '部落文化', sort_order: 2, is_published: false, is_fish_category: false, image_path: null, image_url: null, items_count: 0 },
      { id: 4, title: '海洋知識', sort_order: 3, is_published: true, is_fish_category: false, image_path: null, image_url: null, items_count: 1 },
    ]

    const wrapper = mount(TopicsIndex, { props: { topics } })
    const cards = wrapper.findAll('[data-testid="topic-card"]')

    expect(cards).toHaveLength(4)
    expect(cards[0].find('[data-testid="topic-primary-link"]').attributes('href')).toBe('/fishs')
    expect(cards[0].text()).toContain('前往前台魚類清單')
    expect(cards[1].find('[data-testid="topic-primary-link"]').attributes('href')).toBe('/admin/topic-items?topic_id=2')
    expect(cards[1].find('a[href="/admin/topics/2/edit"]').exists()).toBe(true)
    expect(cards[0].find('[data-testid="topic-image-placeholder"]').exists()).toBe(true)
    expect(cards[0].find('img').exists()).toBe(false)
    expect(cards[0].text()).toContain('0 筆項目')
    expect(cards[1].find('img').attributes('src')).toBe('/storage/method.jpg')
  })
})
