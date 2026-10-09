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
    expect(wrapper.findAll('option').some(option => option.text() === '共用')).toBe(true)
    expect(wrapper.text()).toContain('儲存並確認')
    expect(wrapper.text()).toContain('ivalino｜正式地名')
    expect(wrapper.text()).toContain('併入既有地名')
  })

  it('gives every input textarea and select an explicit border width', () => {
    const wrapper = mount(Edit, { props })
    for (const control of wrapper.findAll('input, textarea, select')) expect(control.classes()).toContain('border')
  })
})
