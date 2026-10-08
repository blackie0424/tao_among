import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Edit from '@/Pages/Admin/Places/Edit.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  useForm: vi.fn(values => ({ ...values, errors: {}, put: vi.fn() })),
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['title'] } }))

describe('Admin/Places/Edit styles', () => {
  it('gives every input and textarea an explicit border width', () => {
    const wrapper = mount(Edit, {
      props: { place: { id: 1, name: '東清灣', tao_name: 'Iraraley', notes: '' } },
    })

    for (const control of wrapper.findAll('input, textarea')) {
      expect(control.classes()).toContain('border')
    }
    for (const label of wrapper.findAll('label')) {
      expect(label.classes()).toContain('mb-1')
    }
  })
})
