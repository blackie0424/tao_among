<template>
  <form @submit.prevent class="space-y-4">
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
        <p class="text-sm font-medium text-gray-900">正在編輯 {{ fishName }} 的進階知識</p>
        <p class="text-xs text-gray-500">修改知識內容或分類</p>
      </div>
    </div>

    <!-- 部落資訊 -->
    <div>
      <label for="locate" class="block text-sm font-medium text-gray-700 mb-1">
        部落 <span class="text-red-500">*</span>
      </label>
      <select
        id="locate"
        v-model="form.locate"
        @blur="touchField('locate')"
        :class="[
          'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors',
          errors.locate
            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
            : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500',
        ]"
        required
      >
        <option value="">請選擇部落</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">
          {{ tribe }}
        </option>
      </select>
      <div v-if="errors.locate" class="flex items-center gap-1 text-red-500 text-sm mt-1">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
        {{ errors.locate }}
      </div>
    </div>

    <!-- 知識分類 -->
    <div>
      <label for="note_type" class="block text-sm font-medium text-gray-700 mb-1">
        知識分類 <span class="text-red-500">*</span>
      </label>
      <select
        id="note_type"
        v-model="form.note_type"
        @blur="touchField('note_type')"
        :class="[
          'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors',
          errors.note_type
            ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
            : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500',
        ]"
        required
      >
        <option value="">請選擇分類</option>
        <option v-for="type in noteTypes" :key="type" :value="type">
          {{ type }}
        </option>
      </select>
      <div v-if="errors.note_type" class="flex items-center gap-1 text-red-500 text-sm mt-1">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
        {{ errors.note_type }}
      </div>
    </div>

    <!-- 知識內容 -->
    <div>
      <label for="note" class="block text-sm font-medium text-gray-700 mb-1">
        知識內容 <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <textarea
          id="note"
          v-model="form.note"
          @blur="touchField('note')"
          rows="6"
          :class="[
            'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 transition-colors resize-none',
            errors.note
              ? 'border-red-300 focus:ring-red-500 focus:border-red-500'
              : 'border-gray-300 focus:ring-blue-500 focus:border-blue-500',
          ]"
          placeholder="請輸入關於這條魚的進階知識，例如：生態習性、捕獲技巧、文化意義等"
          required
        ></textarea>
        <div class="absolute bottom-2 right-2 text-xs text-gray-400">
          {{ form.note.length }}/2000
        </div>
      </div>
      <div v-if="errors.note" class="flex items-center gap-1 text-red-500 text-sm mt-1">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
            clip-rule="evenodd"
          />
        </svg>
        {{ errors.note }}
      </div>
      <div
        v-else-if="touched.note && form.note.length >= 10"
        class="flex items-center gap-1 text-green-500 text-sm mt-1"
      >
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>
        內容長度符合要求
      </div>
    </div>

    <!-- 網路錯誤提示 -->
    <div v-if="networkError" class="p-3 bg-red-50 border border-red-200 rounded-lg">
      <div class="flex items-start gap-2">
        <svg
          class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clip-rule="evenodd"
          />
        </svg>
        <div class="flex-1">
          <p class="text-sm font-medium text-red-800">提交失敗</p>
          <p class="text-xs text-red-600 mt-1">{{ networkError }}</p>
          <div class="flex gap-2 mt-2">
            <button
              @click="retrySubmit"
              type="button"
              class="text-xs bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded transition-colors"
              :disabled="processing || retryAttempts >= maxRetryAttempts"
            >
              {{
                retryAttempts >= maxRetryAttempts
                  ? '已達重試上限'
                  : `重試 (${retryAttempts}/${maxRetryAttempts})`
              }}
            </button>
            <button
              @click="networkError = null"
              type="button"
              class="text-xs text-red-600 hover:text-red-800 px-2 py-1 transition-colors"
            >
              忽略
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'
import { useFormValidation, validationRules } from '../composables/useFormValidation.js'

const props = defineProps({
  note: Object,
  noteTypes: Array,
  tribes: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted', 'statusChange'])

// 表單驗證設置
const formValidationRules = {
  note: [
    validationRules.required('知識內容為必填欄位'),
    validationRules.minLength(10, '知識內容至少需要 10 個字元'),
    validationRules.maxLength(2000, '知識內容不能超過 2000 個字元'),
  ],
  note_type: [validationRules.maxLength(50, '分類名稱不能超過 50 個字元')],
  locate: [validationRules.maxLength(255, '位置資訊不能超過 255 個字元')],
}

const {
  form,
  errors,
  touched,
  isValid,
  hasErrors,
  validateAll,
  touchField,
  setServerErrors,
  clearErrors,
} = useFormValidation(
  {
    note_type: '',
    note: '',
    locate: '',
  },
  formValidationRules
)

const processing = ref(false)
const canSubmit = ref(true)
const networkError = ref(null)
const retryAttempts = ref(0)
const maxRetryAttempts = 3

// 初始化表單資料
onMounted(() => {
  if (props.note) {
    form.note_type = props.note.note_type || ''
    form.note = props.note.note || ''
    form.locate = props.note.locate || ''
  }
})

function submitForm() {
  // 先進行前端驗證
  if (!validateAll()) {
    return
  }

  processing.value = true
  networkError.value = null
  clearErrors()
  canSubmit.value = false
  emit('statusChange', { canSubmit: false, processing: true })

  // 準備表單資料
  const formData = {
    note_type: form.note_type,
    note: form.note,
    locate: form.locate,
    _method: 'PUT',
  }

  const updateUrl = `/fish/${props.fishId}/knowledge/${props.note.id}`

  router.post(updateUrl, formData, {
    onSuccess: () => {
      retryAttempts.value = 0
      emit('submitted')
    },
    onError: (errorResponse) => {
      handleSubmitError(errorResponse)
    },
    onFinish: () => {
      processing.value = false
    },
  })
}

function retrySubmit() {
  if (retryAttempts.value >= maxRetryAttempts) return

  retryAttempts.value++

  // 等待一段時間後重試
  setTimeout(() => {
    submitForm()
  }, 1000 * retryAttempts.value)
}

function handleSubmitError(errorResponse) {
  // 檢查是否為網路錯誤
  if (!navigator.onLine) {
    networkError.value = '無網路連線，請檢查網路狀態後重試'
  } else if (errorResponse.message && errorResponse.message.includes('timeout')) {
    networkError.value = '請求超時，請稍後再試'
  } else if (errorResponse.message && errorResponse.message.includes('500')) {
    networkError.value = '伺服器錯誤，請稍後再試'
  } else if (typeof errorResponse === 'object' && Object.keys(errorResponse).length > 0) {
    // 伺服器端驗證錯誤
    setServerErrors(errorResponse)
  } else {
    networkError.value = '提交失敗，請稍後再試'
  }

  canSubmit.value = true
  emit('statusChange', { canSubmit: true, processing: false })
}

// 暴露 submitForm 方法和狀態給父元件
defineExpose({
  submitForm,
  canSubmit,
  processing,
})
</script>
