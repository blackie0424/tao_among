import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopicItemsIndex from '@/Pages/Admin/TopicItems/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    template: '<a :href="href"><slot /></a>',
    props: ['href'],
  },
  router: {
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['title'],
  },
}))

describe('Admin/TopicItems/Index.vue', () => {
  it('接受 topics 和 selectedTopicId props 並正確渲染', () => {
    const topics = [
      { id: 1, title: '魚類圖鑑' },
      { id: 2, title: '漁獵方法' },
    ]
    const items = {
      data: [
        { id: 1, title: '項目1', topic: { title: '魚類圖鑑' }, sort_order: 0, is_published: true },
      ],
      current_page: 1,
      last_page: 1,
    }

    const wrapper = mount(TopicItemsIndex, {
      props: {
        topics,
        items,
        selectedTopicId: 1,
      },
    })

    expect(wrapper.text()).toContain('魚類圖鑑')
    expect(wrapper.text()).toContain('漁獵方法')
    expect(wrapper.text()).toContain('項目1')
  })

  it('使用 topic_id 作為 query 參數', () => {
    const topics = [{ id: 1, title: '魚類圖鑑' }]
    const items = { data: [], current_page: 1, last_page: 1 }

    const wrapper = mount(TopicItemsIndex, {
      props: { topics, items, selectedTopicId: null },
    })

    // 檢查連結是否使用 topic_id
    const links = wrapper.findAll('a')
    const topicLink = links.find(l => l.attributes('href')?.includes('topic_id=1'))
    expect(topicLink).toBeTruthy()
  })
})
