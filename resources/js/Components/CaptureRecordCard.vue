<template>
  <div class="bg-white rounded-lg shadow-md overflow-hidden">
    <!-- 捕獲照片 -->
    <div class="aspect-w-16 aspect-h-12 bg-gray-100">
      <LazyImage
        :src="recordImageUrl"
        :alt="`${record.tribe} 捕獲紀錄`"
        wrapperClass="w-full h-48 bg-gray-100"
        imgClass="w-full h-full object-cover"
      />
    </div>

    <!-- 紀錄資訊 -->
    <div class="p-4">
      <!-- 部落標籤和選單 -->
      <div class="flex items-center justify-between mb-3">
        <span
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
        >
          {{ record.tribe }}
        </span>

        <!-- 三點選單 -->
        <OverflowMenu
          :apiUrl="`/fish/${fishId}/capture-records/${record.id}`"
          :fishId="fishId.toString()"
          :editUrl="`/fish/${fishId}/capture-records/${record.id}/edit`"
          @deleted="$emit('deleted')"
        />
      </div>

      <!-- 捕獲資訊 -->
      <div class="space-y-2 mb-3">
        <div>
          <span class="text-xs font-medium text-gray-500">捕獲地點</span>
          <p class="text-sm text-gray-900">{{ record.location }}</p>
        </div>
        <div>
          <span class="text-xs font-medium text-gray-500">捕獲方式</span>
          <p class="text-sm text-gray-900">{{ record.capture_method }}</p>
        </div>
        <div>
          <span class="text-xs font-medium text-gray-500">捕獲日期</span>
          <p class="text-sm text-gray-900">{{ formatDate(record.capture_date) }}</p>
        </div>
      </div>

      <!-- 備註 -->
      <div v-if="record.notes" class="mb-3">
        <span class="text-xs font-medium text-gray-500">備註</span>
        <p class="text-sm text-gray-700 mt-1">{{ record.notes }}</p>
      </div>

      <!-- 時間資訊 -->
      <div class="text-xs text-gray-400">記錄時間: {{ formatDateTime(record.created_at) }}</div>
    </div>
  </div>
</template>

<script setup>
import LazyImage from './LazyImage.vue'
import OverflowMenu from './OverflowMenu.vue'
import { computed } from 'vue'

const props = defineProps({
  record: Object,
  fishId: Number,
})

const emit = defineEmits(['updated', 'deleted'])

// 處理捕獲紀錄圖片 URL
const recordImageUrl = computed(() => {
  // 使用後端已經處理好的 image_url 屬性
  return props.record.image_url || '/images/default-capture.png'
})

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('zh-TW')
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
