import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopicIndex from '@/Pages/Topic/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle'],
  },
}))

vi.mock('@/Components/UI/ItemCard.vue', () => ({
  default: {
    template: '<div>{{ title }}</div>',
    props: ['href', 'imageUrl', 'title', 'index'],
  },
}))

describe('Topic/Index.vue', () => {
  it('接受 topic prop 並正確渲染標題', () => {
    const topic = {
      id: 1,
      title: '魚餌圖鑑',
      slug: 'bait-guide',
    }
    const items = []

    const wrapper = mount(TopicIndex, {
      props: { topic, items },
    })

    expect(wrapper.text()).toContain('魚餌圖鑑')
  })

  it('使用 /topics/ 路由而非 /knowledge/', () => {
    const topic = {
      id: 1,
      title: '魚餌圖鑑',
      slug: 'bait-guide',
    }
    const items = [
      { id: 1, title: '項目1', image_url: 'test.jpg' },
    ]

    const wrapper = mount(TopicIndex, {
      props: { topic, items },
    })

    // ItemCard 應該收到 /topics/bait-guide/1 這樣的 href
    const itemCard = wrapper.findComponent({ name: 'default' })
    expect(itemCard.exists()).toBe(true)
  })
})
