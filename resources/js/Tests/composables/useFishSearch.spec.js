import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useFishSearch } from '@/composables/useFishSearch'

const makeFilters = (overrides = {}) =>
  ref({
    name: '',
    tribe: '',
    food_category: '',
    processing_method: '',
    capture_location: '',
    without_audio: '',
    ...overrides,
  })

describe('useFishSearch', () => {
  let currentFilters
  let nameQuery
  let onSearch

  beforeEach(() => {
    currentFilters = makeFilters()
    nameQuery = ref('')
    onSearch = vi.fn()
  })

  // ─── appliedFilters ───────────────────────────────────────────────────────

  describe('appliedFilters', () => {
    it('無任何篩選條件時回傳空陣列', () => {
      const { appliedFilters } = useFishSearch(currentFilters, nameQuery, onSearch)
      expect(appliedFilters.value).toEqual([])
    })

    it('有 tribe 時產生 chip', () => {
      currentFilters = makeFilters({ tribe: '阿美族' })
      const { appliedFilters } = useFishSearch(currentFilters, nameQuery, onSearch)
      expect(appliedFilters.value).toContainEqual({ key: 'tribe', label: '部落', value: '阿美族' })
    })

    it('有 nameQuery 時產生 name chip', () => {
      nameQuery = ref('飛魚')
      const { appliedFilters } = useFishSearch(currentFilters, nameQuery, onSearch)
      expect(appliedFilters.value).toContainEqual({ key: 'name', label: '名稱', value: '飛魚' })
    })

    it('without_audio 為 truthy 時產生 chip', () => {
      currentFilters = makeFilters({ without_audio: 1 })
      const { appliedFilters } = useFishSearch(currentFilters, nameQuery, onSearch)
      expect(appliedFilters.value).toContainEqual({
        key: 'without_audio',
        label: '音檔',
        value: '尚無音檔',
      })
    })

    it('同時有多個篩選條件時全部回傳', () => {
      currentFilters = makeFilters({ tribe: '阿美族', food_category: '白肉魚' })
      nameQuery = ref('飛魚')
      const { appliedFilters } = useFishSearch(currentFilters, nameQuery, onSearch)
      expect(appliedFilters.value).toHaveLength(3)
    })
  })

  // ─── removeFilter ─────────────────────────────────────────────────────────

  describe('removeFilter', () => {
    it('移除 name 時清空 nameQuery 並呼叫 onSearch', () => {
      nameQuery = ref('飛魚')
      const { removeFilter } = useFishSearch(currentFilters, nameQuery, onSearch)
      removeFilter('name')
      expect(nameQuery.value).toBe('')
      expect(onSearch).toHaveBeenCalledOnce()
    })

    it('移除 without_audio 時清空欄位並呼叫 onSearch', () => {
      currentFilters = makeFilters({ without_audio: 1 })
      const { removeFilter } = useFishSearch(currentFilters, nameQuery, onSearch)
      removeFilter('without_audio')
      expect(currentFilters.value.without_audio).toBe('')
      expect(onSearch).toHaveBeenCalledOnce()
    })

    it('移除已知篩選 key 時清空欄位並呼叫 onSearch', () => {
      currentFilters = makeFilters({ tribe: '阿美族' })
      const { removeFilter } = useFishSearch(currentFilters, nameQuery, onSearch)
      removeFilter('tribe')
      expect(currentFilters.value.tribe).toBe('')
      expect(onSearch).toHaveBeenCalledOnce()
    })

    it('移除不存在的 key 時仍呼叫 onSearch', () => {
      const { removeFilter } = useFishSearch(currentFilters, nameQuery, onSearch)
      removeFilter('unknown_key')
      expect(onSearch).toHaveBeenCalledOnce()
    })
  })

  // ─── handleSearchToggle ───────────────────────────────────────────────────

  describe('handleSearchToggle', () => {
    it('一般點擊時切換 showSearchDialog', () => {
      const { showSearchDialog, handleSearchToggle } = useFishSearch(
        currentFilters,
        nameQuery,
        onSearch
      )
      expect(showSearchDialog.value).toBe(false)
      handleSearchToggle({})
      expect(showSearchDialog.value).toBe(true)
      handleSearchToggle({})
      expect(showSearchDialog.value).toBe(false)
    })

    it('Shift + 點擊時清空表單並開啟 dialog', () => {
      currentFilters = makeFilters({ tribe: '阿美族' })
      nameQuery = ref('飛魚')
      const { showSearchDialog, handleSearchToggle } = useFishSearch(
        currentFilters,
        nameQuery,
        onSearch
      )
      handleSearchToggle({ shiftKey: true })
      expect(showSearchDialog.value).toBe(true)
      expect(currentFilters.value.tribe).toBe('')
      expect(nameQuery.value).toBe('')
    })
  })

  // ─── submitUnifiedSearch ──────────────────────────────────────────────────

  describe('submitUnifiedSearch', () => {
    it('呼叫 onSearch 並關閉 dialog', () => {
      const { showSearchDialog, submitUnifiedSearch, handleSearchToggle } = useFishSearch(
        currentFilters,
        nameQuery,
        onSearch
      )
      handleSearchToggle({}) // 開啟 dialog
      submitUnifiedSearch()
      expect(onSearch).toHaveBeenCalledOnce()
      expect(showSearchDialog.value).toBe(false)
    })
  })

  // ─── resetUnifiedSearch ───────────────────────────────────────────────────

  describe('resetUnifiedSearch', () => {
    it('呼叫 onSearch 並關閉 dialog', () => {
      const { showSearchDialog, resetUnifiedSearch, handleSearchToggle } = useFishSearch(
        currentFilters,
        nameQuery,
        onSearch
      )
      handleSearchToggle({}) // 開啟 dialog
      resetUnifiedSearch()
      expect(onSearch).toHaveBeenCalledOnce()
      expect(showSearchDialog.value).toBe(false)
    })
  })
})
