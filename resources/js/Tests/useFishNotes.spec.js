import { describe, it, expect } from 'vitest'
import { ref, isRef } from 'vue'
import { useFishNotes } from '@/composables/useFishNotes.js'

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

// ────────────────────────────────────────────────────
// 回傳值結構
// ────────────────────────────────────────────────────

describe('回傳值結構', () => {
  it('應回傳 hasNotes 與 groupedNotesByTypeAndLocate', () => {
    const { hasNotes, groupedNotesByTypeAndLocate } = useFishNotes(ref({}))
    expect(isRef(hasNotes)).toBe(true)
    expect(isRef(groupedNotesByTypeAndLocate)).toBe(true)
  })
})

// ────────────────────────────────────────────────────
// hasNotes
// ────────────────────────────────────────────────────

describe('hasNotes', () => {
  it('fishNotes 為空物件時，hasNotes 應為 false', () => {
    const { hasNotes } = useFishNotes(ref({}))
    expect(hasNotes.value).toBe(false)
  })

  it('fishNotes 有資料時，hasNotes 應為 true', () => {
    const { hasNotes } = useFishNotes(ref(fishNotesWithData))
    expect(hasNotes.value).toBe(true)
  })

  it('fishNotes 為 null 時，hasNotes 應為 false', () => {
    const { hasNotes } = useFishNotes(ref(null))
    expect(hasNotes.value).toBe(false)
  })
})

// ────────────────────────────────────────────────────
// groupedNotesByTypeAndLocate
// ────────────────────────────────────────────────────

describe('groupedNotesByTypeAndLocate', () => {
  it('fishNotes 為空物件時，應回傳空物件', () => {
    const { groupedNotesByTypeAndLocate } = useFishNotes(ref({}))
    expect(groupedNotesByTypeAndLocate.value).toEqual({})
  })

  it('fishNotes 為 null 時，應回傳空物件', () => {
    const { groupedNotesByTypeAndLocate } = useFishNotes(ref(null))
    expect(groupedNotesByTypeAndLocate.value).toEqual({})
  })

  it('應依 note_type 進行第一層分組', () => {
    const { groupedNotesByTypeAndLocate } = useFishNotes(ref(fishNotesWithData))
    expect(Object.keys(groupedNotesByTypeAndLocate.value)).toContain('食用方式')
    expect(Object.keys(groupedNotesByTypeAndLocate.value)).toContain('禁忌')
  })

  it('應依 locate 進行第二層分組', () => {
    const { groupedNotesByTypeAndLocate } = useFishNotes(ref(fishNotesWithData))
    const 食用方式 = groupedNotesByTypeAndLocate.value['食用方式']
    expect(Object.keys(食用方式)).toContain('朗島部落')
    expect(Object.keys(食用方式)).toContain('野銀部落')
    expect(食用方式['朗島部落']).toHaveLength(2)
    expect(食用方式['野銀部落']).toHaveLength(1)
  })

  it('locate 為 null 時，應歸類到「未分類部落」', () => {
    const notes = { '食用方式': [{ id: 1, note: '測試', locate: null }] }
    const { groupedNotesByTypeAndLocate } = useFishNotes(ref(notes))
    expect(Object.keys(groupedNotesByTypeAndLocate.value['食用方式'])).toContain('未分類部落')
  })
})

// ────────────────────────────────────────────────────
// 響應性（fishNotes ref 更新後結果應跟著更新）
// ────────────────────────────────────────────────────

describe('響應性', () => {
  it('fishNotes ref 更新後，hasNotes 應跟著變化', () => {
    const fishNotes = ref({})
    const { hasNotes } = useFishNotes(fishNotes)
    expect(hasNotes.value).toBe(false)

    fishNotes.value = fishNotesWithData
    expect(hasNotes.value).toBe(true)
  })
})
