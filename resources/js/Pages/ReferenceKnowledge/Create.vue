<template>
  <Head :title="`新增${fish.name}的文獻知識`" />

  <FishAppLayout
    :pageTitle="`新增${fish.name}的文獻知識`"
    :mobileBackUrl="`/fish/${fish.id}/reference-knowledge`"
    mobileBackText="文獻知識"
  >
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">新增文獻知識</h1>
      <ReferenceKnowledgeForm
        :references="references"
        :tribes="tribes"
        :cancel-url="`/fish/${fish.id}/reference-knowledge`"
        submit-label="建立文獻知識"
        :processing="processing"
        @submit="onFormSubmit"
        ref="formRef"
      />
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import ReferenceKnowledgeForm from '@/Components/ReferenceKnowledge/ReferenceKnowledgeForm.vue'

const props = defineProps({ fish: Object, references: Array, tribes: Array })
const formRef = ref(null)
const processing = ref(false)

function onFormSubmit(formData) {
  processing.value = true
  router.post(`/fish/${props.fish.id}/reference-knowledge`, formData, {
    onError: (e) => { formRef.value?.setErrors?.(e) },
    onFinish: () => { processing.value = false },
  })
}
</script>
