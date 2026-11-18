<template>
  <Head title="雅美（達悟）族魚類圖鑑" />

  <FishAppLayout
    :pageTitle="PAGE_TITLE"
    mobileBackUrl="/"
    mobileBackText="首頁"
    :showBottomNav="false"
  >
    <div class="container mx-auto px-4 pb-40 relative pt-6">
      <!-- 內容區 -->
      <main ref="scrollHost">
        <div ref="searchBar" class="mb-4">
          <SearchToggleButton label="搜尋魚類" @toggle="handleSearchToggle" />
        </div>

        <!-- 統一搜尋對話框元件 -->
        <FishSearchModal
          v-model:show="showSearchDialog"
          v-model:filters="currentFilters"
          v-model:nameQuery="nameQuery"
          :searchOptions="searchOptions"
          :canAccessLocation="hasLocationAccess"
          :showAdvanced="hasAdvancedSearch"
          @submit="submitAndScrollTop"
          @reset="resetAndScrollTop"
        />

        <FishSearchStatsBar
          variant="header"
          :showTotalCount="false"
          :totalCount="totalCount"
          :appliedFilters="appliedFilters"
          @remove-filter="removeFilter"
          @clear-all="clearAllFilters"
        />

        <ul class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 pt-2">
          <li v-for="(item, index) in items" :key="item.id">
            <FishCard
              :fish="item"
              :index="index"
              :gated="!hasActivatedAccount"
              @blocked="openLoginSheet(item, $event)"
            />
          </li>
        </ul>

        <p
          v-if="showEmptyState"
          data-testid="fish-list-empty"
          class="py-12 text-center text-elder-body text-elder-subtext"
        >
          找不到符合的魚
        </p>

        <FishSearchLoading :show="isLoading" />
        <div ref="sentinel" class="h-8"></div>
        <FishSearchCursorErrorBanner :show="showCursorError" @retry="retryFromStart" />
      </main>
    </div>

    <GuestLoginSheet
      :show="Boolean(sheetFish)"
      :fishId="sheetFish?.id"
      :fishName="sheetFish?.name"
      :pendingActivation="isPendingActivation"
      @close="closeLoginSheet"
    />
    <!-- 右下角：回到頂端在上、搜尋在下（搜尋最常用，拇指最好按） -->
    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
      <BackToTopButton />
      <FloatingSearchButton
        v-if="showFloatingSearch"
        :filtered="appliedFilters.length > 0"
        @click="openSearch"
      />
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import { ref, onMounted, onBeforeUnmount, watch, computed, nextTick } from 'vue'
import { canAccessLocation, isActivated } from '@/constants/roles'

import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import SearchToggleButton from '@/Components/UI/SearchToggleButton.vue'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'
import FishSearchStatsBar from '@/Components/FishList/FishSearchStatsBar.vue'
import FishSearchLoading from '@/Components/FishList/FishSearchLoading.vue'
import FishSearchCursorErrorBanner from '@/Components/FishList/FishSearchCursorErrorBanner.vue'
import FishCard from '@/Components/FishList/FishCard.vue'
import GuestLoginSheet from '@/Components/FishList/GuestLoginSheet.vue'
import BackToTopButton from '@/Components/UI/BackToTopButton.vue'
import FloatingSearchButton from '@/Components/UI/FloatingSearchButton.vue'

import { useFishList } from '@/composables/useFishList'
import { useFishListCache } from '@/composables/useFishListCache'
import { useFishSearch } from '@/composables/useFishSearch'

const PAGE_TITLE = 'among no tao'
const userRole = computed(() => usePage().props.auth?.user?.role)
const hasLocationAccess = computed(() => canAccessLocation(userRole.value))
const hasActivatedAccount = computed(() => isActivated(userRole.value))
const hasAdvancedSearch = hasActivatedAccount
// 已登入但還沒開通（guest）：面板改說等待開通
const isPendingActivation = computed(() => userRole.value === 'guest')

// ── 訪客面板：點魚卡時顯示，關閉後焦點回到被點的魚卡 ──
const sheetFish = ref(null)
let sheetTrigger = null
const openLoginSheet = (fish, trigger) => {
  sheetFish.value = fish
  sheetTrigger = trigger
}
const closeLoginSheet = () => {
  sheetFish.value = null
  nextTick(() => sheetTrigger?.focus())
}

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

// 浮動搜尋鈕不傳點擊事件：避免 Shift+點擊清空表單，圓點才會一直對應實際列表條件
const openSearch = () => handleSearchToggle()

// 送出／清除後回到最上方，讓使用者看到新結果與已套用的篩選標籤
const scrollToTop = () => window.scrollTo({ top: 0, behavior: 'instant' })
const submitAndScrollTop = () => {
  submitUnifiedSearch()
  scrollToTop()
}
const resetAndScrollTop = () => {
  resetUnifiedSearch()
  scrollToTop()
}

// ── 上方搜尋橫條看不到（離開畫面或被黏住的導覽列蓋住）才顯示浮動搜尋鈕 ──
const searchBar = ref(null)
const isSearchBarVisible = ref(true)
const showFloatingSearch = computed(() => !isSearchBarVisible.value)
let searchBarObserver = null
let observedNavBottom = null

const measureNavBottom = () =>
  Math.max(0, Math.round(document.querySelector('header')?.getBoundingClientRect().bottom ?? 0))

// 觀察範圍的上緣扣掉導覽列底緣，橫條被導覽列蓋住時也算看不到
const observeSearchBar = () => {
  if (!searchBar.value) return
  searchBarObserver?.disconnect()
  observedNavBottom = measureNavBottom()
  searchBarObserver = new IntersectionObserver(
    ([entry]) => {
      isSearchBarVisible.value = entry.isIntersecting
    },
    { rootMargin: `${-observedNavBottom}px 0px 0px 0px` }
  )
  searchBarObserver.observe(searchBar.value)
}

// 視窗大小改變（例如跨手機／桌機斷點）導覽列高度會變，底緣有變才重建觀察
const refreshSearchBarObserver = () => {
  if (measureNavBottom() !== observedNavBottom) observeSearchBar()
}

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

// ── 搜尋沒有結果：初始載入流程跑完、沒在載入、也不是游標失效時才算「沒有結果」──
const initialized = ref(false)
const showEmptyState = computed(
  () => initialized.value && !isLoading.value && !showCursorError.value && items.value.length === 0
)

// ── 初始化 ─────────────────────────────────────────────────────
const initList = async () => {
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
}

onMounted(async () => {
  observeSearchBar()
  window.addEventListener('resize', refreshSearchBarObserver)
  try {
    await initList()
  } finally {
    initialized.value = true
  }
})

onBeforeUnmount(() => {
  if (items.value.length) saveStateToStorage()
  disconnectObserver()
  searchBarObserver?.disconnect()
  window.removeEventListener('resize', refreshSearchBarObserver)
})
</script>
