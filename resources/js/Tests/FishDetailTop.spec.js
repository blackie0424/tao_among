import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FishDetailTop from '@/Components/FishDetail/FishDetailTop.vue'

const mockPage = {
  props: {
    auth: {
      user: null,
    },
  },
}

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    props: ['href', 'title'],
    template: '<a :href="href" :title="title"><slot /></a>',
  },
  usePage: () => mockPage,
}))

vi.mock('@/Components/UI/Volume.vue', () => ({
  default: {
    template: '<div data-testid="volume" />',
    props: ['audioUrl'],
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img :src="src" :alt="alt" />',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

const makeFish = (overrides = {}) => ({
  id: 42,
  name: '苦花',
  image_url: 'https://example.com/fish.jpg',
  display_image_url: 'https://example.com/fish-display.jpg',
  audio_url: null,
  ...overrides,
})

const mountComponent = (user = null) => {
  mockPage.props.auth.user = user

  return mount(FishDetailTop, {
    props: {
      fish: makeFish(),
    },
  })
}

describe('FishDetailTop', () => {
  beforeEach(() => {
    mockPage.props.auth.user = null
  })

  it('does not show batch capture record link for guests', () => {
    const wrapper = mountComponent()

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(false)
  })

  it('does not show batch capture record link for viewer users', () => {
    const wrapper = mountComponent({ id: 1, role: 'viewer' })

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(false)
  })

  it('shows batch capture record link for editor users', () => {
    const wrapper = mountComponent({ id: 1, role: 'editor' })

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(true)
  })

  it('shows batch capture record link for admin users', () => {
    const wrapper = mountComponent({ id: 1, role: 'admin' })

    expect(wrapper.find('a[href="/fish/42/capture-records/batch-create"]').exists()).toBe(true)
  })
})
