<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <div class="container mx-auto p-4 pb-20 relative">
    <!-- 資料筆數統計卡 + Filter Chips -->
    <FishSearchStatsBar
      :totalCount="totalCount"
      :legacyTotal="legacyTotal"
      :appliedFilters="appliedFilters"
      @remove-filter="removeFilter"
    >
      <template #actions>
        <SearchToggleButton @toggle="handleSearchToggle" />
      </template>
    </FishSearchStatsBar>

    <main ref="scrollHost">
      <!-- 統一搜尋對話框元件 -->
      <FishSearchModal
        v-model:show="showSearchDialog"
        v-model:filters="currentFilters"
        v-model:nameQuery="nameQuery"
        :searchOptions="searchOptions"
        @submit="submitUnifiedSearch"
        @reset="resetUnifiedSearch"
      />

      <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <li v-for="item in items" :key="item.id">
          <FishCard :fish="item" />
        </li>
      </ul>

      <FishSearchLoading :show="isLoading" />
      <div ref="sentinel" class="h-8"></div>
      <FishSearchCursorErrorBanner :show="showCursorError" @retry="retryFromStart" />
    </main>

    <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
    <HomeBottomNavBar />
  </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'

import HomeBottomNavBar from '@/Components/Global/HomeBottomNavBar.vue'
import SearchToggleButton from '@/Components/SearchToggleButton.vue'
import FishSearchModal from '@/Components/FishSearchModal.vue'
import FishSearchStatsBar from '@/Components/FishSearchStatsBar.vue'
import FishSearchLoading from '@/Components/Global/FishSearchLoading.vue'
import FishSearchCursorErrorBanner from '@/Components/Fish/FishSearchCursorErrorBanner.vue'
import FishCard from '@/Components/FishCard.vue'

const props = defineProps({
  // legacy 完整集合（相容舊測試）
  fishs: { type: Array, default: () => [] },
  // 新搜尋契約（後端精簡列表）
  items: { type: Array, default: () => [] },
  pageInfo: { type: Object, default: () => ({ hasMore: false, nextCursor: null }) },
  filters: {
    type: Object,
    default: () => ({}),
  },
  searchOptions: {
    type: Object,
    default: () => ({
      tribes: [],
      dietaryClassifications: [],
      processingMethods: [],
      captureMethods: [],
      captureLocations: [],
    }),
  },
  searchStats: {
    type: Object,
    default: () => ({}),
  },
})

// 響應式狀態
const currentFilters = ref({
  name: '',
  tribe: '',
  food_category: '',
  processing_method: '',
  capture_location: '',
  // capture_method 已暫時移除
  ...props.filters,
})

// 新列表狀態（使用後端精簡欄位）
const items = ref(props.items || [])
const pageInfo = ref(props.pageInfo || { hasMore: false, nextCursor: null })
const nameQuery = ref(currentFilters.value.name || '')
const showCursorError = ref(false)
const isLoading = ref(false)
const showSearchDialog = ref(false)
// 顯示總筆數：優先以後端 searchStats.total_results，否則退回目前清單數
const totalCount = computed(() => {
  const stat = props.searchStats && props.searchStats.total_results
  if (typeof stat === 'number') return stat
  return Array.isArray(items.value) ? items.value.length : 0
})
// legacy 全部總數（未精簡）：使用 props.fishs 長度作為原始總數顯示
const legacyTotal = computed(() =>
  Array.isArray(props.fishs) ? props.fishs.length : totalCount.value
)

// 顯示中的搜尋條件（chip 用）
const appliedFilters = computed(() => {
  const chips = []
  const map = [
    { key: 'tribe', label: '部落', value: currentFilters.value.tribe },
    { key: 'food_category', label: '分類', value: currentFilters.value.food_category },
    { key: 'processing_method', label: '魚鱗處理', value: currentFilters.value.processing_method },
    { key: 'capture_location', label: '捕獲地點', value: currentFilters.value.capture_location },
  ]
  for (const item of map) {
    if (item.value) chips.push({ key: item.key, label: item.label, value: item.value })
  }
  if (nameQuery.value) chips.push({ key: 'name', label: '名稱', value: nameQuery.value })
  return chips
})

// 直接使用 icon 切換統一搜尋表單顯示；Shift+點可清除後重新開啟
const handleSearchToggle = (e) => {
  if (e && e.shiftKey) {
    clearUnifiedSearchForm()
    showSearchDialog.value = true
    return
  }
  showSearchDialog.value = !showSearchDialog.value
}

const submitUnifiedSearch = () => {
  performSearch()
  showSearchDialog.value = false
}
const clearUnifiedSearchForm = () => {
  currentFilters.value = {
    name: '',
    tribe: '',
    food_category: '',
    processing_method: '',
    capture_location: '',
    // capture_method 已暫時移除
  }
  nameQuery.value = ''
}
const resetUnifiedSearch = () => {
  performSearch()
  showSearchDialog.value = false
}

// 移除單一條件 chip 並立即重新搜尋
const removeFilter = (key) => {
  if (key === 'name') {
    nameQuery.value = ''
    // 若 props.filters 仍含 name，不動它，只以目前狀態為準
  } else if (key in currentFilters.value) {
    currentFilters.value[key] = ''
  }
  performSearch()
}

// 重新啟動搜尋（第一頁）
const performSearch = () => {
  showCursorError.value = false
  pageInfo.value = { hasMore: false, nextCursor: null }
  fetchPage({})
}

// 執行搜尋
const buildQueryParams = (override = {}) => {
  const base = {
    ...currentFilters.value,
    name: nameQuery.value || currentFilters.value.name || '',
    perPage: 20,
  }
  const merged = { ...base, ...override }
  return Object.fromEntries(
    Object.entries(merged).filter(([_, v]) => v !== '' && v !== null && v !== undefined)
  )
}

const fetchPage = (opts = {}) => {
  if (isLoading.value || showCursorError.value) return
  isLoading.value = true
  const params = buildQueryParams(opts)
  const isPagination = Boolean(params.last_id)
  router.get('/fishs', params, {
    preserveState: true,
    preserveScroll: true,
    replace: isPagination, // 分頁請求不堆疊歷史紀錄，避免返回鍵陷阱
    onSuccess: (page) => {
      const newItems = page.props.items || []
      const newPageInfo = page.props.pageInfo || { hasMore: false, nextCursor: null }
      if (params.last_id) {
        items.value = [...items.value, ...newItems]
      } else {
        items.value = newItems
      }
      pageInfo.value = newPageInfo
      isLoading.value = false

      // 清理網址上的分頁參數，避免重新整理後僅看到部分資料
      try {
        const url = new URL(window.location.href)
        url.searchParams.delete('last_id')
        url.searchParams.delete('perPage')
        const qs = url.searchParams.toString()
        const clean = url.pathname + (qs ? `?${qs}` : '') + (url.hash || '')
        window.history.replaceState(null, '', clean)
      } catch (e) {
        // 忽略 URL API 在部分環境不可用的情況
      }
    },
    onError: (errors) => {
      // 若為游標錯誤 (422 INVALID_CURSOR) → 顯示 Banner
      isLoading.value = false
      showCursorError.value = true
    },
  })
}

const restartSearch = () => {
  showCursorError.value = false
  pageInfo.value = { hasMore: false, nextCursor: null }
  fetchPage({})
}

const retryFromStart = () => {
  // 保留目前篩選與名稱，僅重置游標從第一頁重新抓取
  restartSearch()
}

// 監聽滾動觸底（IntersectionObserver）
const sentinel = ref(null)
let observer
const initObserver = () => {
  if (!sentinel.value) return
  observer = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (e.isIntersecting && pageInfo.value.hasMore && !isLoading.value) {
        fetchPage({ last_id: pageInfo.value.nextCursor })
      }
    })
  })
  observer.observe(sentinel.value)
}

// 監聽 props 變化
watch(
  () => props.items,
  (newVal) => {
    if (!pageInfo.value.nextCursor) items.value = newVal || []
  },
  { immediate: true }
)
watch(
  () => props.pageInfo,
  (pi) => {
    if (pi) pageInfo.value = pi
  },
  { immediate: true }
)

// 初始化
onMounted(() => {
  // 若網址含分頁參數（last_id/perPage），首次載入就清理並重抓第一頁，避免重整後只看到部分資料
  try {
    const url = new URL(window.location.href)
    const hadCursor = url.searchParams.has('last_id') || url.searchParams.has('perPage')
    if (hadCursor) {
      url.searchParams.delete('last_id')
      url.searchParams.delete('perPage')
      const qs = url.searchParams.toString()
      const clean = url.pathname + (qs ? `?${qs}` : '') + (url.hash || '')
      window.history.replaceState(null, '', clean)
      // 強制以目前篩選重新發送首批請求（忽略伺服器端因游標導致的部分資料）
      performSearch()
    } else if (!items.value.length) {
      // 初始抓第一頁（若後端未提供 items）
      fetchPage({})
    }
  } catch (e) {
    if (!items.value.length) {
      fetchPage({})
    }
  }
  initObserver()
})
</script>

<style scoped>
/* 已移至 FishSearchModal.vue */
</style>
