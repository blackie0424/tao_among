import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordDisplayCard from '@/Components/CaptureRecord/CaptureRecordDisplayCard.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img data-testid="lazy-image" :src="src" :alt="alt" />',
    props: ['src', 'alt', 'class'],
  },
}))

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))

const makeRecord = (overrides = {}) => ({
  id: 1,
  capture_date: '2024-03-05T00:00:00.000Z',
  location: '朗島',
  tribe: '朗島部落',
  capture_method: '魚槍',
  image_url: 'https://example.com/photo.jpg',
  notes: '很大隻',
  ...overrides,
})

const mountCard = (propsData = {}) =>
  mount(CaptureRecordDisplayCard, {
    props: {
      record: makeRecord(),
      index: 0,
      fishName: '飛魚',
      ...propsData,
    },
  })

// ──────────────────────────────────────────────
// 序號顯示
// ──────────────────────────────────────────────
describe('卡片標題為捕獲日期（#132 AC 9）', () => {
  it('標題顯示格式化的捕獲日期，不再顯示「第 N 筆紀錄」編號', () => {
    const wrapper = mountCard({ index: 2 })
    expect(wrapper.get('[data-testid="capture-card-title"]').text()).toBe('2024/03/05')
    expect(wrapper.text()).not.toMatch(/第\s*\d+\s*筆紀錄/)
  })

  it('不同序號的卡片標題只跟自己的日期有關', () => {
    const a = mountCard({ index: 0, record: makeRecord({ capture_date: '2024-03-05T00:00:00.000Z' }) })
    const b = mountCard({ index: 5, record: makeRecord({ capture_date: '2023-12-31T12:00:00.000Z' }) })
    expect(a.get('[data-testid="capture-card-title"]').text()).toBe('2024/03/05')
    expect(b.get('[data-testid="capture-card-title"]').text()).toBe('2023/12/31')
  })
})

describe('欄位圖示', () => {
  it('四個欄位都以對讀屏隱藏的線條圖示顯示', () => {
    const wrapper = mountCard({ record: makeRecord({ session_notes: '這次出海備註' }) })

    const icons = wrapper.findAll('svg[aria-hidden="true"]')
    expect(icons).toHaveLength(4)
    expect(icons.every((icon) => icon.attributes('fill') === 'none')).toBe(true)
    expect(wrapper.text()).not.toMatch(/[📅📍🎣📝🗒️]/u)
    expect(wrapper.text()).toContain('捕獲地點：')
    expect(wrapper.text()).toContain('捕獲方式：魚槍')
    expect(wrapper.text()).toContain('捕獲說明')
    expect(wrapper.text()).toContain('情境備註')
  })
})

// ──────────────────────────────────────────────
// 交替色系
// ──────────────────────────────────────────────
describe('單一樣式、無交替色（#132 AC 9）', () => {
  it('第 1、2 筆卡片的樣式 class 相同，沒有舊的 teal、slate 與編號圓點', () => {
    const first = mountCard({ index: 0 })
    const second = mountCard({ index: 1 })

    expect(first.classes()).toEqual(second.classes())
    expect(first.classes()).toEqual(expect.arrayContaining(['border', 'border-rule-strong']))
    expect(first.html()).not.toMatch(/teal|slate|rounded-full bg-/)
  })

  it('沒有 Tailwind 預設色與陰影，欄位字級 18px', () => {
    const wrapper = mountCard({ record: makeRecord({ session_notes: '情境' }), fishId: 1 })

    expect(wrapper.html()).not.toMatch(/(?:bg|text|border)-(?:blue|gray|green|teal|slate|emerald|amber)-\d|shadow|bg-white/)
    expect(wrapper.html()).not.toMatch(/text-(?:xs|sm|base)\b/)
  })
})

// ──────────────────────────────────────────────
// 捕獲時間
// ──────────────────────────────────────────────
describe('捕獲時間', () => {
  it('有 capture_date 時應顯示格式化日期', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_date: '2024-03-05T00:00:00.000Z' }) })
    expect(wrapper.text()).toContain('2024/03/05')
  })

  it('capture_date 為 null（資料表必填，防呆）時不顯示標題', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_date: null }) })
    expect(wrapper.find('[data-testid="capture-card-title"]').exists()).toBe(false)
  })
})

// ──────────────────────────────────────────────
// 捕獲地點
// ──────────────────────────────────────────────
describe('捕獲地點', () => {
  it('有 location 時應顯示地點與部落標籤', () => {
    const wrapper = mountCard({ record: makeRecord({ location: '朗島', tribe: '朗島部落' }) })
    expect(wrapper.text()).toContain('捕獲地點')
    expect(wrapper.text()).toContain('朗島部落')
    expect(wrapper.text()).toContain('朗島')
  })

  it('location 為空字串時不應顯示捕獲地點區塊', () => {
    const wrapper = mountCard({ record: makeRecord({ location: '' }) })
    expect(wrapper.text()).not.toContain('捕獲地點')
  })
  it('location 為 null 時不應顯示捕獲地點區塊', () => {
    const wrapper = mountCard({ record: makeRecord({ location: null }) })
    expect(wrapper.text()).not.toContain('捕獲地點')
  })
})

// ──────────────────────────────────────────────
// 捕獲方式
// ──────────────────────────────────────────────
describe('捕獲方式', () => {
  it('有 capture_method 時應顯示', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_method: '魚槍' }) })
    expect(wrapper.text()).toContain('捕獲方式：魚槍')
  })

  it('capture_method 為 null 時不應顯示', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_method: null }) })
    expect(wrapper.text()).not.toContain('捕獲方式')
  })
})

// ──────────────────────────────────────────────
// LazyImage
// ──────────────────────────────────────────────
describe('LazyImage', () => {
  it('應以 fishName 和 index+1 組合 alt 文字', () => {
    const wrapper = mountCard({ fishName: '飛魚', index: 1 })
    const img = wrapper.find('[data-testid="lazy-image"]')
    expect(img.attributes('alt')).toBe('飛魚 捕獲紀錄 2')
  })

  it('應傳入 record.image_url 作為 src', () => {
    const wrapper = mountCard({ record: makeRecord({ image_url: 'https://example.com/x.jpg' }) })
    const img = wrapper.find('[data-testid="lazy-image"]')
    expect(img.attributes('src')).toBe('https://example.com/x.jpg')
  })
})

// ──────────────────────────────────────────────
// 捕獲說明
// ──────────────────────────────────────────────
describe('捕獲說明', () => {
  it('有 notes 時應顯示', () => {
    const wrapper = mountCard({ record: makeRecord({ notes: '很大隻' }) })
    expect(wrapper.text()).toContain('捕獲說明')
    expect(wrapper.text()).toContain('很大隻')
  })

  it('notes 為 null 時不應顯示捕獲說明區塊', () => {
    const wrapper = mountCard({ record: makeRecord({ notes: null }) })
    expect(wrapper.text()).not.toContain('捕獲說明')
  })
})

describe('情境備註', () => {
  it('與捕獲說明分開顯示情境備註', () => {
    const wrapper = mountCard({
      record: makeRecord({ notes: '單筆備註', session_notes: '這次出海備註' }),
    })
    expect(wrapper.text()).toContain('捕獲說明')
    expect(wrapper.text()).toContain('單筆備註')
    expect(wrapper.text()).toContain('情境備註')
    expect(wrapper.text()).toContain('這次出海備註')
  })

  it.each([null, ''])('session_notes 為 %j 時不顯示情境備註', (sessionNotes) => {
    const wrapper = mountCard({ record: makeRecord({ session_notes: sessionNotes }) })
    expect(wrapper.text()).not.toContain('情境備註')
  })
})

describe('魚頁捕獲卡不放編輯入口', () => {
  it.each([null, 17])('情境 %j 的卡片沒有任何連結與編輯字樣', (capture_session_id) => {
    const wrapper = mountCard({ record: makeRecord({ id: 23, capture_session_id }), fishId: 42, canEdit: true })
    expect(wrapper.find('a').exists()).toBe(false)
    expect(wrapper.find('[data-testid="capture-card-footer"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('編輯')
  })
})
