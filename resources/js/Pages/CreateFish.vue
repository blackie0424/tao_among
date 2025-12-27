<template>
  <Head title="新增魚類" />

  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :submitNote="handleNext"
      :submitting="submitting"
      :title="topNavTitle"
      :showSubmit="showSubmitButton"
      :submitLabel="submitLabel"
      :showLoading="step === 1 && submitting"
    />
    <div class="pt-16">
      <FishImageUploader v-if="step === 1" @uploaded="onImageUploaded" ref="uploaderRef" />
      <FishNameForm
        v-if="step === 2"
        :uploadedFileName="uploadedFileName"
        @submitted="onFishSubmitted"
        ref="nameFormRef"
      />

      <!-- Step 3: 詢問是否新增捕獲紀錄 -->
      <div v-if="step === 3" class="max-w-md mx-auto mt-8">
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
          <!-- 成功圖示 -->
          <div
            class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4"
          >
            <svg
              class="h-8 w-8 text-green-600"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>

          <!-- 成功訊息 -->
          <h2 class="text-2xl font-bold text-gray-900 mb-2">魚類建立成功！</h2>
          <p class="text-lg text-gray-600 mb-4">「{{ createdFishName }}」已成功加入資料庫</p>

          <!-- 詢問訊息 -->
          <div class="bg-blue-50 rounded-lg p-4 mb-6">
            <p class="text-base text-gray-700 mb-2">是否要記錄這次的捕獲資訊？</p>
            <p class="text-sm text-gray-500">（圖片將自動帶入，不需重新上傳）</p>
          </div>

          <!-- 稍後再說按鈕 -->
          <button
            @click="skipCaptureRecord"
            class="w-full px-4 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium"
          >
            稍後再說
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import { onMounted, ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import TopNavBar from '@/Components/Global/TopNavBar.vue'
import FishImageUploader from '@/Components/FishImageUploader.vue'
import FishNameForm from '@/Components/FishNameForm.vue'

const props = defineProps({
  fish: {
    type: Object,
    default: null,
  },
  showCapturePrompt: {
    type: Boolean,
    default: false,
  },
  imageFileName: {
    type: String,
    default: '',
  },
})

const step = ref(1)
const uploadedFileName = ref('')
const submitting = ref(false)
const uploaderRef = ref(null)
const nameFormRef = ref(null)

// Step 3 相關狀態
const createdFishId = ref(null)
const createdFishName = ref('')
const imageFileName = ref('')

// 動態計算 TopNavBar 的標題
const topNavTitle = computed(() => {
  if (step.value === 1) return '新增魚類'
  if (step.value === 2) return '新增魚類'
  if (step.value === 3) return '魚類建立成功！'
  return '新增魚類'
})

// 動態計算送出按鈕文字
const submitLabel = computed(() => {
  if (step.value === 1 && submitting.value) return '上傳中...'
  if (step.value === 1) return '下一步'
  if (step.value === 2) return '送出'
  if (step.value === 3) return '記錄捕獲資訊'
  return '送出'
})

// 動態控制是否顯示送出按鈕
const showSubmitButton = computed(() => {
  return true
})

// 返回
function goBack() {
  if (step.value === 3) {
    // 在第三步驟，取消就是「稍後再說」
    skipCaptureRecord()
  } else {
    window.history.length > 1 ? window.history.back() : router.visit('/fishs')
  }
}

// 統一由 TopNavBar 送出
function handleNext() {
  if (step.value === 1 && uploaderRef.value) {
    if (!uploaderRef.value.selectedFile) {
      uploaderRef.value.uploadError = '請選擇要上傳的圖片'
      submitting.value = false
      return
    }
    submitting.value = true
    uploaderRef.value.uploadImage().finally(() => {
      submitting.value = false
    })
  } else if (step.value === 2 && nameFormRef.value) {
    submitting.value = true
    nameFormRef.value.submitForm().finally(() => {
      submitting.value = false
    })
  } else if (step.value === 3) {
    // 第三步驟：點「記錄捕獲資訊」
    goToAddCaptureRecord()
  } else {
    submitting.value = false
  }
}

function onImageUploaded(filename) {
  uploadedFileName.value = filename
  step.value = 2
  submitting.value = false
}

function onFishSubmitted(fishId) {
  submitting.value = false

  // 如果後端回傳 showCapturePrompt，進入第三步驟
  if (props.showCapturePrompt && fishId) {
    createdFishId.value = fishId
    createdFishName.value = props.fish?.name || ''
    imageFileName.value = props.imageFileName || uploadedFileName.value
    step.value = 3
  } else {
    // 否則按原流程導向詳情頁
    if (fishId) {
      router.visit(`/fish/${fishId}`)
    } else {
      router.visit('/fishs')
    }
  }
}

// 點「記錄捕獲資訊」
function goToAddCaptureRecord() {
  router.visit(`/fish/${createdFishId.value}/capture-records/create`, {
    data: {
      prefill_image: imageFileName.value,
    },
  })
}

// 點「稍後再說」或左上角 X
function skipCaptureRecord() {
  router.visit(`/fish/${createdFishId.value}`)
}

onMounted(() => {
  // 檢查是否從後端重新載入頁面時已經在第三步驟
  if (props.showCapturePrompt && props.fish) {
    createdFishId.value = props.fish.id
    createdFishName.value = props.fish.name
    imageFileName.value = props.imageFileName || ''
    uploadedFileName.value = props.imageFileName || ''
    step.value = 3
  }

  if (!window.heic2any) {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js'
    script.async = true
    document.head.appendChild(script)
  }
})
</script>
