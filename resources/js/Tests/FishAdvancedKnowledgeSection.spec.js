import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FishAdvancedKnowledgeSection from '@/Components/FishKnowledge/FishAdvancedKnowledgeSection.vue'

// ────────────────────────────────────────────────────
// Fixtures
// ────────────────────────────────────────────────────

const fishNotesWithData = {
  '食用方式': [
    { id: 1, note: '可以生吃', locate: '朗島部落' },
    { id: 2, note: '也可以煮湯', locate: '朗島部落' },
    { id: 3, note: '乾燥後食用', locate: '野銀部落' },
  ],
  '禁忌': [
    { id: 4, note: '孕婦不可食', locate: '朗島部落' },
  ],
}

const mountSection = (propsData = {}) =>
  mount(FishAdvancedKnowledgeSection, {
    props: {
      fishNotes: {},
      isEditor: false,
      user: null,
      ...propsData,
    },
  })

// ────────────────────────────────────────────────────
// 權限控制
// ────────────────────────────────────────────────────

describe('權限控制', () => {
  it('非 editor 時，整個區塊不應渲染', () => {
    const wrapper = mountSection({ isEditor: false, user: { id: 1 } })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('isEditor=true 且 user=null 且 fishNotes 為空時，區塊不應渲染', () => {
    const wrapper = mountSection({ isEditor: true, user: null, fishNotes: {} })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('isEditor=true 且 user 存在時，即使 fishNotes 為空仍應渲染區塊', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: {} })
    expect(wrapper.html()).not.toBe('<!--v-if-->')
  })

  it('isEditor=true 且 fishNotes 有資料時，應渲染區塊', () => {
    const wrapper = mountSection({ isEditor: true, user: null, fishNotes: fishNotesWithData })
    expect(wrapper.html()).not.toBe('<!--v-if-->')
  })
})

// ────────────────────────────────────────────────────
// 空狀態
// ────────────────────────────────────────────────────

describe('空狀態', () => {
  it('fishNotes 為空時，應顯示「目前沒有進階地方知識的紀錄」', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: {} })
    expect(wrapper.text()).toContain('目前沒有進階地方知識的紀錄')
  })
})

// ────────────────────────────────────────────────────
// 知識分類標題
// ────────────────────────────────────────────────────

describe('知識分類標題', () => {
  it('應顯示「進階知識」標題', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: fishNotesWithData })
    expect(wrapper.text()).toContain('進階知識')
  })

  it('應顯示各 note_type（分類標籤）', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: fishNotesWithData })
    expect(wrapper.text()).toContain('食用方式')
    expect(wrapper.text()).toContain('禁忌')
  })
})

// ────────────────────────────────────────────────────
// 部落分組
// ────────────────────────────────────────────────────

describe('部落分組', () => {
  it('應顯示各 locate（部落標籤）', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: fishNotesWithData })
    expect(wrapper.text()).toContain('朗島部落')
    expect(wrapper.text()).toContain('野銀部落')
  })

  it('locate 為空時，應顯示「未分類部落」', () => {
    const notes = { '食用方式': [{ id: 1, note: '測試筆記', locate: null }] }
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: notes })
    expect(wrapper.text()).toContain('未分類部落')
  })
})

// ────────────────────────────────────────────────────
// 筆記內容
// ────────────────────────────────────────────────────

describe('筆記內容', () => {
  it('應顯示各筆 note 內容', () => {
    const wrapper = mountSection({ isEditor: true, user: { id: 1 }, fishNotes: fishNotesWithData })
    expect(wrapper.text()).toContain('可以生吃')
    expect(wrapper.text()).toContain('也可以煮湯')
    expect(wrapper.text()).toContain('乾燥後食用')
    expect(wrapper.text()).toContain('孕婦不可食')
  })
})
