import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishCard from '@/Components/FishList/FishCard.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  usePage: () => ({ props: { auth: { user: null } } }),
}))
vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    props: ['src', 'alt'],
    template: '<img data-testid="item-image" :src="src" :alt="alt" />',
  },
}))
vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<span />' } }))

const mountCard = (fish) => mount(FishCard, { props: { fish: { id: 7, ...fish } } })

describe('FishCard 顯示值', () => {
  it('顯示魚名與照片，連到詳細頁', () => {
    const wrapper = mountCard({ name: '飛魚', image_url: '/fish.jpg' })

    expect(wrapper.get('[data-testid="item-title"]').text()).toBe('飛魚')
    expect(wrapper.get('[data-testid="item-image"]').attributes('src')).toBe('/fish.jpg')
    expect(wrapper.get('a').attributes('href')).toBe('/fish/7')
  })

  it.each([null, undefined, '', '   '])('魚名為 %j 時顯示「未命名」', (name) => {
    const wrapper = mountCard({ name, image_url: '/fish.jpg' })

    expect(wrapper.get('[data-testid="item-title"]').text()).toBe('未命名')
  })

  it('沒有照片時顯示佔位區塊，卡片仍可點', () => {
    const wrapper = mountCard({ name: '飛魚', image_url: null })

    expect(wrapper.find('[data-testid="item-image"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="item-image-placeholder"]').exists()).toBe(true)
    expect(wrapper.get('a').attributes('href')).toBe('/fish/7')
  })
})
