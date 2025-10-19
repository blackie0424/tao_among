<template>
  <Head title="魚類圖鑑清單" />

  <div class="container mx-auto p-4 pb-20 relative">
    <!-- 搜尋 icon（固定右上角，支援深色/淺色模式） -->
    <button
      class="fixed top-4 right-4 z-50 bg-white/90 dark:bg-gray-800/80 rounded-full shadow p-2 hover:bg-blue-100 dark:hover:bg-blue-900 transition border border-gray-300 dark:border-gray-700"
      @click="toggleFilterPanel"
      aria-label="展開搜尋篩選"
    >
      <svg
        class="w-6 h-6 text-blue-700 dark:text-gray-200"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        viewBox="0 0 24 24"
      >
        <circle cx="11" cy="11" r="8" />
        <line x1="21" y1="21" x2="16.65" y2="16.65" />
      </svg>
    </button>

    <main>
      <!-- 篩選面板（支援深色/淺色模式，提升對比） -->
      <transition name="fade">
        <div
          v-if="showFilterPanel"
          class="fixed inset-0 z-40 flex items-start justify-center bg-black/30"
        >
          <div
            class="bg-white dark:bg-gray-900 rounded-lg shadow-lg mt-16 p-4 w-full max-w-xl relative border border-gray-200 dark:border-gray-700"
          >
            <!-- 放大且明顯的紅色關閉 icon -->
            <button
              class="absolute top-4 right-4 bg-red-600 hover:bg-red-700 text-white rounded-full p-3 shadow-lg transition text-2xl flex items-center justify-center"
              style="width: 48px; height: 48px"
              @click="showFilterPanel = false"
              aria-label="關閉篩選"
            >
              <svg
                class="w-7 h-7"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                viewBox="0 0 24 24"
              >
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
            <div class="text-gray-800 dark:text-gray-100">
              <FilterPanel
                :filters="currentFilters"
                :tribes="searchOptions.tribes"
                :food-categories="searchOptions.dietaryClassifications"
                :processing-methods="searchOptions.processingMethods"
                @filters-change="handleFiltersChange"
              />
            </div>
          </div>
        </div>
      </transition>

      <!-- 搜尋結果 -->
      <SearchResults
        :results="searchResults"
        :filters="currentFilters"
        :loading="isLoading"
        @clear-filters="clearAllFilters"
      />
    </main>

    <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
    <HomeBottomNavBar />
  </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'

import HomeBottomNavBar from '@/Components/Global/HomeBottomNavBar.vue'

import { ref, onMounted, watch } from 'vue'
import FilterPanel from '@/Components/FilterPanel.vue'
import SearchResults from '@/Components/SearchResults.vue'

const props = defineProps({
  fishs: {
    type: Array,
    default: () => [],
  },
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
  location: '',
  ...props.filters,
})

const searchResults = ref(props.fishs || [])
const isLoading = ref(false)
const showFilterPanel = ref(false)

// 搜尋防抖動計時器
let searchTimer = null

// 切換篩選面板顯示狀態
const toggleFilterPanel = () => {
  showFilterPanel.value = !showFilterPanel.value
}

// 處理篩選條件變更
const handleFiltersChange = (newFilters) => {
  currentFilters.value = { ...newFilters }

  // 防抖動搜尋
  if (searchTimer) {
    clearTimeout(searchTimer)
  }

  searchTimer = setTimeout(() => {
    performSearch()
  }, 500)
}

// 執行搜尋
const performSearch = () => {
  isLoading.value = true

  // 準備搜尋參數，將 food_category 映射到後端期望的 dietary_classification
  const searchParams = {
    name: currentFilters.value.name || '',
    tribe: currentFilters.value.tribe || '',
    dietary_classification: currentFilters.value.food_category || '',
    processing_method: currentFilters.value.processing_method || '',
    capture_location: currentFilters.value.location || '',
  }

  // 移除空值參數
  const cleanParams = Object.fromEntries(
    Object.entries(searchParams).filter(([_, value]) => value !== '')
  )

  // 使用 Inertia 進行搜尋
  router.get('/fishs', cleanParams, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page) => {
      searchResults.value = page.props.fishs || []
      isLoading.value = false
    },
    onError: () => {
      isLoading.value = false
    },
  })
}

// 清除所有篩選
const clearAllFilters = () => {
  currentFilters.value = {
    name: '',
    tribe: '',
    food_category: '',
    processing_method: '',
    location: '',
  }
}

// 監聽 props 變化
watch(
  () => props.fishs,
  (newFishs) => {
    searchResults.value = newFishs || []
  },
  { immediate: true }
)

watch(
  () => props.filters,
  (newFilters) => {
    currentFilters.value = {
      name: '',
      tribe: '',
      food_category: '',
      processing_method: '',
      location: '',
      ...newFilters,
    }
  },
  { immediate: true, deep: true }
)

// 初始化
onMounted(() => {
  // 確保初始狀態正確
  searchResults.value = props.fishs || []

  // 如果沒有初始搜尋結果且沒有篩選條件，執行一次空搜尋以獲取所有結果
  if (searchResults.value.length === 0 && Object.values(currentFilters.value).every((v) => !v)) {
    performSearch()
  }
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
