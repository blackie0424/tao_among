import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Index from '@/Pages/Admin/Places/Index.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as'] },
  usePage: vi.fn(() => ({ props: { auth: { user: { name: 'Test Admin' } }, errors: {} }, url: '/admin/places' })),
}))

describe('Admin/Places/Index', () => {
  it('renders the empty state and fixed zero provisional count', () => {
    const wrapper = mount(Index, { props: { places: { data: [] }, provisionalCount: 0, showProvisional: false } })
    expect(wrapper.findComponent(AdminLayout).exists()).toBe(true)
    expect(wrapper.get('[data-testid="places-empty-state"]').text()).toContain('尚無地名')
    expect(wrapper.text()).toContain('待確認（0）')
    expect(wrapper.get('[data-testid="places-empty-state"]').text()).toContain('按「新增地名」建立，或由田調人員在新增情境時輸入')
    expect(wrapper.find('a[href="/capture-sessions/create"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="places-empty-state"] a').text()).toBe('新增地名')
    expect(wrapper.get('[data-testid="places-empty-state"] a').attributes('href')).toBe('/admin/places/create')
  })

  it('renders tribe shared provisional badge counts and actions', () => {
    const wrapper = mount(Index, { props: { provisionalCount: 1, showProvisional: false, places: { data: [
      { id: 1, tribe: 'ivalino', name: '東清灣', tao_name: 'Tao', is_provisional: true, capture_sessions_count: 2 },
      { id: 2, tribe: null, name: '朗島灣', tao_name: null, is_provisional: false, capture_sessions_count: 0 },
    ] } } })
    expect(wrapper.text()).toContain('待確認（1）')
    expect(wrapper.text()).toContain('ivalino')
    expect(wrapper.text()).toContain('未指定部落')
    expect(wrapper.text()).not.toContain('共用')
    expect(wrapper.text()).toContain('使用中 0 次出海')
    expect(wrapper.find('[data-testid="places-empty-state"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('待確認')
    expect(wrapper.findAll('a').some(link => link.attributes('href') === '/admin/places/1/confirm')).toBe(true)
    expect(wrapper.findAll('a').some(link => link.attributes('href') === '/admin/places/2')).toBe(true)
  })

  it('explains an empty provisional filter without implying the page is broken', () => {
    const wrapper = mount(Index, { props: { places: { data: [] }, provisionalCount: 0, showProvisional: true } })
    expect(wrapper.get('[data-testid="places-empty-state"]').text()).toContain('沒有待確認地名')
    expect(wrapper.text()).toContain('目前所有地名都已確認')
  })
})

it.each([false, true])('offers creation outside the empty state with provisional filter %s', showProvisional => {
  const wrapper = mount(Index, { props: { places: { data: [] }, showProvisional } })
  expect(wrapper.findComponent(AdminLayout).exists()).toBe(true)
  const links = wrapper.findAll('a[href="/admin/places/create"]')
  expect(links.some(link => !link.element.closest('[data-testid="places-empty-state"]'))).toBe(true)
  expect(links[0].text()).toBe('新增地名')
})
