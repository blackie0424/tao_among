import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@/utils/localDate', () => ({ formatLocalDate: vi.fn(() => '2026-10-08') }))
vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn() },
  usePage: () => ({ props: { auth: { user: null }, flash: {} } }),
}))

import CaptureRecordForm from '@/Components/CaptureRecord/CaptureRecordForm.vue'

describe('CaptureRecordForm local date', () => {
  it('uses the browser-local date as the legacy edit date maximum', async () => {
    const wrapper = mount(CaptureRecordForm, {
      props: {
        tribes: ['ivalino'],
        capture_methods: { fishing: '釣魚' },
        fishName: '測試魚',
        record: {
          id: 1,
          session_id: null,
          tribe: 'ivalino',
          location: '測試灣',
          capture_method: 'fishing',
          capture_date: '2026-10-07',
        },
      },
    })
    await wrapper.vm.$nextTick()

    expect(wrapper.get('#capture_date').attributes('max')).toBe('2026-10-08')
  })
})