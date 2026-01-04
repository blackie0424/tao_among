<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      title="編輯捕獲紀錄"
      :showSubmit="canSubmit && !uploading"
      :submitNote="submitForm"
      :submitLabel="uploading ? '上傳中...' : '更新'"
    />
    <div class="pt-16">
      <CaptureRecordEditForm
        :record="record"
        :tribes="tribes"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.display_image_url || fish.image_url"
        @submitted="onRecordUpdated"
        @statusChange="onStatusChange"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import CaptureRecordEditForm from '../Components/CaptureRecordEditForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  record: Object,
  tribes: Array,
})

const formRef = ref(null)
const canSubmit = ref(true)
const uploading = ref(false)

function onStatusChange(status) {
  canSubmit.value = status.canSubmit
  uploading.value = status.uploading
}

function goBack() {
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

function onRecordUpdated() {
  // 返回捕獲紀錄列表頁面
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
