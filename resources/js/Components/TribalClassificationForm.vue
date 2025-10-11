<template>
  <form @submit.prevent="submitForm" class="space-y-4">
    <!-- 魚類提醒 -->
    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
      <div class="w-12 h-12 flex-shrink-0">
        <LazyImage
          :src="fishImage"
          :alt="fishName"
          wrapperClass="w-full h-full bg-gray-200 rounded-lg"
          imgClass="w-full h-full object-contain"
        />
      </div>
      <div>
        <p class="text-sm font-medium text-gray-900">正在為 {{ fishName }} 新增部落分類</p>
        <p class="text-xs text-gray-500">請選擇部落並填寫相關資訊</p>
      </div>
    </div>
    <!-- 部落選擇 -->
    <div>
      <label for="tribe" class="block text-sm font-medium text-gray-700 mb-1">
        部落 <span class="text-red-500">*</span>
      </label>
      <select
        id="tribe"
        v-model="form.tribe"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      >
        <option value="">請選擇部落</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">
          {{ tribe }}
        </option>
      </select>
      <div v-if="errors.tribe" class="text-red-500 text-sm mt-1">{{ errors.tribe }}</div>
    </div>

    <!-- 飲食分類選擇 -->
    <div>
      <label for="food_category" class="block text-sm font-medium text-gray-700 mb-1">
        飲食分類
      </label>
      <select
        id="food_category"
        v-model="form.food_category"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">尚未紀錄</option>
        <option v-for="category in foodCategories" :key="category" :value="category">
          {{ category || '空值' }}
        </option>
      </select>
      <div v-if="errors.food_category" class="text-red-500 text-sm mt-1">
        {{ errors.food_category }}
      </div>
    </div>

    <!-- 處理方式選擇 -->
    <div>
      <label for="processing_method" class="block text-sm font-medium text-gray-700 mb-1">
        處理方式
      </label>
      <select
        id="processing_method"
        v-model="form.processing_method"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">尚未紀錄</option>
        <option v-for="method in processingMethods" :key="method" :value="method">
          {{ method || '空值' }}
        </option>
      </select>
      <div v-if="errors.processing_method" class="text-red-500 text-sm mt-1">
        {{ errors.processing_method }}
      </div>
    </div>

    <!-- 調查備註 -->
    <div>
      <label for="notes" class="block text-sm font-medium text-gray-700 mb-1"> 調查備註 </label>
      <textarea
        id="notes"
        v-model="form.notes"
        rows="4"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="請記錄調查者、調查時間、調查過程、不同觀點等資訊"
      ></textarea>
      <div v-if="errors.notes" class="text-red-500 text-sm mt-1">{{ errors.notes }}</div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'

const props = defineProps({
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted'])

const form = reactive({
  tribe: '',
  food_category: '',
  processing_method: '',
  notes: '',
})

const errors = ref({})
const processing = ref(false)

function submitForm() {
  processing.value = true
  errors.value = {}

  router.post(`/fish/${props.fishId}/tribal-classifications`, form, {
    onSuccess: () => {
      // 重置表單
      form.tribe = ''
      form.food_category = ''
      form.processing_method = ''
      form.notes = ''
      emit('submitted')
    },
    onError: (errorResponse) => {
      errors.value = errorResponse
    },
    onFinish: () => {
      processing.value = false
    },
  })
}

// 暴露 submitForm 方法給父元件
defineExpose({
  submitForm,
})
</script>
