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

      <!-- 進階知識列表 -->
      <div class="bg-white rounded-lg shadow-md p-4">
        <h3 class="text-lg font-semibold mb-4">進階知識</h3>

        <!-- 空狀態 -->
        <div v-if="totalKnowledgeCount === 0" class="text-center py-8">
          <div class="text-gray-400 mb-4">
            <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
              ></path>
            </svg>
          </div>
          <h3 class="text-lg font-medium text-gray-900 mb-2">尚未記錄進階知識</h3>
          <p class="text-gray-500">點擊右下角的按鈕開始記錄這條魚的進階知識資訊</p>
        </div>

        <!-- 按分類分組的知識列表 -->
        <div v-else class="space-y-6">
          <div v-for="category in groupedNotes" :key="category.name" class="mb-6">
            <!-- 分類標題 -->
            <div class="flex items-center mb-3">
              <h4 class="text-md font-semibold text-gray-800">{{ category.name }}</h4>
              <span class="ml-2 text-sm text-gray-500">({{ category.notes.length }})</span>
            </div>

            <!-- 分類下的知識卡片 -->
            <div class="space-y-3">
              <FishKnowledgeCard
                v-for="note in category.notes"
                :key="note.id"
                :note="note"
                :fishId="fish.id"
                @updated="onNoteUpdated"
                @deleted="onNoteDeleted"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 新增進階知識 FAB 按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="新增知識"
      icon="+"
      :to="`/fish/${fish.id}/create`"
      position="right-bottom"
    />

    <!-- 底部導航列 -->
    <BottomNavBar
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledgeList="`/fish/${fish.id}/knowledge-list`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="'knowledgeList'"
    />
  </div>
</template>

<script setup>
import FishKnowledgeCard from '../Components/FishKnowledgeCard.vue'
import LazyImage from '../Components/LazyImage.vue'
import FabButton from '../Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  fish: Object,
  groupedNotes: Object,
})

// 計算總知識數量
const totalKnowledgeCount = computed(() => {
  if (!props.groupedNotes) return 0
  return Object.values(props.groupedNotes).reduce((total, category) => {
    return total + (category.notes ? category.notes.length : 0)
  }, 0)
})

// 計算分類數量
const categoryCount = computed(() => {
  if (!props.groupedNotes) return 0
  return Object.keys(props.groupedNotes).length
})

// 轉換分組資料為陣列格式
const groupedNotes = computed(() => {
  if (!props.groupedNotes) return []
  return Object.entries(props.groupedNotes)
    .map(([name, data]) => ({
      name: name || '一般知識',
      notes: data.notes || [],
    }))
    .sort((a, b) => a.name.localeCompare(b.name))
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
