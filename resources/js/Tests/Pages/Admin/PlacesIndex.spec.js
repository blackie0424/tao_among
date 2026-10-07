import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Index from '@/Pages/Admin/Places/Index.vue'
vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' }, Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as'] } }))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['title'] } }))
describe('Admin/Places/Index', () => {
  it('shows fixed usage meaning and only deletes unused places', () => {
    const wrapper = mount(Index, { props: { places: { data: [
      { id: 1, name: '東清灣', tao_name: 'Tao', capture_sessions_count: 2 },
      { id: 2, name: '朗島灣', tao_name: null, capture_sessions_count: 0 },
    ] } } })
    expect(wrapper.text()).toContain('使用中 2 次出海')
    expect(wrapper.text()).toContain('使用中 0 次出海')
    expect(wrapper.findAll('a').filter(link => link.attributes('href') === '/admin/places/2')).toHaveLength(1)
  })
})
