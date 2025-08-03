<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :submitNote="handleNext"
      :submitting="submitting"
      title="新增魚類"
      :showSubmit="true"
      :submitLabel="step === 1 && submitting ? '上傳中...' : step === 3 ? '送出' : '下一步'"
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
      <FishSizeSelector
        v-if="step === 3"
        :fishId="fishId"
        @finished="onSizeFinished"
        ref="sizeSelectorRef"
      />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import TopNavBar from '@/Components/Global/TopNavBar.vue'
import FishImageUploader from '@/Components/FishImageUploader.vue'
import FishNameForm from '@/Components/FishNameForm.vue'
import FishSizeSelector from '@/Components/FishSizeSelector.vue'

const step = ref(1)
const uploadedFileName = ref('')
const fishId = ref(null)
const submitting = ref(false)
const uploaderRef = ref(null)
const nameFormRef = ref(null)
const sizeSelectorRef = ref(null)

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

// 統一由 TopNavBar 送出
function handleNext() {
  submitting.value = true
  if (step.value === 1 && uploaderRef.value) {
    uploaderRef.value.uploadImage().finally(() => {
      submitting.value = false
    })
  } else if (step.value === 2 && nameFormRef.value) {
    nameFormRef.value.submitForm().finally(() => {
      submitting.value = false
    })
  } else if (step.value === 3 && sizeSelectorRef.value) {
    sizeSelectorRef.value.submitSize().finally(() => {
      submitting.value = false
    })
  } else {
    submitting.value = false
  }
}

function onImageUploaded(filename) {
  uploadedFileName.value = filename
  step.value = 2
  submitting.value = false // 進入下一頁時解除動畫
}
function onFishSubmitted(id) {
  fishId.value = id
  step.value = 3
  submitting.value = false
}
function onSizeFinished() {
  router.visit('/fishs')
  submitting.value = false
}
</script>
