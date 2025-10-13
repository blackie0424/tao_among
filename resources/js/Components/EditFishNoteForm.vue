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

    <!-- 知識分類 -->
    <div>
      <label for="note_type" class="block text-sm font-medium text-gray-700 mb-1"> 知識分類 </label>
      <select
        id="note_type"
        v-model="form.note_type"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">一般知識</option>
        <option v-for="type in noteTypes" :key="type" :value="type">
          {{ type }}
        </option>
      </select>
      <div v-if="errors.note_type" class="text-red-500 text-sm mt-1">{{ errors.note_type }}</div>
    </div>

    <!-- 知識內容 -->
    <div>
      <label for="note" class="block text-sm font-medium text-gray-700 mb-1">
        知識內容 <span class="text-red-500">*</span>
      </label>
      <textarea
        id="note"
        v-model="form.note"
        rows="6"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="請輸入關於這條魚的進階知識，例如：生態習性、捕獲技巧、文化意義等"
        required
      ></textarea>
      <div v-if="errors.note" class="text-red-500 text-sm mt-1">{{ errors.note }}</div>
    </div>

    <!-- 位置資訊 -->
    <div>
      <label for="locate" class="block text-sm font-medium text-gray-700 mb-1"> 位置資訊 </label>
      <input
        id="locate"
        v-model="form.locate"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="例如：太魯閣溪上游、立霧溪出海口"
      />
      <div v-if="errors.locate" class="text-red-500 text-sm mt-1">{{ errors.locate }}</div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'

const props = defineProps({
  note: Object,
  noteTypes: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted', 'statusChange'])

const form = reactive({
  note_type: '',
  note: '',
  locate: '',
})

const errors = ref({})
const processing = ref(false)
const canSubmit = ref(true)

// 初始化表單資料
onMounted(() => {
  if (props.note) {
    form.note_type = props.note.note_type || ''
    form.note = props.note.note || ''
    form.locate = props.note.locate || ''
  }
})

function submitForm() {
  processing.value = true
  errors.value = {}
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
      emit('submitted')
    },
    onError: (errorResponse) => {
      errors.value = errorResponse
      canSubmit.value = true
      emit('statusChange', { canSubmit: true, processing: false })
    },
    onFinish: () => {
      processing.value = false
    },
  })
}

// 暴露 submitForm 方法和狀態給父元件
defineExpose({
  submitForm,
  canSubmit,
  processing,
})
</script>
