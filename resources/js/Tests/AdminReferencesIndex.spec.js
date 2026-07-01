import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminReferencesIndex from '@/Pages/Admin/References/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => ({ props: { auth: { user: { name: '管理員' } } }, url: '/admin/references' }),
}))

vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['title'],
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<div :class="wrapperClass"><img :src="src" :alt="alt" :class="imgClass" /></div>',
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
    expect(wrapper.find('[data-testid="reference-cover"]').classes()).toContain('w-1/2')
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
