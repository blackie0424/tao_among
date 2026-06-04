<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      title="編輯捕獲紀錄"
      :showSubmit="canSubmit && !uploading"
      :submitNote="submitForm"
      :submitLabel="uploading ? '上傳中...' : '更新'"
    />
    <div class="pt-16">
      <CaptureRecordForm
        :record="record"
        :tribes="tribes"
        :capture_methods="capture_methods"
        :fishName="fish.name"
        :fishImage="fish.display_image_url || fish.image_url"
        @submit="onFormSubmit"
        @statusChange="onStatusChange"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import FormActionBar from '../Components/Global/FormActionBar.vue'
import CaptureRecordForm from '../Components/CaptureRecord/CaptureRecordForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  record: Object,
  tribes: Array,
  capture_methods: Array,
})

const formRef = ref(null)
const canSubmit = ref(true)
const uploading = ref(false)

function onStatusChange(status) {
  canSubmit.value = status.canSubmit
  uploading.value = status.uploading
}

function goBack() {
  router.visit(`/fish/${props.fish.id}/media-manager`)
}

function onFormSubmit(formData) {
  router.post(`/fish/${props.fish.id}/capture-records/${props.record.id}`, formData, {
    onSuccess: () => router.visit(`/fish/${props.fish.id}/media-manager`),
    onError: (e) => { formRef.value?.setErrors?.(e) },
  })
}

// 整合送出到 FormActionBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
