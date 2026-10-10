<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <FishAppLayout
    :pageTitle="PAGE_TITLE"
    mobileBackUrl="/"
    mobileBackText="首頁"
    :showBottomNav="false"
  >
    <!-- Mobile Actions Slot: 搜尋按鈕 -->
    <template #mobile-actions>
      <FishListNavActions variant="mobile" @toggle="handleSearchToggle" />
    </template>

    <div class="container mx-auto px-4 pb-20 relative pt-6">
      <!-- 內容區 -->
      <main ref="scrollHost">
        <div data-testid="desktop-search-entry" class="mb-4 hidden justify-end lg:flex">
          <FishListNavActions variant="desktop" @toggle="handleSearchToggle" />
        </div>

        <!-- 統一搜尋對話框元件 -->
        <FishSearchModal
          v-model:show="showSearchDialog"
          v-model:filters="currentFilters"
          v-model:nameQuery="nameQuery"
          :searchOptions="searchOptions"
          :canAccessLocation="hasLocationAccess"
          @submit="submitUnifiedSearch"
          @reset="resetUnifiedSearch"
        />

        <FishSearchStatsBar
          variant="header"
          :showTotalCount="false"
          :totalCount="totalCount"
          :appliedFilters="appliedFilters"
          @remove-filter="removeFilter"
          @clear-all="clearAllFilters"
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
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'
import { canAccessLocation } from '@/constants/roles'

import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishListNavActions from '@/Components/FishList/FishListNavActions.vue'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'
import FishSearchStatsBar from '@/Components/FishList/FishSearchStatsBar.vue'
import FishSearchLoading from '@/Components/FishList/FishSearchLoading.vue'
import FishSearchCursorErrorBanner from '@/Components/FishList/FishSearchCursorErrorBanner.vue'
import FishCard from '@/Components/FishList/FishCard.vue'

import { useFishList } from '@/composables/useFishList'
import { useFishListCache } from '@/composables/useFishListCache'
import { useFishSearch } from '@/composables/useFishSearch'

const PAGE_TITLE = 'among no tao'
const hasLocationAccess = computed(() => canAccessLocation(usePage().props.auth?.user?.role))

const props = defineProps({
  items: { type: Array, default: () => [] },
  pageInfo: { type: Object, default: () => ({ hasMore: false, nextCursor: null }) },
  filters: { type: Object, default: () => ({}) },
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
  searchStats: { type: Object, default: () => ({}) },
})

// ── 共享狀態（傳入各 composable）──────────────────────────────
const currentFilters = ref({
  name: '',
  tribe: '',
  food_category: '',
  processing_method: '',
  capture_location: '',
  without_audio: '',
  ...props.filters,
  without_audio: props.filters?.without_audio ? 1 : '',
})
const nameQuery = ref(currentFilters.value.name || '')

// ── Composable：資料抓取 + 無限滾動 ──────────────────────────
const {
  items,
  pageInfo,
  isLoading,
  showCursorError,
  sentinel,
  fetchPage,
  performSearch,
  retryFromStart,
  initObserver,
  disconnectObserver,
  cleanPaginationFromUrl,
} = useFishList(currentFilters, nameQuery)

// ── Composable：SessionStorage 快取 ──────────────────────────
const { saveStateToStorage, clearStateStorage, restoreStateFromStorage, processStaleItems } =
  useFishListCache(items, pageInfo, currentFilters, nameQuery, () => props.filters)

// 觸發搜尋前先清快取，再交由 useFishList 重置並 fetchPage
const doSearch = () => performSearch(clearStateStorage)

// ── Composable：搜尋篩選 UI ───────────────────────────────────
const {
  showSearchDialog,
  appliedFilters,
  handleSearchToggle,
  submitUnifiedSearch,
  resetUnifiedSearch,
  clearAllFilters,
  removeFilter,
} = useFishSearch(currentFilters, nameQuery, doSearch)

// ── 統計數字（仍依賴 props，保留在頁面層）──────────────────────
const totalCount = computed(() => {
  const stat = props.searchStats && props.searchStats.total_results
  if (typeof stat === 'number') return stat
  return Array.isArray(items.value) ? items.value.length : 0
})

// ── 同步 Inertia server-side 回傳的 props ─────────────────────
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

// ── 初始化 ─────────────────────────────────────────────────────
onMounted(async () => {
  const restored = await restoreStateFromStorage()
  if (restored && items.value.length) {
    initObserver()
    return
  }

  // 快取未還原時，仍需處理 stale IDs（例如 Inertia 歷史快取導致 props 過期的情境）
  if (items.value.length) {
    await processStaleItems()
  }

  try {
    const url = new URL(window.location.href)
    const hadCursor = url.searchParams.has('last_id') || url.searchParams.has('perPage')
    if (hadCursor) {
      cleanPaginationFromUrl()
      doSearch()
    } else if (!items.value.length) {
      fetchPage({})
    }
  } catch (e) {
    if (!items.value.length) fetchPage({})
  }
  initObserver()
})

onBeforeUnmount(() => {
  if (items.value.length) saveStateToStorage()
  disconnectObserver()
})
</script>
