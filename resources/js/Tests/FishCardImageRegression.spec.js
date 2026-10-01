import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishCard from '@/Components/FishList/FishCard.vue'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { role: 'viewer' } } } }) }))
vi.mock('@/Components/UI/ItemCard.vue', () => ({
  default: { name: 'ItemCard', props: ['href', 'imageUrl', 'title', 'imageStyle', 'audioUrl', 'index'], template: '<div />' },
}))

it('FishCard 仍把魚類圖片傳給 ItemCard', () => {
  const wrapper = mount(FishCard, { props: { fish: { id: 7, name: '飛魚', image_url: '/fish.jpg' } } })

  expect(wrapper.getComponent({ name: 'ItemCard' }).props('imageUrl')).toBe('/fish.jpg')
})
