<template>
  <div class="container mx-auto p-4 relative">
    <div class="pb-20">
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
            <p class="text-gray-600 mb-4">不同部落的飲食分類與處理方式</p>

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

      <!-- 現有部落分類列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">現有部落分類</h3>

        <!-- 空狀態 -->
        <div v-if="fish.tribal_classifications?.length === 0" class="text-center py-8">
          <div class="text-gray-400 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              ></path>
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">尚未記錄地方知識</h3>
          <p class="text-gray-500">點擊右下角的按鈕開始記錄這條魚在不同部落的飲食分類與處理方式</p>
        </div>

        <!-- 分類卡片列表 -->
        <div v-else class="space-y-4">
          <TribalClassificationCard
            v-for="classification in fish.tribal_classifications"
            :key="classification.id"
            :classification="classification"
            :fishId="fish.id"
            @updated="onClassificationUpdated"
            @deleted="onClassificationDeleted"
          />
        </div>
      </div>
    </div>

    <!-- 新增地方知識 FAB 按鈕 -->
    <FabButton
      bgClass="bg-green-600"
      hoverClass="hover:bg-green-700"
      textClass="text-white"
      label="新增地方知識"
      icon="＋"
      :to="`/fish/${fish.id}/tribal-classifications/create`"
      position="right-bottom"
    />

    <!-- 底部導航列 -->
    <BottomNavBar
      :to="`/fish/${fish.id}/create`"
      :audio="`/fish/${fish.id}/createAudio`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      label="新增知識"
      icon="＋"
      :currentPage="'tribalKnowledge'"
    />
  </div>
</template>

<script setup>
import TribalClassificationCard from '../Components/TribalClassificationCard.vue'
import LazyImage from '../Components/LazyImage.vue'
import FabButton from '../Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
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

function onClassificationUpdated() {
  // 重新載入頁面以顯示更新的分類
  router.reload()
}

function onClassificationDeleted() {
  // 重新載入頁面以移除刪除的分類
  router.reload()
}
</script>
