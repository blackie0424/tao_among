<template>
  <form @submit.prevent="submitForm" class="space-y-4">
    <!-- 魚類提醒 -->
    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
      <LazyImage
        :src="fishImage"
        :alt="fishName"
        wrapperClass="fish-image-wrapper"
        imgClass="fish-image"
      />
    </div>

    <!-- 部落選擇 -->
    <div>
      <label for="tribe" class="block text-sm font-medium text-gray-700 mb-1">
        部落 <span class="text-red-500">*</span>
      </label>
      <select
        id="tribe"
        v-model="form.locate"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      >
        <option value="">請選擇部落</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">
          {{ tribe }}
        </option>
      </select>
      <div v-if="errors.locate" class="text-red-500 text-sm mt-1">{{ errors.locate }}</div>
    </div>

    <!-- 知識分類選擇 -->
    <div>
      <label for="note_type" class="block text-sm font-medium text-gray-700 mb-1">
        知識分類 <span class="text-red-500">*</span>
      </label>
      <select
        id="note_type"
        v-model="form.note_type"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      >
        <option value="">請選擇分類</option>
        <option v-for="type in noteTypes" :key="type" :value="type">
          {{ type }}
        </option>
      </select>
      <div v-if="errors.note_type" class="text-red-500 text-sm mt-1">
        {{ errors.note_type }}
      </div>
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
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'

const props = defineProps({
  tribes: Array,
  noteTypes: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted'])

const form = reactive({
  locate: '',
  note_type: '',
  note: '',
})

const errors = ref({})
const processing = ref(false)

function submitForm() {
  processing.value = true
  errors.value = {}

  router.post(`/fish/${props.fishId}/knowledge`, form, {
    onSuccess: () => {
      // 重置表單
      form.locate = ''
      form.note_type = ''
      form.note = ''
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
