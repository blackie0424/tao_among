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
            <p class="text-gray-600 mb-4">捕獲紀錄與照片</p>

            <!-- 統計資訊 -->
            <div class="flex flex-wrap gap-4 text-sm">
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                <span class="text-gray-700">
                  已記錄 {{ fish.captureRecords?.length || 0 }} 筆捕獲紀錄
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

      <!-- 捕獲紀錄列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">捕獲紀錄</h3>

        <!-- 空狀態 -->
        <div v-if="fish.captureRecords?.length === 0" class="text-center py-8">
          <div class="text-gray-400 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
              ></path>
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
              ></path>
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">尚未記錄捕獲紀錄</h3>
          <p class="text-gray-500">點擊右下角的按鈕開始記錄這條魚的捕獲照片和相關資訊</p>
        </div>

        <!-- 捕獲紀錄卡片列表 -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <CaptureRecordCard
            v-for="record in fish.captureRecords"
            :key="record.id"
            :record="record"
            :fishId="fish.id"
            @updated="onRecordUpdated"
            @deleted="onRecordDeleted"
          />
        </div>
      </div>
    </div>

    <!-- 新增捕獲紀錄 FAB 按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="新增捕獲紀錄"
      icon="+"
      :to="`/fish/${fish.id}/capture-records/create`"
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
      :currentPage="'captureRecords'"
    />
  </div>
</template>

<script setup>
import CaptureRecordCard from '../Components/CaptureRecordCard.vue'
import LazyImage from '../Components/LazyImage.vue'
import FabButton from '../Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
})

// 計算已涵蓋的部落數量
const uniqueTribes = computed(() => {
  if (!props.fish.captureRecords) return []
  const tribes = props.fish.captureRecords.map((r) => r.tribe)
  return [...new Set(tribes)]
})

function onRecordUpdated() {
  // 重新載入頁面以顯示更新的紀錄
  router.reload()
}

function onRecordDeleted() {
  // 重新載入頁面以移除刪除的紀錄
  router.reload()
}
</script>
