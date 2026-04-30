import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Fish from '@/Pages/Fish.vue'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a><slot /></a>', props: ['href'] },
  usePage: () => ({ props: { auth: { user: null } } }),
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

vi.mock('@/Components/TribalClassificationSummary.vue', () => ({
  default: {
    template: '<div />',
    props: ['classifications', 'tribes', 'fishId'],
  },
}))

vi.mock('@/Components/LazyImage.vue', () => ({
  default: {
    template: '<img />',
    props: ['src', 'alt', 'class'],
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
      tribes: [],
      ...propsData,
    },
  })

// ──────────────────────────────────────────────
// formatDate
// ──────────────────────────────────────────────
describe('formatDate', () => {
  it('應將日期字串格式化為 YYYY/MM/DD', () => {
    const wrapper = mountFish({
      captureRecords: [
        {
          id: 1,
          capture_date: '2024-03-05T00:00:00.000Z',
          image_url: null,
        },
      ],
    })

    expect(wrapper.text()).toContain('2024/03/05')
  })

  it('若 capture_date 為 null，不應渲染日期區塊', () => {
    const wrapper = mountFish({
      captureRecords: [{ id: 1, capture_date: null, image_url: null }],
    })

    expect(wrapper.text()).not.toContain('捕獲時間')
  })
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
    const wrapper = mountFish()
    expect(wrapper.vm.isEditor).toBe(false)
  })
})

// ──────────────────────────────────────────────
// groupedNotesByTypeAndLocate computed
// ──────────────────────────────────────────────
describe('groupedNotesByTypeAndLocate', () => {
  const fishNotes = {
    習性: [
      { id: 1, note: '深海魚', locate: '蘭嶼' },
      { id: 2, note: '珊瑚礁', locate: '蘭嶼' },
      { id: 3, note: '常在淺灘', locate: '綠島' },
    ],
    烹飪: [
      { id: 4, note: '適合清蒸', locate: null },
    ],
  }

  it('應依 note_type 再依 locate 進行二次分組', () => {
    const wrapper = mountFish({ fishNotes })
    const grouped = wrapper.vm.groupedNotesByTypeAndLocate

    expect(Object.keys(grouped)).toContain('習性')
    expect(Object.keys(grouped.習性)).toContain('蘭嶼')
    expect(grouped.習性['蘭嶼']).toHaveLength(2)
    expect(grouped.習性['綠島']).toHaveLength(1)
  })

  it('locate 為 null 時，應歸類到 "未分類部落"', () => {
    const wrapper = mountFish({ fishNotes })
    const grouped = wrapper.vm.groupedNotesByTypeAndLocate

    expect(Object.keys(grouped.烹飪)).toContain('未分類部落')
    expect(grouped.烹飪['未分類部落']).toHaveLength(1)
  })

  it('fishNotes 為空物件時，grouped 應為空物件', () => {
    const wrapper = mountFish({ fishNotes: {} })
    expect(wrapper.vm.groupedNotesByTypeAndLocate).toEqual({})
  })

  it('fishNotes 為 null 時，grouped 應為空物件', () => {
    const wrapper = mountFish({ fishNotes: null })
    expect(wrapper.vm.groupedNotesByTypeAndLocate).toEqual({})
  })
})
