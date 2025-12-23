<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <div class="container mx-auto p-4 pb-20 relative">
    <div class="mb-4 flex items-center justify-between">
      <div
        class="flex flex-wrap items-center gap-x-2 gap-y-1 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded-lg px-3 py-2 shadow-sm text-xl"
      >
        <div class="inline-flex items-center gap-2 shrink-0">
          <span class="text-amber-700 dark:text-amber-300">資料筆數</span>
          <span class="text-amber-900 dark:text-amber-200 font-semibold">{{ legacyTotal }}</span>
          <template v-if="totalCount !== legacyTotal">
            <span class="mx-1 text-amber-300 dark:text-amber-700">|</span>
            <span class="text-amber-700 dark:text-amber-300">符合條件</span>
            <span class="text-amber-900 dark:text-amber-200 font-medium">{{ totalCount }}</span>
          </template>
        </div>
        <!-- 已套用的搜尋條件 chips（與資料筆數同列，空間不足時自動換行） -->
        <div
          v-if="appliedFilters.length"
          class="flex flex-row flex-wrap items-center gap-x-2 gap-y-1 ml-2"
        >
          <span
            v-for="f in appliedFilters"
            :key="f.key + ':' + f.value"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-amber-300/80 bg-white/70 text-amber-800 dark:bg-amber-900/40 dark:text-amber-100 dark:border-amber-700/70 text-xl"
          >
            <span class="truncate max-w-[16rem]">{{ f.label }}：{{ f.value }}</span>
            <button
              type="button"
              class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded hover:bg-amber-200/60 dark:hover:bg-amber-700/60 text-amber-800 dark:text-amber-100"
              :aria-label="`移除條件 ${f.label}`"
              @click="removeFilter(f.key)"
            >
              ×
            </button>
          </span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <SearchToggleButton @toggle="handleSearchToggle" />
      </div>
    </div>

    <main ref="scrollHost">
      <!-- 統一搜尋對話框：包含所有下拉 + 可選填文字欄位 -->
      <transition name="fade">
        <div
          v-if="showSearchDialog"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
          @click.self="closeSearchDialog"
        >
          <div
            class="bg-white dark:bg-gray-800 w-full max-w-md rounded-xl shadow-lg p-6 relative text-xl"
          >
            <button
              class="absolute top-4 right-4 text-gray-500 hover:text-gray-700"
              @click="closeSearchDialog"
              aria-label="關閉搜尋"
            >
              ✕
            </button>
            <h2 class="font-semibold mb-4 text-gray-800 dark:text-gray-100">條件搜尋</h2>
            <form @submit.prevent="submitUnifiedSearch" class="space-y-5">
              <!-- 下拉：族群 -->
              <div>
                <label class="block mb-1 text-gray-600 dark:text-gray-300">部落</label>
                <select
                  v-model="currentFilters.tribe"
                  class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 dark:text-gray-100"
                >
                  <option value="">請選擇部落</option>
                  <option v-for="t in searchOptions.tribes" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
              <!-- 下拉：食物分類 -->
              <div>
                <label class="block mb-1 text-gray-600 dark:text-gray-300">分類</label>
                <select
                  v-model="currentFilters.food_category"
                  class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 dark:text-gray-100"
                >
                  <option value="">請選擇分類</option>
                  <option v-for="fc in searchOptions.dietaryClassifications" :key="fc" :value="fc">
                    {{ fc }}
                  </option>
                </select>
              </div>
              <!-- 下拉：魚鱗的處理方式 -->
              <div>
                <label class="block mb-1 text-gray-600 dark:text-gray-300">魚鱗的處理</label>
                <select
                  v-model="currentFilters.processing_method"
                  class="w-full border rounded px-3 py-2 bg-white dark:bg-gray-700 dark:text-gray-100"
                >
                  <option value="">請選擇魚鱗的處理方式</option>
                  <option v-for="pm in searchOptions.processingMethods" :key="pm" :value="pm">
                    {{ pm }}
                  </option>
                </select>
              </div>
              <!-- 文字：捕獲地點 -->
              <div>
                <label class="block mb-1 text-gray-600 dark:text-gray-300">捕獲地點</label>
                <input
                  v-model="currentFilters.capture_location"
                  type="text"
                  placeholder="可留空"
                  class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                />
              </div>
              <!-- 文字：名稱關鍵字 -->
              <div>
                <label class="block mb-1 text-gray-600 dark:text-gray-300">名稱</label>
                <input
                  v-model="nameQuery"
                  type="text"
                  placeholder="可留空"
                  class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-gray-100"
                />
              </div>
              <div class="flex justify-between items-center pt-2">
                <button
                  type="button"
                  @click="resetUnifiedSearch"
                  class="px-3 py-2 rounded border border-gray-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                >
                  清除
                </button>
                <button
                  type="submit"
                  class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                >
                  搜尋
                </button>
              </div>
            </form>
          </div>
        </div>
      </transition>

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
import { Head, router, Link } from '@inertiajs/vue3'
import { ref, onMounted, watch, computed } from 'vue'

import HomeBottomNavBar from '@/Components/Global/HomeBottomNavBar.vue'
import SearchToggleButton from '@/Components/SearchToggleButton.vue'
// FilterModal 已由統一搜尋表單取代（若需恢復可再引用）
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
const showFilterPanel = ref(false) // 不再使用，但保留狀態以防回退需求
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

// 搜尋防抖動計時器
let searchTimer = null

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

// 切換篩選面板顯示狀態
const toggleFilterPanel = () => {
  showFilterPanel.value = !showFilterPanel.value
}
// 若預設是開篩選，改成：第一次點 → 開搜尋對話框；再次點（或 Shift+點）→ 開篩選
// 直接使用 icon 切換統一搜尋表單顯示；Shift+點可清除後重新開啟
const handleSearchToggle = (e) => {
  if (e && e.shiftKey) {
    clearUnifiedSearchForm()
    showSearchDialog.value = true
    return
  }
  showSearchDialog.value = !showSearchDialog.value
}

// 搜尋對話框開關（改由右上角按鈕第二段互動 or 可分離 icon）
const openSearchDialog = () => {
  showSearchDialog.value = true
}
const closeSearchDialog = () => {
  showSearchDialog.value = false
}
const submitUnifiedSearch = () => {
  performSearch()
  closeSearchDialog()
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
// 清除並直接更新畫面回預設 /fishs（送出搜尋並關閉彈窗）
const resetUnifiedSearch = () => {
  clearUnifiedSearchForm()
  performSearch()
  closeSearchDialog()
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

// 處理篩選條件變更
// 統一表單不再使用即時防抖搜尋，改為按下「搜尋」才觸發

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

// 清除所有篩選
const clearAllFilters = () => {
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
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter, .fade-leave-to /* .fade-leave-active in <2.1.8 */ {
  opacity: 0;
}
</style>
