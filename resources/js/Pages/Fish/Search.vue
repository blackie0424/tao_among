<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
      <!-- 頁面標題 -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">魚類搜尋</h1>
        <p class="text-gray-600 dark:text-gray-400">透過部落分類、處理方式或地點搜尋魚類資訊</p>
      </div>

      <!-- 篩選面板 -->
      <FilterPanel
        :filters="currentFilters"
        :tribes="searchOptions.tribes"
        :food-categories="searchOptions.dietaryClassifications"
        :processing-methods="searchOptions.processingMethods"
        @filters-change="handleFiltersChange"
      />

      <!-- 搜尋結果 -->
      <SearchResults
        :results="searchResults"
        :filters="currentFilters"
        :loading="isLoading"
        @clear-filters="clearAllFilters"
      />
    </div>

    <!-- 返回首頁按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="返回首頁"
      icon="←"
      to="/"
      position="left-bottom"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import FilterPanel from '@/Components/FilterPanel.vue'
import SearchResults from '@/Components/SearchResults.vue'
import FabButton from '@/Components/FabButton.vue'

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

// 搜尋防抖動計時器
let searchTimer = null

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
  router.get('/search', cleanParams, {
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

  // 重新導向到搜尋頁面，不帶任何參數
  router.get(
    '/search',
    {},
    {
      preserveState: false,
      onSuccess: (page) => {
        searchResults.value = page.props.fishs || []
      },
    }
  )
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
