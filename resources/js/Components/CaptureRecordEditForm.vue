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
        <p class="text-sm font-medium text-gray-900">正在編輯 {{ fishName }} 的捕獲紀錄</p>
        <p class="text-xs text-gray-500">修改照片或捕獲資訊</p>
      </div>
    </div>

    <!-- 當前照片和上傳新照片 -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1"> 捕獲照片 </label>

      <!-- 當前照片 -->
      <div v-if="record.image_url && !imagePreview" class="mb-4">
        <p class="text-sm text-gray-600 mb-2">當前照片：</p>
        <div class="relative inline-block">
          <LazyImage
            :src="record.image_url"
            :alt="'當前捕獲照片'"
            wrapperClass="w-32 h-32 bg-gray-100 rounded-lg"
            imgClass="w-full h-full object-cover"
          />
        </div>
      </div>

      <!-- 上傳新照片 -->
      <div
        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors"
      >
        <div class="space-y-1 text-center">
          <div v-if="!imagePreview">
            <svg
              class="mx-auto h-12 w-12 text-gray-400"
              stroke="currentColor"
              fill="none"
              viewBox="0 0 48 48"
            >
              <path
                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <div class="flex text-sm text-gray-600">
              <label
                for="image"
                class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500"
              >
                <span>{{ record.image_url ? '更換照片' : '上傳照片' }}</span>
                <input
                  id="image"
                  name="image"
                  type="file"
                  accept="image/*"
                  class="sr-only"
                  @change="handleImageChange"
                />
              </label>
              <p class="pl-1">或拖拽到此處</p>
            </div>
            <p class="text-xs text-gray-500">PNG, JPG, WEBP 最大 10MB</p>
          </div>
          <div v-else class="relative">
            <img :src="imagePreview" alt="預覽圖片" class="mx-auto h-32 w-auto rounded-lg" />
            <button
              type="button"
              @click="removeImage"
              class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
            >
              ×
            </button>
            <p class="mt-2 text-sm text-gray-600">{{ selectedFileName }}</p>
          </div>
        </div>
      </div>
      <div v-if="errors.image" class="text-red-500 text-sm mt-1">{{ errors.image }}</div>
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

    <!-- 捕獲地點 -->
    <div>
      <label for="location" class="block text-sm font-medium text-gray-700 mb-1">
        捕獲地點 <span class="text-red-500">*</span>
      </label>
      <input
        id="location"
        v-model="form.location"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="例如：太魯閣溪上游、立霧溪出海口"
        required
      />
      <div v-if="errors.location" class="text-red-500 text-sm mt-1">{{ errors.location }}</div>
    </div>

    <!-- 捕獲方式 -->
    <div>
      <label for="capture_method" class="block text-sm font-medium text-gray-700 mb-1">
        捕獲方式 <span class="text-red-500">*</span>
      </label>
      <input
        id="capture_method"
        v-model="form.capture_method"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="例如：傳統魚網、釣竿、陷阱"
        required
      />
      <div v-if="errors.capture_method" class="text-red-500 text-sm mt-1">
        {{ errors.capture_method }}
      </div>
    </div>

    <!-- 捕獲日期 -->
    <div>
      <label for="capture_date" class="block text-sm font-medium text-gray-700 mb-1">
        捕獲日期 <span class="text-red-500">*</span>
      </label>
      <input
        id="capture_date"
        v-model="form.capture_date"
        type="date"
        :max="today"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      />
      <div v-if="errors.capture_date" class="text-red-500 text-sm mt-1">
        {{ errors.capture_date }}
      </div>
    </div>

    <!-- 備註 -->
    <div>
      <label for="notes" class="block text-sm font-medium text-gray-700 mb-1"> 備註 </label>
      <textarea
        id="notes"
        v-model="form.notes"
        rows="4"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="記錄捕獲時的天氣、水況、特殊情況等"
      ></textarea>
      <div v-if="errors.notes" class="text-red-500 text-sm mt-1">{{ errors.notes }}</div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'

const props = defineProps({
  record: Object,
  tribes: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted'])

const form = reactive({
  tribe: '',
  location: '',
  capture_method: '',
  capture_date: '',
  notes: '',
  image: null,
})

const errors = ref({})
const processing = ref(false)
const imagePreview = ref(null)
const selectedFileName = ref('')

// 今天的日期（用於限制日期選擇）
const today = computed(() => {
  return new Date().toISOString().split('T')[0]
})

// 初始化表單資料
onMounted(() => {
  if (props.record) {
    form.tribe = props.record.tribe || ''
    form.location = props.record.location || ''
    form.capture_method = props.record.capture_method || ''
    form.capture_date = props.record.capture_date || ''
    form.notes = props.record.notes || ''
  }
})

function handleImageChange(event) {
  const file = event.target.files[0]
  if (file) {
    form.image = file
    selectedFileName.value = file.name

    // 建立預覽
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

function removeImage() {
  form.image = null
  imagePreview.value = null
  selectedFileName.value = ''
  // 清除 input 的值
  const input = document.getElementById('image')
  if (input) input.value = ''
}

function submitForm() {
  processing.value = true
  errors.value = {}

  // 使用 FormData 來處理檔案上傳
  const formData = new FormData()
  formData.append('_method', 'PUT')
  formData.append('tribe', form.tribe)
  formData.append('location', form.location)
  formData.append('capture_method', form.capture_method)
  formData.append('capture_date', form.capture_date)
  formData.append('notes', form.notes)
  if (form.image) {
    formData.append('image', form.image)
  }

  router.post(`/fish/${props.fishId}/capture-records/${props.record.id}`, formData, {
    onSuccess: () => {
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
