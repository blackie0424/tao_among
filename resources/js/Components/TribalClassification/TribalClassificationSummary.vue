<template>
  <div class="space-y-6 w-full mb-6 md:mb-10">
    <!-- 區塊一：地方知識（分類與處理方式表格，保留原貌） -->
    <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
      <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
        <h3 class="text-xl md:text-2xl font-bold text-gray-900 flex items-center gap-2">
          地方知識
        </h3>
      </div>

      <!-- 比較檢視（統一顯示所有部落，無資料則顯示未紀錄） -->
      <div class="space-y-3 md:hidden">
        <article
          v-for="item in mappedClassifications"
          :key="item.tribe"
          data-testid="tribal-classification-card"
          class="break-words rounded-lg border border-gray-200 bg-gray-50 p-4"
        >
          <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-elder-aux font-medium text-blue-800">
            {{ item.tribe }}
          </span>
          <dl class="mt-3 space-y-3">
            <div>
              <dt class="text-elder-aux font-bold text-gray-700">飲食分類</dt>
              <dd class="mt-1 text-base" :class="item.food_category ? 'text-gray-900' : 'text-elder-subtext'">
                {{ item.food_category || '尚未紀錄' }}
              </dd>
            </div>
            <div>
              <dt class="text-elder-aux font-bold text-gray-700">處理方式</dt>
              <dd class="mt-1 text-base" :class="item.processing_method ? 'text-gray-900' : 'text-elder-subtext'">
                {{ item.processing_method || '尚未紀錄' }}
              </dd>
            </div>
          </dl>
        </article>
      </div>

      <div class="hidden overflow-x-auto md:block">
        <table class="hidden min-w-full divide-y divide-gray-200 md:table">
          <thead>
            <tr>
              <th class="px-3 py-2 text-left text-elder-aux font-bold text-gray-700 uppercase tracking-wider">部落</th>
              <th class="px-3 py-2 text-left text-elder-aux font-bold text-gray-700 uppercase tracking-wider">飲食分類</th>
              <th class="px-3 py-2 text-left text-elder-aux font-bold text-gray-700 uppercase tracking-wider">處理方式</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <tr v-for="item in mappedClassifications" :key="item.tribe">
              <td class="px-3 py-2 align-top">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-elder-aux font-medium bg-blue-100 text-blue-800">
                  {{ item.tribe }}
                </span>
              </td>
              <td class="px-3 py-2 align-top text-base md:text-lg" :class="item.food_category ? 'text-gray-900' : 'text-elder-subtext'">
                {{ item.food_category || '尚未紀錄' }}
              </td>
              <td class="px-3 py-2 align-top text-base md:text-lg" :class="item.processing_method ? 'text-gray-900' : 'text-elder-subtext'">
                {{ item.processing_method || '尚未紀錄' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 區塊二：地方知識田調紀錄（僅顯示有 notes 註記的部落） -->
    <div v-if="validClassifications.length > 0" class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
      <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold flex items-center gap-2 text-gray-900">
          <svg
            class="w-5 h-5 shrink-0 stroke-current"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            aria-hidden="true"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13.5v4.5l3 1.5"
            />
          </svg>
          地方知識田調紀錄
        </h3>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div v-for="(item, index) in validClassifications" :key="index" class="bg-gray-50 rounded-lg p-4 border border-gray-200 h-full flex flex-col">
          <div class="flex items-center mb-2 shrink-0">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-elder-aux font-medium bg-blue-100 text-blue-800">
              {{ item.tribe }}
            </span>
          </div>
          <p class="text-gray-800 text-elder-aux md:text-base whitespace-pre-line break-words leading-relaxed flex-grow">
            {{ item.notes }}
          </p>
        </div>
      </div>
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
  },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const mappedClassifications = computed(() => {
  // 防呆：如果父層尚未傳遞 tribes 或 tribes 為空，回退到原本的邏輯
  if (!props.tribes || props.tribes.length === 0) {
    return props.classifications.map(c => ({
      ...c,
    }))
  }

  // 映射所有 tribes，並標示有沒有現存資料
  return props.tribes.map(tribe => {
    const existing = props.classifications.find(c => c.tribe === tribe)
    return {
      tribe: tribe,
      food_category: existing?.food_category || null,
      processing_method: existing?.processing_method || null,
    }
  })
})

const validClassifications = computed(() => {
   if (!props.classifications) return [];
  // 僅列出有部落屬性且有寫註記的資料
   return props.classifications.filter(c => c.tribe && c.notes && c.notes.trim() !== '');
})
</script>
