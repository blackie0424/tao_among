<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">捕獲紀錄</h3>
      <a
        :href="`/fish/${fishId}/capture-records`"
        class="text-sm text-blue-600 hover:text-blue-800 font-medium"
      >
        查看全部
      </a>
    </div>

    <!-- 無資料狀態 -->
    <div v-if="records.length === 0" class="text-center py-8 text-gray-500">
      <svg
        class="mx-auto h-12 w-12 text-gray-400 mb-4"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
        />
        <circle cx="12" cy="13" r="3" />
      </svg>
      <p class="text-sm">尚未新增任何捕獲紀錄</p>
      <a
        :href="`/fish/${fishId}/capture-records/create`"
        class="inline-flex items-center mt-2 text-sm text-blue-600 hover:text-blue-800"
      >
        新增捕獲紀錄
      </a>
    </div>

    <!-- 捕獲紀錄預覽 -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="record in records.slice(0, 4)"
        :key="record.id"
        class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
      >
        <!-- 縮圖 -->
        <div class="flex-shrink-0">
          <LazyImage
            :src="record.image_url"
            :alt="`${record.tribe} 捕獲紀錄`"
            wrapperClass="w-12 h-12 bg-gray-200 rounded-lg overflow-hidden"
            imgClass="w-full h-full object-cover"
          />
        </div>

        <!-- 資訊 -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center space-x-2 mb-1">
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
            >
              {{ record.tribe }}
            </span>
            <span class="text-xs text-gray-500">{{ formatDate(record.capture_date) }}</span>
          </div>
          <p class="text-sm text-gray-900 truncate">{{ record.location }}</p>
          <p class="text-xs text-gray-600 truncate">{{ record.capture_method }}</p>
        </div>
      </div>

      <div v-if="records.length > 4" class="col-span-full text-center">
        <a
          :href="`/fish/${fishId}/capture-records`"
          class="text-sm text-blue-600 hover:text-blue-800"
        >
          查看其他 {{ records.length - 4 }} 筆記錄
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import LazyImage from './LazyImage.vue'

const props = defineProps({
  records: {
    type: Array,
    default: () => [],
  },
  fishId: {
    type: [String, Number],
    required: true,
  },
})

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('zh-TW')
}
</script>
