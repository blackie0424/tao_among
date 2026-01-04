<template>
  <div>
    <div v-if="classifications.length === 0" class="text-gray-500 text-center py-8">
      尚未新增任何部落分類資料
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="classification in classifications"
        :key="classification.id"
        class="border border-gray-200 rounded-lg p-4"
      >
        <!-- 編輯模式 -->
        <div v-if="editingId === classification.id">
          <form @submit.prevent="updateClassification(classification)" class="space-y-3">
            <!-- 部落選擇 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">部落</label>
              <select
                v-model="editForm.tribe"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option v-for="tribe in tribes" :key="tribe" :value="tribe">
                  {{ tribe }}
                </option>
              </select>
            </div>

            <!-- 飲食分類選擇 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">飲食分類</label>
              <select
                v-model="editForm.food_category"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">尚未紀錄</option>
                <option
                  v-for="category in filteredFoodCategories"
                  :key="category"
                  :value="category"
                >
                  {{ category }}
                </option>
              </select>
            </div>

            <!-- 處理方式選擇 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">處理方式</label>
              <select
                v-model="editForm.processing_method"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">尚未紀錄</option>
                <option v-for="method in filteredProcessingMethods" :key="method" :value="method">
                  {{ method }}
                </option>
              </select>
            </div>

            <!-- 調查備註 -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">調查備註</label>
              <textarea
                v-model="editForm.notes"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              ></textarea>
            </div>

            <!-- 編輯按鈕 -->
            <div class="flex justify-end space-x-2">
              <button
                type="button"
                @click="cancelEdit"
                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              >
                取消
              </button>
              <button
                type="submit"
                :disabled="processing"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {{ processing ? '更新中...' : '更新' }}
              </button>
            </div>
          </form>
        </div>

        <!-- 顯示模式 -->
        <div v-else>
          <div class="flex justify-between items-start">
            <div class="flex-1">
              <div class="flex items-center space-x-4 mb-2">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                >
                  {{ classification.tribe }}
                </span>
                <span class="text-sm text-gray-600">
                  飲食分類: {{ classification.food_category || '尚未紀錄' }}
                </span>
                <span class="text-sm text-gray-600">
                  處理方式: {{ classification.processing_method || '尚未紀錄' }}
                </span>
              </div>

              <div v-if="classification.notes" class="mt-2">
                <p class="text-sm text-gray-700">
                  <span class="font-medium">調查備註:</span>
                  {{ classification.notes }}
                </p>
              </div>

              <div class="text-xs text-gray-500 mt-2">
                建立時間: {{ formatDate(classification.created_at) }}
                <span v-if="classification.updated_at !== classification.created_at">
                  | 更新時間: {{ formatDate(classification.updated_at) }}
                </span>
              </div>
            </div>

            <!-- 操作按鈕 -->
            <div class="flex space-x-2 ml-4">
              <button
                @click="startEdit(classification)"
                class="text-blue-600 hover:text-blue-800 text-sm"
              >
                編輯
              </button>
              <button
                @click="deleteClassification(classification)"
                class="text-red-600 hover:text-red-800 text-sm"
                :disabled="processing"
              >
                刪除
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  classifications: Array,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
  fishId: Number,
})

// 過濾掉空值選項
const filteredFoodCategories = computed(() => {
  return (props.foodCategories || []).filter((category) => category !== null && category !== '')
})

const filteredProcessingMethods = computed(() => {
  return (props.processingMethods || []).filter((method) => method !== null && method !== '')
})

const emit = defineEmits(['updated', 'deleted'])

const editingId = ref(null)
const processing = ref(false)
const editForm = reactive({
  tribe: '',
  food_category: '',
  processing_method: '',
  notes: '',
})

function startEdit(classification) {
  editingId.value = classification.id
  editForm.tribe = classification.tribe
  editForm.food_category = classification.food_category
  editForm.processing_method = classification.processing_method
  editForm.notes = classification.notes
}

function cancelEdit() {
  editingId.value = null
  editForm.tribe = ''
  editForm.food_category = ''
  editForm.processing_method = ''
  editForm.notes = ''
}

function updateClassification(classification) {
  processing.value = true

  router.put(`/fish/${props.fishId}/tribal-classifications/${classification.id}`, editForm, {
    onSuccess: () => {
      editingId.value = null
      emit('updated')
    },
    onFinish: () => {
      processing.value = false
    },
  })
}

function deleteClassification(classification) {
  if (confirm('確定要刪除這筆部落分類資料嗎？')) {
    processing.value = true

    router.delete(`/fish/${props.fishId}/tribal-classifications/${classification.id}`, {
      onSuccess: () => {
        emit('deleted')
      },
      onFinish: () => {
        processing.value = false
      },
    })
  }
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
