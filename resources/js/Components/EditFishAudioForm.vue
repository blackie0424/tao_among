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
        <p class="text-sm font-medium text-gray-900">正在編輯 {{ fishName }} 的發音資料</p>
        <p class="text-xs text-gray-500">修改發音名稱或音頻檔案</p>
      </div>
    </div>

    <!-- 發音名稱 -->
    <div>
      <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
        發音名稱 <span class="text-red-500">*</span>
      </label>
      <input
        id="name"
        v-model="form.name"
        type="text"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="例如：太魯閣族發音、阿美族發音"
        required
      />
      <div v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</div>
    </div>

    <!-- 當前音頻和上傳新音頻 -->
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1"> 音頻檔案 </label>

      <!-- 當前音頻 -->
      <div v-if="audio.locate && !audioPreview" class="mb-4">
        <p class="text-sm text-gray-600 mb-2">當前音頻：</p>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
          <button
            type="button"
            @click="toggleCurrentAudio"
            class="flex items-center justify-center w-10 h-10 bg-blue-500 text-white rounded-full hover:bg-blue-600"
          >
            <svg
              v-if="!isCurrentPlaying"
              class="w-4 h-4 ml-0.5"
              fill="currentColor"
              viewBox="0 0 24 24"
            >
              <path d="M8 5v14l11-7z" />
            </svg>
            <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M6 6h4v12H6zm8-6v12h4V6h-4z" />
            </svg>
          </button>
          <div class="flex-1">
            <p class="text-sm font-medium">{{ audio.name }}</p>
            <p class="text-xs text-gray-500">{{ audio.locate }}</p>
          </div>
        </div>
        <audio
          ref="currentAudioElement"
          :src="`/storage/audio/${audio.locate}`"
          @ended="onCurrentAudioEnded"
          preload="none"
        ></audio>
      </div>

      <!-- 上傳新音頻 -->
      <div
        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors"
      >
        <div class="space-y-1 text-center">
          <div v-if="!audioPreview">
            <svg
              class="mx-auto h-12 w-12 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 48 48"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
              />
            </svg>
            <div class="flex text-sm text-gray-600">
              <label
                for="audio"
                class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500"
              >
                <span>{{ audio.locate ? '更換音頻' : '上傳音頻' }}</span>
                <input
                  id="audio"
                  name="audio"
                  type="file"
                  accept="audio/*"
                  class="sr-only"
                  @change="handleAudioChange"
                />
              </label>
              <p class="pl-1">或拖拽到此處</p>
            </div>
            <p class="text-xs text-gray-500">MP3, WAV, M4A 最大 50MB</p>
          </div>
          <div v-else class="relative">
            <div class="flex items-center justify-center gap-3 p-4 bg-gray-100 rounded-lg">
              <button
                type="button"
                @click="togglePreviewAudio"
                class="flex items-center justify-center w-10 h-10 bg-green-500 text-white rounded-full hover:bg-green-600"
              >
                <svg
                  v-if="!isPreviewPlaying"
                  class="w-4 h-4 ml-0.5"
                  fill="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path d="M8 5v14l11-7z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6 6h4v12H6zm8-6v12h4V6h-4z" />
                </svg>
              </button>
              <div class="flex-1">
                <p class="text-sm font-medium">新音頻預覽</p>
                <p class="text-xs text-gray-500">{{ selectedFileName }}</p>
              </div>
            </div>
            <button
              type="button"
              @click="removeAudio"
              class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
              :disabled="uploading"
            >
              ×
            </button>
            <!-- 上傳狀態 -->
            <div v-if="uploading" class="mt-2 text-sm text-blue-600">
              <div class="flex items-center">
                <svg
                  class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                正在上傳音頻...
              </div>
            </div>
            <div v-else-if="uploadedAudioFilename" class="mt-2 text-sm text-green-600">
              ✓ 音頻上傳成功
            </div>
            <audio
              ref="previewAudioElement"
              :src="audioPreview"
              @ended="onPreviewAudioEnded"
              preload="none"
            ></audio>
          </div>
        </div>
      </div>
      <div v-if="errors.audio" class="text-red-500 text-sm mt-1">{{ errors.audio }}</div>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from './LazyImage.vue'

const props = defineProps({
  audio: Object,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted', 'statusChange'])

const form = reactive({
  name: '',
  audio: null,
})

const errors = ref({})
const processing = ref(false)
const audioPreview = ref(null)
const selectedFileName = ref('')
const uploading = ref(false)
const uploadedAudioFilename = ref(null)
const canSubmit = ref(true)

// 音頻播放狀態
const isCurrentPlaying = ref(false)
const isPreviewPlaying = ref(false)
const currentAudioElement = ref(null)
const previewAudioElement = ref(null)

// 初始化表單資料
onMounted(() => {
  if (props.audio) {
    form.name = props.audio.name || ''
  }
})

async function handleAudioChange(event) {
  const file = event.target.files[0]
  if (file) {
    form.audio = file
    selectedFileName.value = file.name
    canSubmit.value = false
    uploading.value = true
    errors.value = {}
    emit('statusChange', { canSubmit: false, uploading: true })

    // 建立預覽
    const reader = new FileReader()
    reader.onload = (e) => {
      audioPreview.value = e.target.result
    }
    reader.readAsDataURL(file)

    // 自動上傳音頻
    try {
      // 1. 取得簽名上傳 URL
      const signedUrlResponse = await fetch('/prefix/api/supabase/signed-upload-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: file.name }),
      })

      const signedUrlData = await signedUrlResponse.json()
      if (!signedUrlResponse.ok) {
        throw new Error(signedUrlData.message || '取得上傳網址失敗')
      }

      // 2. 直接上傳檔案到 Supabase
      const uploadResponse = await fetch(signedUrlData.url, {
        method: 'PUT',
        body: file,
      })

      if (!uploadResponse.ok) {
        throw new Error('音頻上傳失敗')
      }

      // 3. 上傳成功，儲存檔案名稱
      uploadedAudioFilename.value = signedUrlData.filename
      canSubmit.value = true
      emit('statusChange', { canSubmit: true, uploading: false })
    } catch (uploadError) {
      errors.value = { audio: uploadError.message }
      canSubmit.value = true
      emit('statusChange', { canSubmit: true, uploading: false })
    } finally {
      uploading.value = false
    }
  }
}

function removeAudio() {
  form.audio = null
  audioPreview.value = null
  selectedFileName.value = ''
  uploadedAudioFilename.value = null
  canSubmit.value = true
  isPreviewPlaying.value = false
  // 清除 input 的值
  const input = document.getElementById('audio')
  if (input) input.value = ''
}

function toggleCurrentAudio() {
  if (isCurrentPlaying.value) {
    currentAudioElement.value?.pause()
    isCurrentPlaying.value = false
  } else {
    // 停止預覽音頻
    if (isPreviewPlaying.value) {
      previewAudioElement.value?.pause()
      isPreviewPlaying.value = false
    }
    currentAudioElement.value?.play()
    isCurrentPlaying.value = true
  }
}

function togglePreviewAudio() {
  if (isPreviewPlaying.value) {
    previewAudioElement.value?.pause()
    isPreviewPlaying.value = false
  } else {
    // 停止當前音頻
    if (isCurrentPlaying.value) {
      currentAudioElement.value?.pause()
      isCurrentPlaying.value = false
    }
    previewAudioElement.value?.play()
    isPreviewPlaying.value = true
  }
}

function onCurrentAudioEnded() {
  isCurrentPlaying.value = false
}

function onPreviewAudioEnded() {
  isPreviewPlaying.value = false
}

function submitForm() {
  processing.value = true
  errors.value = {}

  // 準備表單資料
  const formData = {
    name: form.name,
    _method: 'PUT',
  }

  // 如果有上傳新音頻，加入檔案名稱
  if (uploadedAudioFilename.value) {
    formData.audio_filename = uploadedAudioFilename.value
  }

  const updateUrl = `/fish/${props.fishId}/audio/${props.audio.id}`

  router.post(updateUrl, formData, {
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

// 暴露 submitForm 方法和狀態給父元件
defineExpose({
  submitForm,
  canSubmit,
  uploading,
})
</script>
