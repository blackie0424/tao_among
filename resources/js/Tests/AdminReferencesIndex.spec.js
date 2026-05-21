import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminReferencesIndex from '@/Pages/Admin/References/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText'],
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img :src="src" :alt="alt" />',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

const mountPage = (references = []) =>
  mount(AdminReferencesIndex, {
    props: {
      references: {
        data: references,
      },
    },
  })

describe('Admin References Index', () => {
  it('以書籍卡片呈現文獻資料', () => {
    const wrapper = mountPage([
      {
        id: 1,
        name: '海洋植物圖鑑',
        author: '陳作者',
        image_url: 'https://example.com/book.jpg',
        external_url: 'https://example.com/book',
        status: 'enabled',
      },
    ])

    expect(wrapper.findAll('[data-testid="reference-card"]')).toHaveLength(1)
    expect(wrapper.text()).toContain('海洋植物圖鑑')
    expect(wrapper.text()).toContain('陳作者')
    expect(wrapper.find('img[alt="海洋植物圖鑑"]').exists()).toBe(true)
  })

  it('沒有圖片時顯示預設封面提示', () => {
    const wrapper = mountPage([
      {
        id: 1,
        name: '沒有封面的文獻',
        author: '作者甲',
        image_url: null,
        external_url: null,
        status: 'disabled',
      },
    ])

    expect(wrapper.text()).toContain('暫無封面')
  })
})

