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
        <p class="text-sm font-medium text-gray-900">正在為 {{ fishName }} 新增捕獲紀錄</p>
        <p class="text-xs text-gray-500">請上傳照片並填寫捕獲資訊</p>
      </div>
    </div>

    <!-- 捕獲照片上傳 -->
    <div>
      <label for="image" class="block text-sm font-medium text-gray-700 mb-1">
        捕獲照片 <span class="text-red-500">*</span>
      </label>
      <div
        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors"
      >
        <div class="space-y-1 text-center">
          <div v-if="!imagePreview" class="mx-auto h-12 w-12 text-gray-400">
            <svg fill="none" stroke="currentColor" viewBox="0 0 48 48">
              <path
                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <div v-else class="mx-auto h-32 w-32">
            <img :src="imagePreview" alt="預覽" class="h-full w-full object-cover rounded-lg" />
          </div>
          <div class="flex text-sm text-gray-600">
            <label
              for="image"
              class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500"
            >
              <span>{{ imagePreview ? '更換照片' : '上傳照片' }}</span>
              <input
                id="image"
                name="image"
                type="file"
                accept="image/*"
                class="sr-only"
                @change="handleImageChange"
                required
              />
            </label>
          </div>
          <p class="text-xs text-gray-500">PNG, JPG, WEBP 最大 10MB</p>
        </div>
      </div>
      <div v-if="errors.image" class="text-red-500 text-sm mt-1">{{ errors.image }}</div>
    </div>

    <!-- 部落選擇 -->
    <div>
      <label for="tribe" class="block text-sm font-medium text-gray-700 mb-1">
        捕獲部落 <span class="text-red-500">*</span>
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
        placeholder="請輸入捕獲地點"
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
        placeholder="請輸入捕獲方式"
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
        rows="3"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="請輸入相關備註資訊"
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
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted'])

const form = reactive({
  image: null,
  tribe: '',
  location: '',
  capture_method: '',
  capture_date: '',
  notes: '',
})

const errors = ref({})
const processing = ref(false)
const imagePreview = ref(null)

function handleImageChange(event) {
  const file = event.target.files[0]
  if (file) {
    form.image = file

    // 建立預覽
    const reader = new FileReader()
    reader.onload = (e) => {
      imagePreview.value = e.target.result
    }
    reader.readAsDataURL(file)
  }
}

async function submitForm() {
  processing.value = true
  errors.value = {}

  try {
    let imageFilename = null

    // 如果有選擇圖片，先上傳到 Supabase
    if (form.image) {
      try {
        // 1. 取得簽名上傳 URL
        const signedUrlResponse = await fetch('/prefix/api/supabase/signed-upload-url', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ filename: form.image.name }),
        })

        const signedUrlData = await signedUrlResponse.json()
        if (!signedUrlResponse.ok) {
          throw new Error(signedUrlData.message || '取得上傳網址失敗')
        }

        // 2. 直接上傳檔案到 Supabase
        const uploadResponse = await fetch(signedUrlData.url, {
          method: 'PUT',
          body: form.image,
        })

        if (!uploadResponse.ok) {
          throw new Error('圖片上傳失敗')
        }

        imageFilename = signedUrlData.filename
      } catch (uploadError) {
        errors.value = { image: uploadError.message }
        processing.value = false
        return
      }
    }

    // 3. 提交表單資料（包含上傳後的檔案名稱）
    const formData = {
      tribe: form.tribe,
      location: form.location,
      capture_method: form.capture_method,
      capture_date: form.capture_date,
      notes: form.notes,
      image_filename: imageFilename,
    }

    router.post(`/fish/${props.fishId}/capture-records`, formData, {
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
  } catch (error) {
    errors.value = { general: error.message }
    processing.value = false
  }
}

// 暴露 submitForm 方法給父元件
defineExpose({
  submitForm,
})
</script>
