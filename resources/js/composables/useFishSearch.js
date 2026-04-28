/**
 * useFishSearch — 魚類清單搜尋篩選 UI 邏輯 composable
 *
 * 職責（SRP）：
 *   - 管理 showSearchDialog 響應式狀態
 *   - 計算 appliedFilters（顯示於 header 的篩選 chips）
 *   - 封裝搜尋表單的開關、提交、重置、移除單一條件等操作
 *
 * @param {import('vue').Ref} currentFilters  篩選條件 ref（來自 Fishs.vue）
 * @param {import('vue').Ref} nameQuery        名稱關鍵字 ref（來自 Fishs.vue）
 * @param {Function}          onSearch         執行搜尋的 callback（來自 Fishs.vue）
 */

import { ref, computed } from 'vue'

export function useFishSearch(currentFilters, nameQuery, onSearch) {
  const showSearchDialog = ref(false)

  /** 目前已套用的篩選 chips（供 FishSearchStatsBar 顯示） */
  const appliedFilters = computed(() => {
    const chips = []
    const map = [
      { key: 'tribe', label: '部落', value: currentFilters.value.tribe },
      { key: 'food_category', label: '分類', value: currentFilters.value.food_category },
      {
        key: 'processing_method',
        label: '魚鱗處理',
        value: currentFilters.value.processing_method,
      },
      {
        key: 'capture_location',
        label: '捕獲地點',
        value: currentFilters.value.capture_location,
      },
    ]
    for (const item of map) {
      if (item.value) chips.push({ key: item.key, label: item.label, value: item.value })
    }
    if (nameQuery.value) chips.push({ key: 'name', label: '名稱', value: nameQuery.value })
    if (currentFilters.value.without_audio)
      chips.push({ key: 'without_audio', label: '音檔', value: '尚無音檔' })
    return chips
  })

  /** 清空搜尋表單（不執行搜尋） */
  const clearUnifiedSearchForm = () => {
    currentFilters.value = {
      name: '',
      tribe: '',
      food_category: '',
      processing_method: '',
      capture_location: '',
      without_audio: '',
    }
    nameQuery.value = ''
  }

  /**
   * 切換搜尋 Dialog 顯示
   * Shift + 點擊 → 清空表單後開啟
   */
  const handleSearchToggle = (e) => {
    if (e && e.shiftKey) {
      clearUnifiedSearchForm()
      showSearchDialog.value = true
      return
    }
    showSearchDialog.value = !showSearchDialog.value
  }

  /** 提交搜尋表單 */
  const submitUnifiedSearch = () => {
    onSearch()
    showSearchDialog.value = false
  }

  /** 重置搜尋（表單內清除按鈕） */
  const resetUnifiedSearch = () => {
    onSearch()
    showSearchDialog.value = false
  }

  /**
   * 移除單一篩選 chip 並立即重新搜尋
   * @param {string} key 篩選條件 key
   */
  const removeFilter = (key) => {
    if (key === 'name') {
      nameQuery.value = ''
    } else if (key === 'without_audio') {
      currentFilters.value.without_audio = ''
    } else if (key in currentFilters.value) {
      currentFilters.value[key] = ''
    }
    onSearch()
  }

  return {
    showSearchDialog,
    appliedFilters,
    handleSearchToggle,
    submitUnifiedSearch,
    resetUnifiedSearch,
    removeFilter,
  }
}
