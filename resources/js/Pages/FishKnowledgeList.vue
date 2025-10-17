<template>
  <Head :title="`${fish.name}的知識管理`" />

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
            <p class="text-gray-600 mb-4">進階知識管理</p>

            <!-- 統計資訊 -->
            <div class="flex flex-wrap gap-4 text-sm">
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                <span class="text-gray-700"> 已記錄 {{ totalKnowledgeCount }} 筆進階知識 </span>
              </div>
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                <span class="text-gray-700"> 涵蓋 {{ categoryCount }} 個分類 </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 網路狀態提示 -->
      <div v-if="!isOnline" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"
            />
          </svg>
          <div>
            <p class="text-sm font-medium text-yellow-800">網路連線中斷</p>
            <p class="text-xs text-yellow-600">部分功能可能無法正常使用，請檢查網路連線</p>
          </div>
        </div>
      </div>

      <div v-if="wasOffline" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fill-rule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clip-rule="evenodd"
            />
          </svg>
          <div>
            <p class="text-sm font-medium text-green-800">網路連線已恢復</p>
            <p class="text-xs text-green-600">資料已自動同步更新</p>
          </div>
        </div>
      </div>

      <!-- 進階知識列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">進階知識</h3>

        <!-- 空狀態 -->
        <div v-if="totalKnowledgeCount === 0" class="text-center py-12">
          <div class="text-gray-400 mb-6">
            <svg class="w-20 h-20 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="1.5"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
              ></path>
            </svg>
          </div>
          <h3 class="text-xl font-medium text-gray-900 mb-3">尚未記錄進階知識</h3>
          <p class="text-gray-500 mb-6 max-w-md mx-auto">
            開始記錄這條魚的進階知識，可以參考下方的分類標籤進行紀錄
          </p>
          <div class="text-sm text-gray-400">
            <p>可記錄的分類包括：</p>
            <div class="flex flex-wrap justify-center gap-2 mt-2">
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">外觀特徵</span>
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">分布地區</span>
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">傳統價值</span>
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">經驗分享</span>
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">相關故事</span>
              <span class="px-2 py-1 bg-gray-100 rounded text-xs">游棲生態</span>
            </div>
          </div>
        </div>

        <!-- 按分類分組的知識列表 -->
        <div v-else class="space-y-8">
          <div v-for="(category, index) in groupedNotes" :key="category.name" class="relative">
            <!-- 分類標題區塊 -->
            <div class="sticky top-0 bg-white z-10 pb-3">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <div class="w-1 h-6 bg-blue-500 rounded-full mr-3"></div>
                  <h4 class="text-lg font-semibold text-gray-800">{{ category.name }}</h4>
                  <span class="ml-2 px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">
                    {{ category.count || category.notes.length }}
                  </span>
                </div>
              </div>
              <!-- 分隔線 -->
              <div class="mt-3 border-b border-gray-200"></div>
            </div>

            <!-- 分類下的知識卡片 -->
            <div class="space-y-3 pt-2">
              <FishKnowledgeCard
                v-for="note in category.notes"
                :key="note.id"
                :note="note"
                :fishId="fish.id"
                @updated="onNoteUpdated"
                @deleted="onNoteDeleted"
              />
            </div>

            <!-- 分類間的間距分隔線 (除了最後一個) -->
            <div v-if="index < groupedNotes.length - 1" class="mt-8 border-b border-gray-100"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增進階知識 FAB 按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="新增進階知識"
      icon="+"
      :to="`/fish/${fish.id}/create`"
      position="right-bottom"
    />

    <!-- 底部導航列 -->
    <BottomNavBar
      :fishBasicInfo="`/fish/${fish.id}`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledge="`/fish/${fish.id}/knowledge`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="'knowledge'"
    />
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'

import FishKnowledgeCard from '../Components/FishKnowledgeCard.vue'
import LazyImage from '../Components/LazyImage.vue'
import FabButton from '../Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import { router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted } from 'vue'
import { useNetworkStatus } from '../composables/useNetworkStatus.js'

const props = defineProps({
  fish: Object,
  groupedNotes: Array,
  stats: Object,
})

// 網路狀態監控
const { isOnline, wasOffline } = useNetworkStatus()

// 計算總知識數量
const totalKnowledgeCount = computed(() => {
  return props.stats?.total || 0
})

// 計算分類數量
const categoryCount = computed(() => {
  return props.groupedNotes?.length || 0
})

// 分組資料已經是正確格式的陣列
const groupedNotes = computed(() => {
  return props.groupedNotes || []
})

// 監聽網路重連事件
onMounted(() => {
  const handleReconnect = () => {
    // 網路重連後重新載入資料
    router.reload({ only: ['groupedNotes', 'stats'] })
  }

  window.addEventListener('network-reconnected', handleReconnect)

  onUnmounted(() => {
    window.removeEventListener('network-reconnected', handleReconnect)
  })
})

function onNoteUpdated() {
  // 重新載入頁面以顯示更新的知識
  router.reload()
}

function onNoteDeleted() {
  // 重新載入頁面以移除刪除的知識
  router.reload()
}
</script>
