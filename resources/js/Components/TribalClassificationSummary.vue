<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">地方知識</h3>
    </div>

    <!-- 無資料狀態 -->
    <div v-if="classifications.length === 0" class="text-center py-8 text-gray-500">
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
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      <p class="text-xl">尚未新增任何部落分類資料</p>
    </div>

    <!-- 比較檢視（預設顯示） -->
    <div v-else class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              部落
            </th>
            <th
              class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              飲食分類
            </th>
            <th
              class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
            >
              處理方式
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="classification in classifications" :key="classification.id">
            <td class="px-3 py-2 whitespace-nowrap">
              <span
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
              >
                {{ classification.tribe }}
              </span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap text-xl text-gray-900">
              {{ classification.food_category || '未分類' }}
            </td>
            <td class="px-3 py-2 whitespace-nowrap text-xl text-gray-900">
              {{ classification.processing_method || '未記錄' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  classifications: {
    type: Array,
    default: () => [],
  },
  fishId: {
    type: [String, Number],
    required: true,
  },
})
</script>
