import { computed } from 'vue'

/**
 * useFishNotes — 魚種進階知識分組 composable
 *
 * 職責（SRP）：
 *   - 計算 hasNotes（是否有任何進階知識）
 *   - 計算 groupedNotesByTypeAndLocate（依 note_type → locate 二層分組）
 *
 * @param {import('vue').Ref<Object>} fishNotes  後端傳入的 fishNotes prop ref
 * @returns {{ hasNotes: ComputedRef<boolean>, groupedNotesByTypeAndLocate: ComputedRef<Object> }}
 */
export function useFishNotes(fishNotes) {
  const hasNotes = computed(() => Object.keys(fishNotes.value || {}).length > 0)

  const groupedNotesByTypeAndLocate = computed(() => {
    const result = {}
    for (const [type, notes] of Object.entries(fishNotes.value || {})) {
      result[type] = {}
      for (const note of notes) {
        const locate = note.locate || '未分類部落'
        if (!result[type][locate]) {
          result[type][locate] = []
        }
        result[type][locate].push(note)
      }
    }
    return result
  })

  return { hasNotes, groupedNotesByTypeAndLocate }
}
