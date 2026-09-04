import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopicShow from '@/Pages/Topic/Show.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    template: '<a :href="href"><slot /></a>',
    props: ['href'],
  },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle'],
  },
}))

describe('Topic/Show.vue', () => {
  it('接受 topic 和 item props 並正確渲染', () => {
    const topic = {
      id: 1,
      title: '魚餌圖鑑',
      slug: 'bait-guide',
    }
    const item = {
      id: 1,
      title: '魚餌項目1',
      description: '這是描述',
      image_url: 'test.jpg',
    }

    const wrapper = mount(TopicShow, {
      props: { topic, item },
    })

    expect(wrapper.text()).toContain('魚餌項目1')
    expect(wrapper.text()).toContain('這是描述')
  })

  it('返回按鈕使用 /topics/ 路由', () => {
    const topic = {
      id: 1,
      title: '魚餌圖鑑',
      slug: 'bait-guide',
    }
    const item = {
      id: 1,
      title: '魚餌項目1',
    }

    const wrapper = mount(TopicShow, {
      props: { topic, item },
    })

    const backLink = wrapper.find('a')
    expect(backLink.attributes('href')).toBe('/topics/bait-guide')
  })
})
