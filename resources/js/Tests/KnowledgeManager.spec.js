import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import KnowledgeManager from '@/Pages/Fish/KnowledgeManager.vue'

const mockUsePage = vi.fn(() => ({ props: { auth: { user: { id: 1, role: 'editor' } } } }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => mockUsePage(),
  router: { delete: vi.fn() },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle', 'activeTab', 'breadcrumbPage', 'mobileBackUrl', 'mobileBackText', 'showBottomNav'],
  },
}))

vi.mock('@/Layouts/FishGridLayout.vue', () => ({
  default: {
    template: '<div><slot name="middle" /><slot name="bottom" /></div>',
    props: ['hideTopOnMobile'],
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img />',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

const mountPage = (propsData = {}) =>
  mount(KnowledgeManager, {
    props: {
      fish: { id: 1, name: '飛魚', image_url: 'https://example.com/fish.jpg' },
      tribalClassifications: [],
      fishNotes: {},
      referenceKnowledge: [],
      tribes: [],
      ...propsData,
    },
  })

describe('KnowledgeManager 文獻知識區塊', () => {
  it('顯示文獻知識標題', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { id: 1, role: 'editor' } } } })
    const wrapper = mountPage()
    expect(wrapper.text()).toContain('文獻知識')
  })

  it('提供文獻知識管理入口', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { id: 1, role: 'editor' } } } })
    const wrapper = mountPage()
    const link = wrapper.findAll('a').find((item) => item.attributes('href') === '/fish/1/reference-knowledge')
    expect(link).toBeTruthy()
  })

  it('viewer 不顯示文獻知識入口', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { id: 1, role: 'viewer' } } } })
    const wrapper = mountPage()
    expect(wrapper.text()).not.toContain('文獻知識')
  })
})
