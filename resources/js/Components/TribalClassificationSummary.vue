<template>
  <!-- 外層卡片：更明顯的圓角、底色與陰影；增加底部間距避免被底部工具列遮蓋 -->
  <div class="rounded-xl bg-white shadow-md border border-gray-200 p-4 mb-6 md:mb-10">
    <!-- 標題區塊：淺底與下邊框，視覺分區 -->
    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
      <h3 class="text-2xl font-bold text-gray-900">地方知識</h3>
      <Link 
        v-if="user"
        :href="`/fish/${fishId}/knowledge-manager`" 
        class="flex items-center gap-1 text-sm bg-teal-100 text-teal-700 px-3 py-1.5 rounded-md font-medium hover:bg-teal-200 transition"
      >
        <span class="text-lg leading-none">⚙️</span> 管理地方知識
      </Link>
    </div>

    <!-- 比較檢視（統一顯示所有部落，無資料則顯示未紀錄） -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead>
          <tr>
            <th
              class="px-3 py-2 text-left text-sm font-bold text-gray-700 uppercase tracking-wider"
            >
              部落
            </th>
            <th
              class="px-3 py-2 text-left text-sm font-bold text-gray-700 uppercase tracking-wider"
            >
              飲食分類
            </th>
            <th
              class="px-3 py-2 text-left text-sm font-bold text-gray-700 uppercase tracking-wider"
            >
              處理方式
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-100">
          <tr v-for="item in mappedClassifications" :key="item.tribe">
            <td class="px-3 py-2 align-top">
              <span
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
              >
                {{ item.tribe }}
              </span>
            </td>
            <td class="px-3 py-2 align-top text-base md:text-lg" :class="item.hasData ? 'text-gray-900' : 'text-gray-400'">
              {{ item.food_category || '尚未紀錄' }}
            </td>
            <td class="px-3 py-2 align-top text-base md:text-lg" :class="item.hasData ? 'text-gray-900' : 'text-gray-400'">
              {{ item.processing_method || '尚未紀錄' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { usePage, Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  classifications: {
    type: Array,
    default: () => [],
  },
  fishId: {
    type: [String, Number],
    required: true,
  },
  tribes: {
    type: Array,
    default: () => [],
  }
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const mappedClassifications = computed(() => {
  // 防呆：如果父層尚未傳遞 tribes 或 tribes 為空，回退到原本的邏輯
  if (!props.tribes || props.tribes.length === 0) {
    return props.classifications.map(c => ({
      ...c,
      hasData: true
    }))
  }

  // 映射所有 tribes，並標示有沒有現存資料
  return props.tribes.map(tribe => {
    const existing = props.classifications.find(c => c.tribe === tribe)
    return {
      tribe: tribe,
      food_category: existing?.food_category || null,
      processing_method: existing?.processing_method || null,
      hasData: !!existing
    }
  })
})
</script>
