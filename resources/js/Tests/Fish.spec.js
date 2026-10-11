import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, enableAutoUnmount } from '@vue/test-utils'
import Fish from '@/Pages/Fish.vue'
import TribalClassificationSummary from '@/Components/TribalClassification/TribalClassificationSummary.vue'
import { nextTick } from 'vue'
import CaptureRecordSection from '@/Components/CaptureRecord/CaptureRecordSection.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'

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

enableAutoUnmount(afterEach)

const mockUsePage = vi.fn(() => ({ props: { auth: { user: null } } }))

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => mockUsePage(),
}))

// Mock Layout 元件，避免遞迴渲染
vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    name: 'FishAppLayout',
    template: '<div><slot /></div>',
    props: [
      'pageTitle',
      'mobileBackUrl',
      'mobileBackText',
      'showBottomNav',
      'stickyMobile',
      'showMobileTitle',
    ],
  },
}))

vi.mock('@/Components/UI/Volume.vue', () => ({
  default: { template: '<div />', props: ['audioUrl'] },
}))

vi.mock('@/Layouts/FishGridLayout.vue', () => ({
  default: {
    template: `
      <div>
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
    attachTo: document.body,
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

describe('地方知識只出現在地方知識分頁', () => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/fish/1')
    mockUsePage.mockReturnValue({ props: { auth: { user: null } } })
  })

  it('基本 section 在有資料或無資料時都不含地方知識摘要', () => {
    for (const tribalClassifications of [[], [{ tribe: '測試部落', food_category: '測試分類' }]]) {
      const wrapper = mountFish({ tribalClassifications, tribes: ['測試部落'] })
      const basic = wrapper.get('[data-testid="basic-tab"]')
      expect(basic.isVisible()).toBe(true)
      expect(Object.keys(wrapper.getComponent(FishGridLayout).vm.$slots)).toEqual([])
      expect(basic.findComponent(TribalClassificationSummary).exists()).toBe(false)
      expect(basic.text()).not.toContain('尚未紀錄')
      wrapper.unmount()
    }
  })

  it('摘要整頁只有一個、保留傳值與 v-show，且支援直接開啟地方知識分頁', async () => {
    const tribalClassifications = [{ tribe: '測試部落', food_category: '測試分類' }]
    const tribes = ['測試部落']
    const wrapper = mountFish({ tribalClassifications, tribes })
    expect(wrapper.findAllComponents(TribalClassificationSummary)).toHaveLength(1)
    const local = wrapper.get('[data-testid="local-tab"]')
    const summary = local.getComponent(TribalClassificationSummary)
    expect(local.isVisible()).toBe(false)
    expect(summary.props()).toEqual({ classifications: tribalClassifications, tribes, fishId: 1 })
    await wrapper
      .findAll('button')
      .find((button) => button.text() === '地方知識')
      .trigger('click')
    expect(local.isVisible()).toBe(true)
    expect(wrapper.get('[data-testid="basic-tab"]').isVisible()).toBe(false)
    expect(local.getComponent(TribalClassificationSummary).vm).toBe(summary.vm)
    expect(new URLSearchParams(window.location.search).get('tab')).toBe('local')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === '基本')
      .trigger('click')
    expect(local.isVisible()).toBe(false)
    expect(local.getComponent(TribalClassificationSummary).vm).toBe(summary.vm)
    wrapper.unmount()

    window.history.replaceState({}, '', '/fish/1?tab=local')
    const direct = mountFish({ tribes })
    await nextTick()
    expect(direct.get('[data-testid="local-tab"]').isVisible()).toBe(true)
    expect(
      direct.get('[data-testid="basic-tab"]').findComponent(TribalClassificationSummary).exists()
    ).toBe(false)
    expect(direct.findAllComponents(TribalClassificationSummary)).toHaveLength(1)
    expect(direct.getComponent(TribalClassificationSummary).props()).toEqual({
      classifications: [],
      tribes,
      fishId: 1,
    })
    direct.unmount()
    window.history.replaceState({}, '', '/fish/1')
  })
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

const expectedActions = [
  [
    'basic',
    '基本',
    [
      ['編輯名稱', '/fish/1/edit'],
      ['管理照片', '/fish/1/media-manager'],
    ],
  ],
  ['local', '地方知識', [['新增地方知識', '/fish/1/tribal-classifications/create']]],
  ['advanced', '進階知識', [['管理進階知識', '/fish/1/knowledge-manager']]],
  ['reference', '文獻知識', [['管理文獻知識', '/fish/1/reference-knowledge']]],
  [
    'capture',
    '捕獲紀錄',
    [
      ['新增捕獲紀錄', '/fish/1/capture-records/batch-create'],
      ['管理捕獲紀錄', '/fish/1/capture-records'],
    ],
  ],
  ['audio', '發音', [['新增發音', '/fish/1/audio/create']]],
]
const readActions = (section) =>
  section
    .get('[data-testid="tab-edit-actions"]')
    .findAll('a')
    .map((a) => [a.text(), a.attributes('href')])

describe.each(['editor', 'admin'])('%s 分頁編輯入口', (role) => {
  beforeEach(() => {
    window.history.replaceState({}, '', '/fish/1')
    mockUsePage.mockReturnValue({ props: { auth: { user: { role } } } })
  })
  it.each(expectedActions)(
    '%s 入口在 section 頂部且文字與目的地正確',
    async (_key, label, expected) => {
      const wrapper = mountFish({ captureRecords: [{ id: 23, image_url: '/test.jpg' }] })
      await wrapper
        .findAll('button')
        .find((b) => b.text() === label)
        .trigger('click')
      const section = wrapper
        .findAll('section')
        .find((s) => s.isVisible() && s.find('[data-testid="tab-edit-actions"]').exists())
      expect(section).toBeTruthy()
      expect(section.element.firstElementChild.dataset.testid).toBe('tab-edit-actions')
      expect(readActions(section)).toEqual(expected)
      if (_key === 'audio')
        expect(section.get('a[href="/fish/1/audio-list"]').text()).toBe('管理所有發音')
      if (_key === 'capture')
        expect(section.get('a[href="/fish/1/capture-records/23/edit"]').text()).toBe('編輯')
    }
  )
})

describe('分頁入口權限與空狀態', () => {
  it.each([null, { role: 'guest' }, { role: 'viewer' }])(
    '%j 的入口與紀錄卡編輯在 DOM 中不存在',
    async (user) => {
      window.history.replaceState({}, '', '/fish/1')
      mockUsePage.mockReturnValue({ props: { auth: { user } } })
      const wrapper = mountFish({ captureRecords: [{ id: 23, image_url: '/test.jpg' }] })
      expect(wrapper.find('[data-testid="tab-edit-actions"]').exists()).toBe(false)
      expect(wrapper.findAll('a')).toHaveLength(0)
      expect(wrapper.findAll('button').map((b) => b.text())).toEqual([
        '基本',
        '地方知識',
        '捕獲紀錄',
      ])
      expect(wrapper.getComponent(CaptureRecordSection).props()).toMatchObject({
        fishId: 1,
        canEdit: false,
      })
      await wrapper
        .findAll('button')
        .find((b) => b.text() === '捕獲紀錄')
        .trigger('click')
      expect(wrapper.text()).not.toContain('編輯')
    }
  )
  it('editor 空資料時五個分頁仍有入口，直接連入捕獲分頁也可用', async () => {
    window.history.replaceState({}, '', '/fish/1?tab=capture')
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountFish()
    await nextTick()
    expect(wrapper.get('a[href="/fish/1/capture-records/batch-create"]').isVisible()).toBe(true)
    for (const [_key, label, expected] of expectedActions.slice(1)) {
      await wrapper
        .findAll('button')
        .find((b) => b.text() === label)
        .trigger('click')
      const section = wrapper
        .findAll('section')
        .find((s) => s.isVisible() && s.find('[data-testid="tab-edit-actions"]').exists())
      expect(readActions(section)).toEqual(expected)
    }
    expect(wrapper.getComponent(CaptureRecordSection).props()).toMatchObject({
      fishId: 1,
      canEdit: true,
    })
  })
  it('魚頁沒有集中式編輯入口', () => {
    mockUsePage.mockReturnValue({ props: { auth: { user: { role: 'editor' } } } })
    const wrapper = mountFish()
    expect(wrapper.text()).not.toContain('編輯這筆資料')
    expect(wrapper.findAll('[data-testid="tab-edit-actions"]')).toHaveLength(6)
    expect(wrapper.find('aside').exists()).toBe(false)
  })
})
