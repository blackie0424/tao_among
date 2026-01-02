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

const step = ref(1)
const uploadedFileName = ref('')
const submitting = ref(false)
const uploaderRef = ref(null)
const nameFormRef = ref(null)

// 動態計算 TopNavBar 的標題
const topNavTitle = computed(() => {
  if (step.value === 1) return '新增魚類'
  if (step.value === 2) return '新增魚類'
  return '新增魚類'
})

// 動態計算送出按鈕文字
const submitLabel = computed(() => {
  if (step.value === 1 && submitting.value) return '上傳中...'
  if (step.value === 1) return '下一步'
  if (step.value === 2) return '送出'
  return '送出'
})

// 動態控制是否顯示送出按鈕
const showSubmitButton = computed(() => {
  return true
})

// 返回
function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
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

  // 建立成功後直接導向詳情頁
  if (fishId) {
    router.visit(`/fish/${fishId}`)
  } else {
    router.visit('/fishs')
  }
}

onMounted(() => {
  if (!window.heic2any) {
    const script = document.createElement('script')
    script.src = 'https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js'
    script.async = true
    document.head.appendChild(script)
  }
})
</script>
