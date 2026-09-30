import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishCard from '@/Components/FishList/FishCard.vue'

let role = 'viewer'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { user: { role } } } }),
}))

vi.mock('@/Components/UI/ItemCard.vue', () => ({
  default: {
    name: 'ItemCard',
    props: ['href', 'imageUrl', 'title', 'imageStyle', 'audioUrl', 'index'],
    template: '<div data-testid="item-card" />',
  },
}))

const fish = {
  id: 1,
  name: '測試魚',
  image_url: 'https://example.com/fish.jpg',
  audio_url: 'https://example.com/audio.m4a',
}

describe('魚卡發音權限', () => {
  it.each([
    ['guest', null],
    ['viewer', null],
    ['editor', fish.audio_url],
    ['admin', fish.audio_url],
  ])('%s 傳給 ItemCard 的 audioUrl 為 %s', (userRole, expected) => {
    role = userRole

    const wrapper = mount(FishCard, { props: { fish } })

    expect(wrapper.getComponent({ name: 'ItemCard' }).props('audioUrl')).toBe(expected)
  })
})
