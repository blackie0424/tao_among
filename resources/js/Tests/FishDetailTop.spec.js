import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishDetailTop from '@/Components/FishDetail/FishDetailTop.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
  usePage: () => ({ props: { auth: { user: null } } }),
}))

vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/UI/LazyImage.vue', () => ({ default: { template: '<img />' } }))

describe('FishDetailTop', () => {
  it('魚名使用長者頁標題字級', () => {
    const wrapper = mount(FishDetailTop, { props: { fish: { id: 1, name: '飛魚' } } })
    expect(wrapper.get('h1').classes()).toContain('text-elder-title')
  })
})

describe('FishDetailTop 博物誌樣式（#132 AC 5）', () => {
  const mountTop = (fish = { id: 1, name: '飛魚', image_url: '/a.jpg' }) =>
    mount(FishDetailTop, { props: { fish } })

  it('魚名為宋體 28px、墨黑字，下方一條細線', () => {
    const wrapper = mountTop()
    const h1 = wrapper.get('h1')

    expect(h1.classes()).toEqual(
      expect.arrayContaining(['font-serif', 'text-elder-title', 'text-elder-text'])
    )
    expect(h1.text()).toBe('飛魚')
    expect(h1.element.parentElement.parentElement.classList).toContain('border-b')
    expect(h1.element.parentElement.parentElement.classList).toContain('border-rule')
  })

  it('照片區為細框，沒有白卡與陰影', () => {
    const html = mountTop().html()

    expect(html).toContain('border-rule-strong')
    expect(html).not.toMatch(/shadow|bg-white|bg-gray-|border-gray-/)
  })

  it('沒有照片網址時仍渲染照片區，魚名照常顯示', () => {
    const wrapper = mountTop({ id: 1, name: '飛魚' })

    expect(wrapper.find('[data-testid="fish-detail-photo"]').exists()).toBe(true)
    expect(wrapper.get('h1').text()).toBe('飛魚')
  })
})
