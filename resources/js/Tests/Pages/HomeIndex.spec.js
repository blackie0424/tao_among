import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeIndex from '@/Pages/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: { visit: vi.fn() },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    name: 'FishAppLayout',
    template: '<main><slot /></main>',
    props: ['showHeader', 'pageTitle'],
  },
}))

async function mountWithCategory(category) {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
    ok: true,
    json: vi.fn().mockResolvedValue([category]),
  }))

  const wrapper = mount(HomeIndex, { props: { slides: [] } })
  await flushPromises()
  return wrapper
}

describe('首頁知識分類卡片', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('有 image_url 時渲染 16:9 圖片與標題', async () => {
    const wrapper = await mountWithCategory({
      id: 1,
      title: '魚類圖鑑',
      image_url: '/images/fishes.jpg',
    })

    expect(wrapper.get('img[alt="魚類圖鑑"]').attributes('src')).toBe('/images/fishes.jpg')
    expect(wrapper.find('.aspect-video').exists()).toBe(true)
    expect(wrapper.text()).toContain('魚類圖鑑')

    wrapper.unmount()
  })

  it('image_url 為 null 時只渲染標題', async () => {
    const wrapper = await mountWithCategory({
      id: 2,
      title: '魚類圖鑑',
      image_url: null,
    })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.aspect-video').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('無圖片')
    expect(wrapper.text()).toContain('魚類圖鑑')

    wrapper.unmount()
  })

  it('image_url 為空字串時只渲染標題', async () => {
    const wrapper = await mountWithCategory({
      id: 3,
      title: '潮間帶生物',
      image_url: '',
    })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.aspect-video').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('無圖片')
    expect(wrapper.text()).toContain('潮間帶生物')

    wrapper.unmount()
  })
})
