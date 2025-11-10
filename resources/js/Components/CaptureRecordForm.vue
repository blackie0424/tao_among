<template>
  <form @submit.prevent="submitForm" class="space-y-8 md:space-y-10 text-xl leading-relaxed">
    <!-- Step 1: 圖片上傳 -->
    <div v-if="step === 1" class="space-y-4 md:space-y-6">
      <div
        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors"
      >
        <div class="space-y-3 md:space-y-4 text-center">
          <div
            v-if="!imagePreview"
            class="mx-auto w-20 h-20 md:w-28 md:h-28 lg:w-32 lg:h-32 text-gray-400"
          >
            <svg
              class="h-full w-full"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 48 48"
              aria-hidden="true"
            >
              <path
                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
          <div v-else class="mx-auto w-64 md:w-80 lg:w-128 aspect-video">
            <img
              :src="imagePreview"
              alt="預覽"
              class="h-full w-full object-cover rounded-lg shadow-sm"
            />
          </div>
          <div class="flex text-base text-gray-600">
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
                :disabled="uploading"
              />
            </label>
          </div>
          <p class="text-sm text-gray-500">PNG, JPG, WEBP 最大 10MB</p>
        </div>
      </div>
      <div v-if="errors.image" class="text-red-500 text-base mt-1">{{ errors.image }}</div>

      <!-- 導航按鈕已改由 TopNavBar 控制 -->
    </div>

    <!-- Step 2: 部落、地點、時間 -->
    <div v-if="step === 2" class="space-y-6">
      <div>
        <label for="tribe" class="block text-xl font-medium text-gray-700 mb-2">
          捕獲部落 <span class="text-red-500">*</span>
        </label>
        <select
          id="tribe"
          v-model="form.tribe"
          class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">請選擇部落</option>
          <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
        </select>
        <div v-if="errors.tribe" class="text-red-500 text-base mt-1">{{ errors.tribe }}</div>
      </div>

      <div>
        <label for="location" class="block text-xl font-medium text-gray-700 mb-2">
          捕獲地點 <span class="text-red-500">*</span>
        </label>
        <input
          id="location"
          v-model="form.location"
          type="text"
          class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="請輸入捕獲地點"
        />
        <div v-if="errors.location" class="text-red-500 text-base mt-1">{{ errors.location }}</div>
      </div>

      <div>
        <label for="capture_date" class="block text-xl font-medium text-gray-700 mb-2">
          捕獲日期 <span class="text-red-500">*</span>
        </label>
        <input
          id="capture_date"
          v-model="form.capture_date"
          type="date"
          class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        <div v-if="errors.capture_date" class="text-red-500 text-base mt-1">
          {{ errors.capture_date }}
        </div>
      </div>

      <!-- 導航按鈕已改由 TopNavBar 控制 -->
    </div>

    <!-- Step 3: 捕獲方式 + 備註 + 送出 -->
    <div v-if="step === 3" class="space-y-6">
      <div>
        <label for="capture_method" class="block text-xl font-medium text-gray-700 mb-2"
          >捕獲方式 <span class="text-red-500">*</span></label
        >
        <select
          id="capture_method"
          v-model="form.capture_method"
          class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">請選擇捕獲方式</option>
          <option value="mapazat">mapazat</option>
          <option value="mamasil">mamasil</option>
          <option value="mamacik">mamacik</option>
          <option value="mitokzos">mitokzos</option>
          <option value="mipaltog">mipaltog</option>
        </select>
        <div v-if="errors.capture_method" class="text-red-500 text-base mt-1">
          {{ errors.capture_method }}
        </div>
      </div>

      <div>
        <label for="notes" class="block text-xl font-medium text-gray-700 mb-2"> 備註 </label>
        <textarea
          id="notes"
          v-model="form.notes"
          rows="3"
          class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="請輸入相關備註資訊"
        ></textarea>
        <div v-if="errors.notes" class="text-red-500 text-base mt-1">{{ errors.notes }}</div>
      </div>

      <!-- 導航按鈕已改由 TopNavBar 控制 -->
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

const step = ref(1)
const form = reactive({
  image: null,
  tribe: 'iraraley',
  capture_method: 'mamasil',
  location: '',
  capture_date: '',
  notes: '',
  image_filename: null,
})

const errors = ref({})
const processing = ref(false)
const uploading = ref(false)
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

async function uploadImage() {
  if (!form.image) {
    errors.value.image = '請先選擇圖片'
    return false
  }
  uploading.value = true
  errors.value.image = null
  try {
    const signedUrlResponse = await fetch('/prefix/api/supabase/signed-upload-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: form.image.name }),
    })
    const signedUrlData = await signedUrlResponse.json()
    if (!signedUrlResponse.ok) {
      throw new Error(signedUrlData.message || '取得上傳網址失敗')
    }

    const uploadResponse = await fetch(signedUrlData.url, {
      method: 'PUT',
      body: form.image,
    })
    if (!uploadResponse.ok) {
      throw new Error('圖片上傳失敗')
    }

    form.image_filename = signedUrlData.filename
    uploading.value = false
    return true
  } catch (e) {
    errors.value.image = e.message || '上傳失敗'
    uploading.value = false
    return false
  }
}

function nextStep() {
  errors.value = {}
  if (step.value === 1) {
    // 上傳圖片才進下一步
    uploadImage().then((ok) => {
      if (ok) step.value = 2
    })
  } else if (step.value === 2) {
    // 驗證 tribe/location/date
    const e = {}
    if (!form.tribe) e.tribe = '請選擇部落'
    if (!form.location) e.location = '請輸入地點'
    if (!form.capture_date) e.capture_date = '請選擇日期'
    if (Object.keys(e).length) {
      errors.value = e
      return
    }
    step.value = 3
  }
}

function prevStep() {
  if (step.value > 1) step.value--
}

async function finalSubmit() {
  // 驗證捕獲方式
  errors.value = {}
  if (!form.capture_method) {
    errors.value.capture_method = '請選擇捕獲方式'
    return
  }
  processing.value = true
  try {
    const formData = {
      tribe: form.tribe,
      location: form.location,
      capture_method: form.capture_method,
      capture_date: form.capture_date,
      notes: form.notes,
      image_filename: form.image_filename,
    }

    router.post(`/fish/${props.fishId}/capture-records`, formData, {
      onSuccess: () => {
        emit('submitted')
      },
      onError: (errorResponse) => {
        errors.value = errorResponse || { general: '新增失敗' }
      },
      onFinish: () => {
        processing.value = false
      },
    })
  } catch (e) {
    errors.value = { general: e.message }
    processing.value = false
  }
}

// 讓父元件可以透過 ref 呼叫 submitForm（TopNavBar 會呼叫）
async function submitForm() {
  if (step.value === 1) {
    // 若在 step1，嘗試上傳並移到下一步
    const ok = await uploadImage()
    if (ok) step.value = 2
  } else if (step.value === 2) {
    nextStep()
  } else if (step.value === 3) {
    finalSubmit()
  }
}

defineExpose({ step, nextStep, prevStep, finalSubmit, submitForm })
</script>
