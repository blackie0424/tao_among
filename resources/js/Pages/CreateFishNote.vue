<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      :title="`新增${fish.name}的進階知識`"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16">
      <FishNoteForm
        :tribes="tribes"
        :noteTypes="noteTypes"
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
  tribes: Array,
  noteTypes: Array,
})

const formRef = ref(null)

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-manager`)
}

function onFormSubmit(formData) {
  router.post(`/fish/${props.fish.id}/knowledge`, formData, {
    onSuccess: () => {
      formRef.value?.reset?.()
      router.visit(`/fish/${props.fish.id}/knowledge-manager`)
    },
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
