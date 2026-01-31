<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <FishAppLayout
    pageTitle="雅美（達悟）族魚類圖鑑清單"
    mobileBackUrl="/"
    mobileBackText="回首頁"
  >
    <!-- Desktop Nav Slot: 顯示搜尋統計與按鈕 -->
    <template #desktop-nav>
      <div class="flex items-center justify-between w-full px-4 border-l border-gray-200 ml-4 h-10">
        <div class="text-base lg:text-lg font-bold text-gray-700 flex items-center">
           資料總筆數 <span class="text-teal-700 text-xl lg:text-2xl mx-1.5 font-extrabold">{{ totalCount }}</span>
        </div>
        <div class="flex items-center gap-3">
           <!-- 將「新增魚類」按鈕也整併到上方 (Desktop) -->
           <Link
              v-if="user"
              href="/fish/create"
              class="hidden md:inline-flex items-center justify-center px-4 py-1.5 rounded-full bg-teal-600 text-white hover:bg-teal-700 shadow-md transition-all hover:scale-105 font-bold text-lg tracking-wide"
              title="新增魚類"
            >
              <span class="mr-1 text-2xl leading-none font-normal pb-1">+</span> 新增
            </Link>
           <SearchToggleButton @toggle="handleSearchToggle" />
        </div>
      </div>
    </template>

    <!-- Mobile Actions Slot: 搜尋按鈕 + 新增按鈕 (Mobile也加強顯示) -->
    <template #mobile-actions>
      <div class="flex items-center gap-3">
         <Link
            v-if="user"
            href="/fish/create"
            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-teal-600 text-white hover:bg-teal-700 shadow-md border border-white/20"
            title="新增魚類"
          >
            <span class="text-3xl leading-none font-light pb-1">+</span>
          </Link>
        <SearchToggleButton @toggle="handleSearchToggle" />
      </div>
    </template>

    <!-- Bottom Nav Slot: 空白 (移除底部新增按鈕) -->
    <template #bottom-nav>
      <!-- empty -->
    </template>

    <!-- Header Extension Slot: Sticky Search Filter Bar -->
    <template #header-extension>
       <div v-if="appliedFilters.length > 0" class="border-t border-gray-100 transition-all duration-300">
         <div class="container mx-auto px-4 py-3 max-w-7xl">
            <FishSearchStatsBar
              :showTotalCount="false"
              variant="header"
              :totalCount="totalCount"
              :appliedFilters="appliedFilters"
              @remove-filter="removeFilter"
            />
         </div>
       </div>
    </template>

    <div class="container mx-auto px-4 pb-20 relative pt-6">
      <!-- 內容區 -->
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

        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 pt-2">
          <li v-for="(item, index) in items" :key="item.id">
            <FishCard :fish="item" :index="index" />
          </li>
        </ul>

        <FishSearchLoading :show="isLoading" />
        <div ref="sentinel" class="h-8"></div>
        <FishSearchCursorErrorBanner :show="showCursorError" @retry="retryFromStart" />
      </main>

      <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, router, Link, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, watch, computed, nextTick } from 'vue'

import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SearchToggleButton from '@/Components/SearchToggleButton.vue'
import FishSearchModal from '@/Components/FishSearchModal.vue'
import FishSearchStatsBar from '@/Components/FishSearchStatsBar.vue'
import FishSearchLoading from '@/Components/Global/FishSearchLoading.vue'
import FishSearchCursorErrorBanner from '@/Components/Fish/FishSearchCursorErrorBanner.vue'
import FishCard from '@/Components/FishCard.vue'
import {
  getStaleIds,
  clearStaleIds,
  getDeletedIds,
  clearDeletedIds,
  getCreatedIds,
  clearCreatedIds,
} from '@/utils/fishListCache'

const user = computed(() => usePage().props.auth?.user)

const props = defineProps({
  // 精簡欄位列表（後端游標分頁）
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

// === SessionStorage 狀態保存 ===
const STORAGE_KEY = 'fishs_list_state'
const CACHE_TTL = 30 * 60 * 1000 // 30 分鐘過期

// 保存狀態到 sessionStorage
const saveStateToStorage = () => {
  try {
    const state = {
      items: items.value,
      pageInfo: pageInfo.value,
      scrollY: window.scrollY,
      filters: currentFilters.value,
      nameQuery: nameQuery.value,
      timestamp: Date.now(),
    }
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state))
  } catch (e) {
    // sessionStorage 不可用或容量已滿，忽略
  }
}

// 從 sessionStorage 還原狀態
const restoreStateFromStorage = async () => {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return false

    const state = JSON.parse(raw)
    // 檢查是否過期
    if (Date.now() - state.timestamp > CACHE_TTL) {
      sessionStorage.removeItem(STORAGE_KEY)
      return false
    }

    // 檢查篩選條件是否一致（若 URL 帶有不同篩選則不還原）
    const urlFilters = props.filters || {}
    const cachedFilters = state.filters || {}
    const filterKeys = ['tribe', 'food_category', 'processing_method', 'capture_location']
    const filtersMatch = filterKeys.every(
      (key) => (urlFilters[key] || '') === (cachedFilters[key] || '')
    )
    const nameMatch = (urlFilters.name || '') === (state.nameQuery || '')

    if (!filtersMatch || !nameMatch) {
      sessionStorage.removeItem(STORAGE_KEY)
      return false
    }

    // 還原狀態
    items.value = state.items || []
    pageInfo.value = state.pageInfo || { hasMore: false, nextCursor: null }
    currentFilters.value = state.filters || currentFilters.value
    nameQuery.value = state.nameQuery || ''

    // 用於追蹤快取是否需要更新
    let cacheNeedsUpdate = false

    // 檢查是否有新增的魚類（created IDs）
    const createdIds = getCreatedIds()
    if (createdIds.length > 0) {
      // 查詢新增的魚類資料並插入到清單開頭
      await fetchAndPrependCreatedItems(createdIds)
      clearCreatedIds()
      cacheNeedsUpdate = true
    }

    // 檢查是否有需要刪除的魚類（deleted IDs）
    const deletedIds = getDeletedIds()
    if (deletedIds.length > 0) {
      // 從 items 中移除已刪除的魚類
      items.value = items.value.filter((item) => !deletedIds.includes(item.id))
      clearDeletedIds()
      cacheNeedsUpdate = true
    }

    // 檢查是否有需要更新的魚類（stale IDs）
    const staleIds = getStaleIds()
    if (staleIds.length > 0) {
      // 局部更新：只更新有變動的魚類資料
      await refreshStaleItems(staleIds)
      clearStaleIds()
      cacheNeedsUpdate = true
    }

    // 若有刪除或更新，重新保存快取以確保下次重新整理時資料正確
    if (cacheNeedsUpdate) {
      saveStateToStorage()
    }

    // 延遲還原捲動位置（等待 DOM 渲染完成）
    nextTick(() => {
      setTimeout(() => {
        window.scrollTo(0, state.scrollY || 0)
      }, 50)
    })

    return true
  } catch (e) {
    return false
  }
}

// 局部更新：針對特定魚類 ID 呼叫 API 取得最新資料並替換
const refreshStaleItems = async (staleIds) => {
  const fetchPromises = staleIds.map(async (id) => {
    try {
      const response = await fetch(`/prefix/api/fish/${id}/compact`)
      if (!response.ok) return null
      const result = await response.json()
      return result.data
    } catch (e) {
      return null
    }
  })

  const freshDataList = await Promise.all(fetchPromises)

  // 在 items 中替換對應的資料
  freshDataList.forEach((freshData) => {
    if (!freshData) return
    const index = items.value.findIndex((item) => item.id === freshData.id)
    if (index !== -1) {
      items.value[index] = freshData
    }
  })
}

// 新增魚類：查詢新增的魚類資料並插入到清單開頭
const fetchAndPrependCreatedItems = async (createdIds) => {
  const fetchPromises = createdIds.map(async (id) => {
    try {
      const response = await fetch(`/prefix/api/fish/${id}/compact`)
      if (!response.ok) return null
      const result = await response.json()
      return result.data
    } catch (e) {
      return null
    }
  })

  const newDataList = await Promise.all(fetchPromises)

  // 過濾掉失敗的請求，並按 ID 降序排列（最新的在前）
  const validNewItems = newDataList.filter((item) => item !== null).sort((a, b) => b.id - a.id)

  // 插入到 items 開頭（避免重複）
  validNewItems.forEach((newItem) => {
    const exists = items.value.some((item) => item.id === newItem.id)
    if (!exists) {
      items.value.unshift(newItem)
    }
  })
}

// 清除快取
const clearStateStorage = () => {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch (e) {
    // 忽略
  }
}

// 顯示總筆數：優先以後端 searchStats.total_results，否則退回目前清單數
const totalCount = computed(() => {
  const stat = props.searchStats && props.searchStats.total_results
  if (typeof stat === 'number') return stat
  return Array.isArray(items.value) ? items.value.length : 0
})

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
  clearStateStorage() // 新搜尋時清除快取
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
onMounted(async () => {
  // 嘗試從 sessionStorage 還原狀態（優先）
  const restored = await restoreStateFromStorage()
  if (restored && items.value.length) {
    // 成功還原，初始化 observer 後即完成
    initObserver()
    return
  }

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

// 離開頁面前保存狀態
onBeforeUnmount(() => {
  if (items.value.length) {
    saveStateToStorage()
  }
  if (observer) {
    observer.disconnect()
  }
})
</script>

<style scoped>
/* 已移至 FishSearchModal.vue */
</style>
