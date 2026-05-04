import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecords from '@/Pages/CaptureRecords.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: {
    reload: vi.fn(),
  },
}))

describe('CaptureRecords page', () => {
  it('uses the batch create entrypoint for adding capture records', () => {
    const wrapper = mount(CaptureRecords, {
      props: {
        fish: {
          id: 1,
          name: '苦花',
          captureRecords: [],
        },
        tribes: [],
      },
      global: {
        stubs: {
          FishAppLayout: {
            template: '<div><slot /></div>',
          },
          CaptureRecordCard: {
            template: '<div data-testid="capture-record-card" />',
          },
          FabButton: {
            props: ['to', 'label'],
            template: '<a :href="to">{{ label }}</a>',
          },
        },
      },
    })

    expect(wrapper.find('a[href="/fish/1/capture-records/batch-create"]').exists()).toBe(true)
    expect(wrapper.find('a[href="/fish/1/capture-records/create"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('批次新增捕獲紀錄')
  })
})
