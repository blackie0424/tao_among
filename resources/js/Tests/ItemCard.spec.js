import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ItemCard from '@/Components/UI/ItemCard.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))
vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: { props: ['src', 'alt'], template: '<img data-testid="item-image" :src="src" :alt="alt" />' },
}))
vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<button />' } }))

describe('ItemCard optional image', () => {
  it('有圖時渲染圖片與標題', () => {
    const wrapper = mount(ItemCard, { props: { href: '/item/1', imageUrl: '/image.jpg', title: '飛魚' } })

    expect(wrapper.get('[data-testid="item-image"]').attributes('src')).toBe('/image.jpg')
    expect(wrapper.get('[data-testid="item-image-wrapper"]').classes()).toContain('h-[170px]')
    expect(wrapper.text()).toContain('飛魚')
  })

  it.each([null, '', undefined])('圖片為 %s 時渲染等高灰底區塊且沒有圖片', (imageUrl) => {
    const props = { href: '/item/1', title: '純文字項目' }
    if (imageUrl !== undefined) props.imageUrl = imageUrl
    const wrapper = mount(ItemCard, { props })

    const placeholder = wrapper.get('[data-testid="item-image-placeholder"]')
    expect(placeholder.classes()).toContain('h-[170px]')
    expect(placeholder.classes()).toContain('bg-gray-100')
    expect(placeholder.attributes('aria-hidden')).toBe('true')
    expect(wrapper.find('[data-testid="item-image"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('純文字項目')
  })
})
