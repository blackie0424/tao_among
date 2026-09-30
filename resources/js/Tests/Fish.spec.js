import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Fish from '@/Pages/Fish.vue'

// jsdom 缺少 matchMedia，補上 stub
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

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
    name: 'FishAppLayout',
    template: '<div><slot /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText', 'showBottomNav', 'showEditMenu', 'stickyMobile', 'showMobileTitle'],
  },
}))

vi.mock('@/Components/Global/FishEditBar.vue', () => ({
  default: { template: '<div />', props: ['fishId', 'canEdit', 'variant'] },
}))

vi.mock('@/Components/UI/Volume.vue', () => ({
  default: { template: '<div />', props: ['audioUrl'] },
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
// 詳細頁導覽行為
// ──────────────────────────────────────────────
describe('詳細頁導覽行為', () => {
  it('固定返回魚類列表且手機不 sticky、不顯示魚名標題', () => {
    const wrapper = mountFish()
    const layout = wrapper.getComponent({ name: 'FishAppLayout' })
    expect(layout.props()).toMatchObject({
      mobileBackUrl: '/fishs',
      mobileBackText: '魚類列表',
      stickyMobile: false,
      showMobileTitle: false,
    })
  })

  it('分頁列在手機固定 top-0、桌機維持 top-20', () => {
    const wrapper = mountFish()
    const tabBar = wrapper.find('.sticky')
    expect(tabBar.classes()).toContain('top-0')
    expect(tabBar.classes()).toContain('lg:top-20')
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

describe('發音功能依角色顯示', () => {
  it.each([
    ['guest', false],
    ['viewer', false],
    ['editor', true],
    ['admin', true],
  ])('%s 的發音頁籤顯示為 %s', (role, expected) => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { id: 1, role } } } })

    const wrapper = mountFish({ fish: makeFish({ audio_url: 'https://example.com/audio.m4a' }) })

    expect(wrapper.text().includes('發音')).toBe(expected)
  })
})
