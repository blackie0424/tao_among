import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordSection from '@/Components/CaptureRecordSection.vue'

vi.mock('@/Components/CaptureRecordDisplayCard.vue', () => ({
  default: {
    template: '<div data-testid="capture-record-display-card" />',
    props: ['record', 'index', 'fishName'],
  },
}))

const makeRecords = (n = 2) =>
  Array.from({ length: n }, (_, i) => ({ id: i + 1, capture_date: '2024-01-01' }))

const mountSection = (propsData = {}) =>
  mount(CaptureRecordSection, {
    props: {
      captureRecords: [],
      fishName: '飛魚',
      user: null,
      ...propsData,
    },
  })

// ────────────────────────────────────────────────────
// 顯示條件
// ────────────────────────────────────────────────────

describe('顯示條件', () => {
  it('captureRecords 為空且 user 為 null 時，不應渲染', () => {
    const wrapper = mountSection()
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('captureRecords 為空但 user 存在時，應渲染（允許新增狀態）', () => {
    const wrapper = mountSection({ user: { id: 1 } })
    expect(wrapper.html()).not.toBe('<!--v-if-->')
  })

  it('captureRecords 有資料時，應渲染', () => {
    const wrapper = mountSection({ captureRecords: makeRecords(1) })
    expect(wrapper.html()).not.toBe('<!--v-if-->')
  })
})

// ────────────────────────────────────────────────────
// 標題與計數
// ────────────────────────────────────────────────────

describe('標題與計數', () => {
  it('應顯示「捕獲紀錄」標題', () => {
    const wrapper = mountSection({ captureRecords: makeRecords(3) })
    expect(wrapper.text()).toContain('捕獲紀錄')
  })

  it('應顯示正確的筆數', () => {
    const wrapper = mountSection({ captureRecords: makeRecords(3) })
    expect(wrapper.text()).toContain('3')
  })

  it('captureRecords 為空時，計數應顯示 0', () => {
    const wrapper = mountSection({ user: { id: 1 }, captureRecords: [] })
    expect(wrapper.text()).toContain('0')
  })
})

// ────────────────────────────────────────────────────
// 卡片渲染
// ────────────────────────────────────────────────────

describe('卡片渲染', () => {
  it('有紀錄時，應渲染對應數量的 CaptureRecordDisplayCard', () => {
    const wrapper = mountSection({ captureRecords: makeRecords(3) })
    expect(wrapper.findAll('[data-testid="capture-record-display-card"]').length).toBe(3)
  })

  it('captureRecords 為空時，不渲染 CaptureRecordDisplayCard', () => {
    const wrapper = mountSection({ user: { id: 1 }, captureRecords: [] })
    expect(wrapper.findAll('[data-testid="capture-record-display-card"]').length).toBe(0)
  })
})
