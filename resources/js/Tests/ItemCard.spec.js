import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ItemCard from '@/Components/UI/ItemCard.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))
vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    props: ['src', 'alt'],
    emits: ['failed'],
    template:
      '<img data-testid="item-image" :src="src" :alt="alt" @error="$emit(\'failed\')" />',
  },
}))
vi.mock('@/Components/UI/Volume.vue', () => ({
  default: { template: '<span role="button" aria-label="播放發音" />' },
}))

describe('ItemCard optional image', () => {
  it('有發音時只顯示具讀屏名稱的發音按鈕', () => {
    const wrapper = mount(ItemCard, {
      props: { href: '/item/1', title: '飛魚', audioUrl: '/audio/flying-fish.mp3' },
    })

    expect(wrapper.get('[role="button"]').attributes('aria-label')).toBe('播放發音')
    expect(wrapper.text()).not.toContain('聽發音')
  })

  it.each([null, '', undefined])('沒有發音時不顯示發音按鈕：%s', (audioUrl) => {
    const props = { href: '/item/1', title: '飛魚' }
    if (audioUrl !== undefined) props.audioUrl = audioUrl

    expect(mount(ItemCard, { props }).find('[role="button"]').exists()).toBe(false)
  })

  it('有圖時渲染圖片與標題', () => {
    const wrapper = mount(ItemCard, {
      props: { href: '/item/1', imageUrl: '/image.jpg', title: '飛魚' },
    })

    expect(wrapper.get('[data-testid="item-image"]').attributes('src')).toBe('/image.jpg')
    expect(wrapper.get('[data-testid="item-image-wrapper"]').classes()).toContain('h-[190px]')
    expect(wrapper.text()).toContain('飛魚')
  })

  it.each([null, '', undefined])('圖片為 %s 時渲染等高灰底區塊且沒有圖片', (imageUrl) => {
    const props = { href: '/item/1', title: '純文字項目' }
    if (imageUrl !== undefined) props.imageUrl = imageUrl
    const wrapper = mount(ItemCard, { props })

    const placeholder = wrapper.get('[data-testid="item-image-placeholder"]')
    expect(placeholder.classes()).toContain('h-[190px]')
    expect(placeholder.classes()).toContain('bg-rule')
    expect(placeholder.attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('[data-testid="item-image"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('純文字項目')
  })

  it('照片載入失敗時改顯示佔位區塊，標題仍在', async () => {
    const wrapper = mount(ItemCard, {
      props: { href: '/item/1', imageUrl: '/broken.jpg', title: '飛魚' },
    })

    await wrapper.get('[data-testid="item-image"]').trigger('error')

    expect(wrapper.find('[data-testid="item-image"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="item-image-placeholder"]').classes()).toContain('h-[190px]')
    expect(wrapper.text()).toContain('飛魚')
  })

  it('不用卡片陰影，魚名用宋體 22px 且下方有細線', () => {
    const wrapper = mount(ItemCard, { props: { href: '/item/1', title: '飛魚' } })

    expect(wrapper.html()).not.toContain('shadow')
    const name = wrapper.get('[data-testid="item-title"]')
    expect(name.classes()).toEqual(expect.arrayContaining(['font-serif', 'text-elder-name']))
    expect(name.element.closest('.border-b')).not.toBeNull()
  })
})
