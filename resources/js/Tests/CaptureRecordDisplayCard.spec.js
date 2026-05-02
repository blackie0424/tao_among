import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordDisplayCard from '@/Components/CaptureRecord/CaptureRecordDisplayCard.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<img data-testid="lazy-image" :src="src" :alt="alt" />',
    props: ['src', 'alt', 'class'],
  },
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
describe('序號顯示', () => {
  it('應顯示「第 X 筆紀錄」', () => {
    const wrapper = mountCard({ index: 2 })
    expect(wrapper.text()).toContain('第 3 筆紀錄')
  })
})

// ──────────────────────────────────────────────
// 交替色系
// ──────────────────────────────────────────────
describe('交替色系', () => {
  it('偶數 index 時，卡片應含 border-teal-200 class', () => {
    const wrapper = mountCard({ index: 0 })
    expect(wrapper.html()).toContain('border-teal-200')
  })

  it('奇數 index 時，卡片應含 border-slate-200 class', () => {
    const wrapper = mountCard({ index: 1 })
    expect(wrapper.html()).toContain('border-slate-200')
  })
})

// ──────────────────────────────────────────────
// 捕獲時間
// ──────────────────────────────────────────────
describe('捕獲時間', () => {
  it('有 capture_date 時應顯示格式化日期', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_date: '2024-03-05T00:00:00.000Z' }) })
    expect(wrapper.text()).toContain('捕獲時間')
    expect(wrapper.text()).toContain('2024/03/05')
  })

  it('capture_date 為 null 時不應顯示捕獲時間區塊', () => {
    const wrapper = mountCard({ record: makeRecord({ capture_date: null }) })
    expect(wrapper.text()).not.toContain('捕獲時間')
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
