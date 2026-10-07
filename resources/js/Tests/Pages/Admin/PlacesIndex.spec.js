import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Index from '@/Pages/Admin/Places/Index.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as'] },
  usePage: vi.fn(() => ({
    props: { auth: { user: { name: 'Test Admin' } }, errors: {} },
    url: '/admin/places',
  })),
}))

describe('Admin/Places/Index', () => {
  it('renders the empty state inside the real AdminLayout', () => {
    const wrapper = mount(Index, { props: { places: { data: [] } } })

    expect(wrapper.findComponent(AdminLayout).exists()).toBe(true)
    expect(wrapper.get('[data-testid="places-empty-state"]').text()).toContain('尚無地名')
    expect(wrapper.text()).toContain('地名會在新增或編輯情境時建立')
    expect(wrapper.findAll('a').some(link => link.attributes('href') === '/capture-sessions/create' && link.text() === '新增情境')).toBe(true)
  })

  it('renders place rows and aligned actions inside the real AdminLayout', () => {
    const wrapper = mount(Index, { props: { places: { data: [
      { id: 1, name: '東清灣', tao_name: 'Tao', capture_sessions_count: 2 },
      { id: 2, name: '朗島灣', tao_name: null, capture_sessions_count: 0 },
    ] } } })

    expect(wrapper.findComponent(AdminLayout).exists()).toBe(true)
    expect(wrapper.find('[data-testid="places-empty-state"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('使用中 2 次出海')
    expect(wrapper.text()).toContain('使用中 0 次出海')
    expect(wrapper.findAll('a').filter(link => link.attributes('href') === '/admin/places/2')).toHaveLength(1)
    for (const actions of wrapper.findAll('[data-testid="place-actions"]')) {
      expect(actions.classes()).toEqual(expect.arrayContaining(['flex', 'items-center', 'gap-3']))
      for (const action of actions.findAll('a')) {
        expect(action.classes()).toEqual(expect.arrayContaining(['px-2', 'py-1']))
      }
    }
  })
})
