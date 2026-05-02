<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      :title="`編輯${fish.name}的進階知識`"
      :showSubmit="canSubmit && !processing"
      :submitNote="submitForm"
      :submitLabel="processing ? '更新中...' : '更新'"
    />
    <div class="pt-16">
      <EditFishNoteForm
        :note="note"
        :noteTypes="noteTypes"
        :tribes="tribes"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.display_image_url || fish.image_url"
        @submitted="onNoteUpdated"
        @statusChange="onStatusChange"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import FormActionBar from '../Components/Global/FormActionBar.vue'
import EditFishNoteForm from '../Components/Fish/EditFishNoteForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  note: Object,
  noteTypes: Array,
  tribes: Array,
})

const formRef = ref(null)
const canSubmit = ref(true)
const processing = ref(false)

function onStatusChange(status) {
  canSubmit.value = status.canSubmit
  processing.value = status.processing
}

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

function onNoteUpdated() {
  // 返回進階知識列表頁面
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

// 整合送出到 FormActionBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
