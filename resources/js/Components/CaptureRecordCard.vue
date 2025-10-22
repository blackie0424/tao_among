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
      <div class="flex items-center mb-1">
        <div class="flex items-center space-x-2">
          <span
            class="inline-flex items-center px-3 py-1 rounded-full text-lg font-medium bg-blue-100 text-blue-800"
          >
            {{ displayLabel }}
          </span>
          <span
            class="inline-flex items-center px-3 py-1 rounded-full text-lg font-medium bg-green-100 text-green-800"
          >
            {{ record.capture_method }}
          </span>
        </div>

        <!-- 三點選單：靠右 -->
        <div class="ml-auto">
          <OverflowMenu
            :apiUrl="`/fish/${fishId}/capture-records/${record.id}`"
            :fishId="fishId.toString()"
            :editUrl="`/fish/${fishId}/capture-records/${record.id}/edit`"
            @deleted="$emit('deleted')"
          />
        </div>
      </div>

      <!-- 備註 -->
      <div v-if="record.notes" class="mb-1">
        <span class="text-xl font-medium text-gray-800">備註：{{ record.notes }}</span>
      </div>
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

// 顯示標籤：若 location 為空則只顯示 tribe
const displayLabel = computed(() => {
  const loc = (props.record.location || '').toString().trim()
  return loc ? `${props.record.tribe} => ${loc}` : props.record.tribe
})

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('zh-TW')
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
