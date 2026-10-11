import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecords from '@/Pages/CaptureRecords.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: {
    put: vi.fn(),
    delete: vi.fn(),
  },
  usePage: () => ({
    props: {
      auth: {
        user: { id: 1, name: 'Tester' },
      },
    },
  }),
}))

vi.mock('@/utils/fishListCache', () => ({
  markFishStale: vi.fn(),
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
  },
}))

vi.mock('@/Layouts/FishGridLayout.vue', () => ({
  default: {
    template:
      '<div><div data-testid="middle"><slot name="middle" /></div><div data-testid="bottom"><slot name="bottom" /></div></div>',
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img />',
  },
}))

describe('CaptureRecords page after management-page removal', () => {
  it('only shows the batch capture-record create action', () => {
    const wrapper = mount(CaptureRecords, {
      props: {
        fish: {
          id: 1,
          name: '苦花',
          display_capture_record_id: null,
          audios: [],
        },
        captureRecords: [],
      },
      global: {
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.getComponent({ name: 'FabButton' }).props('to')).toBe('/fish/1/capture-records/batch-create')
    expect(wrapper.find('a[href="/fish/1/capture-records/create"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('批次新增')
  })
})
