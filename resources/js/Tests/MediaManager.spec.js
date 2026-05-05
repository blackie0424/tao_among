import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MediaManager from '@/Pages/Fish/MediaManager.vue'

const mockPage = {
  props: {
    auth: {
      user: null,
    },
  },
}

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    name: 'Link',
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: {
    put: vi.fn(),
    delete: vi.fn(),
  },
  usePage: () => mockPage,
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
  },
}))

vi.mock('@/Layouts/FishGridLayout.vue', () => ({
  default: {
    template: '<div><slot name="middle" /><slot name="bottom" /></div>',
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img :src="src" :alt="alt" />',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

vi.mock('@/utils/fishListCache', () => ({
  markFishStale: vi.fn(),
}))

const makeFish = (overrides = {}) => ({
  id: 42,
  name: '苦花',
  display_image_url: 'https://example.com/display.jpg',
  image_url: 'https://example.com/image.jpg',
  audios: [],
  display_capture_record_id: null,
  ...overrides,
})

const mountPage = (user = null) => {
  mockPage.props.auth.user = user

  return mount(MediaManager, {
    props: {
      fish: makeFish(),
      captureRecords: [],
    },
  })
}

describe('MediaManager', () => {
  beforeEach(() => {
    mockPage.props.auth.user = null
  })

  it('hides batch create link for viewer users', () => {
    const wrapper = mountPage({ id: 1, role: 'viewer' })

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(false)
  })

  it('shows batch create link for editor users', () => {
    const wrapper = mountPage({ id: 1, role: 'editor' })

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(true)
  })
})
