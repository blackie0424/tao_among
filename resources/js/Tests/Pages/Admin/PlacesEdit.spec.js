import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Edit from '@/Pages/Admin/Places/Edit.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  useForm: vi.fn(values => ({ ...values, errors: {}, put: vi.fn(), post: vi.fn() })),
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['title'] } }))

describe('Admin/Places/Edit', () => {
  const props = {
    place: { id: 1, tribe: null, name: '東清灣', tao_name: 'Iraraley', notes: '', is_provisional: true },
    tribes: ['ivalino', 'yayo'],
    mergeTargets: [{ id: 2, tribe: 'ivalino', name: '正式地名' }],
  }

  it('renders tribe shared state confirmation meaning and merge targets', () => {
    const wrapper = mount(Edit, { props })
    expect(wrapper.text()).not.toContain('共用')
    expect(wrapper.get('select').element.selectedIndex).toBe(0)
    expect(wrapper.get('option').text()).toBe('請選擇部落')
    expect(wrapper.get('option').element.disabled).toBe(true)
    expect(wrapper.text()).toContain('儲存並確認')
    expect(wrapper.text()).toContain('ivalino｜正式地名')
    expect(wrapper.text()).toContain('併入既有地名')
  })

  it('gives every input textarea and select an explicit border width', () => {
    const wrapper = mount(Edit, { props })
    for (const control of wrapper.findAll('input, textarea, select')) expect(control.classes()).toContain('border')
  })
})

it('labels null tribe merge targets as unspecified', () => {
  const wrapper = mount(Edit, { props: { place: { id: 1, tribe: 'yayo', name: 'Test' }, tribes: ['yayo'], mergeTargets: [{ id: 2, tribe: null, name: 'ZZPLACEMARK' }] } })
  expect(wrapper.text()).toContain('未指定部落｜ZZPLACEMARK')
  expect(wrapper.text()).not.toContain('共用')
})
