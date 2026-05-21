import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Fish from '@/Pages/Fish.vue'

const mockUsePage = vi.fn(() => ({ props: { auth: { user: null } } }))

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a><slot /></a>', props: ['href'] },
  usePage: () => mockUsePage(),
}))

// Mock Layout 元件，避免遞迴渲染
vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText', 'showBottomNav'],
  },
}))

vi.mock('@/Layouts/FishGridLayout.vue', () => ({
  default: {
    template: `
      <div>
        <slot name="top-extra" />
        <slot name="middle" />
        <slot name="bottom" />
      </div>
    `,
  },
}))

vi.mock('@/Components/TribalClassification/TribalClassificationSummary.vue', () => ({
  default: {
    template: '<div />',
    props: ['classifications', 'tribes', 'fishId'],
  },
}))

vi.mock('@/Components/CaptureRecord/CaptureRecordSection.vue', () => ({
  default: {
    template: '<div data-testid="capture-record-section" />',
    props: ['captureRecords', 'fishName', 'user'],
  },
}))

vi.mock('@/Components/FishKnowledge/FishAdvancedKnowledgeSection.vue', () => ({
  default: {
    template: '<div data-testid="fish-advanced-knowledge-section" />',
    props: ['fishNotes', 'isEditor', 'user'],
  },
}))

vi.mock('@/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue', () => ({
  default: {
    template: '<div data-testid="reference-knowledge-section" />',
    props: ['referenceKnowledge', 'isEditor', 'user'],
  },
}))

const makeFish = (overrides = {}) => ({
  id: 1,
  name: '鯛魚',
  image: 'tuna.jpg',
  image_url: 'https://example.com/tuna.jpg',
  ...overrides,
})

const mountFish = (propsData = {}) =>
  mount(Fish, {
    props: {
      fish: makeFish(),
      tribalClassifications: [],
      captureRecords: [],
      fishNotes: {},
      referenceKnowledge: [],
      tribes: [],
      ...propsData,
    },
  })

// ──────────────────────────────────────────────
// mobileBackText computed
// ──────────────────────────────────────────────
describe('mobileBackText', () => {
  it('魚名 <= 12 字元時，應回傳 "among no tao"', () => {
    const wrapper = mountFish({
      fish: makeFish({ name: '短名魚' }),
    })
    // FishAppLayout 接收 mobileBackText prop
    const layout = wrapper.findComponent({ name: 'default' })
    expect(wrapper.html()).not.toContain('...')
  })

  it('魚名 > 12 字元時，mobileBackText 應為 "..."', () => {
    const wrapper = mountFish({
      fish: makeFish({ name: '這條魚的名字實在太長超過十二個字元' }),
    })
    // 透過傳給 FishAppLayout 的 prop 確認
    const fishAppLayout = wrapper.findAllComponents({ template: '<div />' })[0]
    // 以 vm 存取 computed 驗證
    expect(wrapper.vm.mobileBackText).toBe('...')
  })

  it('魚名剛好 12 字元時，mobileBackText 應為 "among no tao"', () => {
    const wrapper = mountFish({
      fish: makeFish({ name: '十二個字元魚名OK' }),
    })
    expect(wrapper.vm.mobileBackText).toBe('among no tao')
  })
})

// ──────────────────────────────────────────────
// isEditor computed
// ──────────────────────────────────────────────
describe('isEditor', () => {
  it('user 為 null 時，isEditor 應為 false', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: null } } })
    const wrapper = mountFish()
    expect(wrapper.vm.isEditor).toBe(false)
  })

  it('editor 時應渲染文獻知識區塊', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { id: 1, role: 'editor' } } } })
    const wrapper = mountFish()
    expect(wrapper.find('[data-testid="reference-knowledge-section"]').exists()).toBe(true)
  })
})
