import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@/utils/localDate', () => ({
  formatLocalDate: vi.fn(() => '2026-10-08'),
}))
vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn() },
  usePage: () => ({ props: { auth: { user: null }, flash: {} } }),
}))

import CaptureRecordForm from '@/Components/CaptureRecord/CaptureRecordForm.vue'

describe('CaptureRecordForm local date', () => {
  it('uses the shared browser-local date for the existing flow maximum', async () => {
    const wrapper = mount(CaptureRecordForm, {
      props: {
        tribes: ['ivalino'],
        capture_methods: { fishing: '釣魚' },
        fishName: '測試魚',
      },
    })
    wrapper.vm.setPrefillImage('prefill.jpg')
    await wrapper.vm.$nextTick()

    expect(wrapper.get('#capture_date').attributes('max')).toBe('2026-10-08')
  })
})
