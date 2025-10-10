<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="部落飲食分類" />
    <div class="pt-16">
      <!-- 魚類資訊 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-col md:flex-row items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="fish.image"
              :alt="fish.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>

          <!-- 魚類資訊 -->
          <div class="w-full md:w-2/3">
            <h2 class="text-2xl font-bold mb-2">{{ fish.name }}</h2>
            <p class="text-gray-600 mb-4">管理不同部落的飲食分類與處理方式</p>

            <!-- 統計資訊 -->
            <div class="flex flex-wrap gap-4 text-sm">
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                <span class="text-gray-700">
                  已記錄 {{ fish.tribal_classifications?.length || 0 }} 筆部落分類
                </span>
              </div>
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                <span class="text-gray-700"> 涵蓋 {{ uniqueTribes.length }} 個部落 </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 新增部落分類表單 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <h3 class="text-lg font-semibold mb-4">新增部落分類</h3>
        <TribalClassificationForm
          :tribes="tribes"
          :foodCategories="foodCategories"
          :processingMethods="processingMethods"
          :fishId="fish.id"
          :fishName="fish.name"
          :fishImage="fish.image"
          @submitted="onClassificationSubmitted"
        />
      </div>

      <!-- 現有部落分類列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">現有部落分類</h3>
        <TribalClassificationList
          :classifications="fish.tribal_classifications"
          :tribes="tribes"
          :foodCategories="foodCategories"
          :processingMethods="processingMethods"
          :fishId="fish.id"
          @updated="onClassificationUpdated"
          @deleted="onClassificationDeleted"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import TribalClassificationForm from '../Components/TribalClassificationForm.vue'
import TribalClassificationList from '../Components/TribalClassificationList.vue'
import LazyImage from '../Components/LazyImage.vue'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
})

// 計算已涵蓋的部落數量
const uniqueTribes = computed(() => {
  if (!props.fish.tribal_classifications) return []
  const tribes = props.fish.tribal_classifications.map((c) => c.tribe)
  return [...new Set(tribes)]
})

function goBack() {
  router.visit(`/fish/${props.fish.id}`)
}

function onClassificationSubmitted() {
  // 重新載入頁面以顯示新增的分類
  router.reload()
}

function onClassificationUpdated() {
  // 重新載入頁面以顯示更新的分類
  router.reload()
}

function onClassificationDeleted() {
  // 重新載入頁面以移除刪除的分類
  router.reload()
}
</script>
