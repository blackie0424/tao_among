<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <FishAppLayout
    pageTitle="among no tao"
    mobileBackUrl="/"
    mobileBackText="首頁"
    :showBottomNav="false"
  >
    <!-- Desktop Nav Slot: 搜尋與新增按鈕 -->
    <template #desktop-nav>
      <div class="flex items-center justify-end w-full px-4 h-10 gap-6">
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

    <!-- Mobile Actions Slot: 搜尋按鈕 + 新增按鈕 -->
    <template #mobile-actions>
      <div class="flex items-center justify-end px-2 w-full">
        <div class="flex items-center gap-3">
          <Link
            v-if="user"
            href="/fish/create"
            class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-teal-600 text-white hover:bg-teal-700 shadow-md border border-white/20"
            title="新增魚類"
          >
            <span class="text-2xl leading-none font-light pb-0.5">+</span>
          </Link>
          <SearchToggleButton @toggle="handleSearchToggle" />
        </div>
      </div>
    </template>

    <!-- Header Extension Slot: Sticky Search Filter Bar -->
    <template #header-extension>
      <FishSearchStatsBar
        variant="header"
        :showTotalCount="false"
        :totalCount="totalCount"
        :appliedFilters="appliedFilters"
        @remove-filter="removeFilter"
      />
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
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue'

import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SearchToggleButton from '@/Components/SearchToggleButton.vue'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'
import FishSearchStatsBar from '@/Components/FishList/FishSearchStatsBar.vue'
import FishSearchLoading from '@/Components/FishList/FishSearchLoading.vue'
import FishSearchCursorErrorBanner from '@/Components/FishList/FishSearchCursorErrorBanner.vue'
import FishCard from '@/Components/FishList/FishCard.vue'

import { useFishList } from '@/composables/useFishList'
import { useFishListCache } from '@/composables/useFishListCache'
import { useFishSearch } from '@/composables/useFishSearch'

const user = computed(() => usePage().props.auth?.user)

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
const { saveStateToStorage, clearStateStorage, restoreStateFromStorage } = useFishListCache(
  items,
  pageInfo,
  currentFilters,
  nameQuery,
  () => props.filters
)

// 觸發搜尋前先清快取，再交由 useFishList 重置並 fetchPage
const doSearch = () => performSearch(clearStateStorage)

// ── Composable：搜尋篩選 UI ───────────────────────────────────
const {
  showSearchDialog,
  appliedFilters,
  handleSearchToggle,
  submitUnifiedSearch,
  resetUnifiedSearch,
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
