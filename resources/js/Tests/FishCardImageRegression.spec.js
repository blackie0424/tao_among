import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishCard from '@/Components/FishList/FishCard.vue'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: { role: 'viewer' } } } }) }))
vi.mock('@/Components/UI/ItemCard.vue', () => ({
  default: { name: 'ItemCard', props: ['href', 'imageUrl', 'title', 'framing', 'audioUrl', 'index'], template: '<div />' },
}))

it('FishCard 仍把魚類圖片傳給 ItemCard', () => {
  const wrapper = mount(FishCard, { props: { fish: { id: 7, name: '飛魚', image_url: '/fish.jpg' } } })

  expect(wrapper.getComponent({ name: 'ItemCard' }).props('imageUrl')).toBe('/fish.jpg')
})

it('FishCard 把圖鑑主圖的調整值傳給 ItemCard，沒有調整值時是 null', () => {
  const framing = { x: 30, y: 60, zoom: 1.5, rotation: 90 }
  const adjusted = mount(FishCard, { props: { fish: { id: 7, name: '飛魚', image_url: '/fish.jpg', display_image_framing: framing } } })
  const plain = mount(FishCard, { props: { fish: { id: 8, name: '鬼頭刀', image_url: '/fish2.jpg' } } })

  expect(adjusted.getComponent({ name: 'ItemCard' }).props('framing')).toEqual(framing)
  expect(plain.getComponent({ name: 'ItemCard' }).props('framing')).toBeFalsy()
})
