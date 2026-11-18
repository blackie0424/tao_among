import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishDetailTop from '@/Components/FishDetail/FishDetailTop.vue'

const mockUsePage = vi.hoisted(() => vi.fn(() => ({ props: { auth: { user: null } } })))

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  usePage: () => mockUsePage(),
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
  afterEach(() => mockUsePage.mockReturnValue({ props: { auth: { user: null } } }))

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

  it('長魚名（無空白長字串）可換行：魚名容器可收縮、h1 可斷行，發音鈕不被擠出', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountTop({
      id: 1,
      name: 'Pseudocheilinus_hexataenia_extraordinarily_long_name',
      audio_url: '/a.mp3',
    })
    const h1 = wrapper.get('h1')

    expect(h1.classes()).toEqual(expect.arrayContaining(['min-w-0', 'break-words']))
    expect(h1.element.parentElement.classList).toContain('min-w-0')
    expect(h1.text()).toBe('Pseudocheilinus_hexataenia_extraordinarily_long_name')
  })

  it('新增錄音的純圖示連結有名稱，圖示標為裝飾', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountTop()
    const link = wrapper.get('a[href="/fish/1/audio/create"]')

    expect(link.attributes('aria-label')).toBe('新增錄音')
    expect(link.get('svg').attributes('aria-hidden')).toBe('true')
  })

  it('沒有照片網址時仍渲染照片區，魚名照常顯示', () => {
    const wrapper = mountTop({ id: 1, name: '飛魚' })

    expect(wrapper.find('[data-testid="fish-detail-photo"]').exists()).toBe(true)
    expect(wrapper.get('h1').text()).toBe('飛魚')
  })
})
