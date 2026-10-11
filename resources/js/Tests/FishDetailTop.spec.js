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
