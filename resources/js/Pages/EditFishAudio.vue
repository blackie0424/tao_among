<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      title="編輯發音資料"
      :showSubmit="canSubmit && !uploading"
      :submitNote="submitForm"
      :submitLabel="uploading ? '上傳中...' : '更新'"
    />
    <div class="pt-16">
      <EditFishAudioForm
        :audio="audio"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.image"
        @submitted="onAudioUpdated"
        @statusChange="onStatusChange"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import EditFishAudioForm from '../Components/EditFishAudioForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  audio: Object,
})

const formRef = ref(null)
const canSubmit = ref(true)
const uploading = ref(false)

function onStatusChange(status) {
  canSubmit.value = status.canSubmit
  uploading.value = status.uploading
}

function goBack() {
  router.visit(`/fish/${props.fish.id}/audio-list`)
}

function onAudioUpdated() {
  // 返回發音列表頁面
  router.visit(`/fish/${props.fish.id}/audio-list`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
