<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      :title="`編輯${fish.name}的進階知識`"
      :showSubmit="!processing"
      :submitNote="submitForm"
      :submitLabel="processing ? '更新中...' : '更新'"
    />
    <div class="pt-16">
      <FishNoteForm
        :initialData="note"
        :noteTypes="noteTypes"
        :tribes="tribes"
        :fishName="fish.name"
        :fishImage="fish.display_image_url || fish.image_url"
        @submit="onFormSubmit"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import FormActionBar from '../Components/Global/FormActionBar.vue'
import FishNoteForm from '../Components/FishNote/FishNoteForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  note: Object,
  noteTypes: Array,
  tribes: Array,
})

const formRef = ref(null)
const processing = ref(false)

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

function onFormSubmit(formData) {
  processing.value = true
  router.post(`/fish/${props.fish.id}/knowledge/${props.note.id}`, formData, {
    onSuccess: () => router.visit(`/fish/${props.fish.id}/knowledge-manager`),
    onError: (e) => { formRef.value?.setErrors?.(e) },
    onFinish: () => { processing.value = false },
  })
}

// 整合送出到 FormActionBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
