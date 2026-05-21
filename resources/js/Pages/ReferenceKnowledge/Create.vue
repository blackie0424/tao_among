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
        :submit-url="`/fish/${fish.id}/reference-knowledge`"
        :cancel-url="`/fish/${fish.id}/reference-knowledge`"
        submit-label="建立文獻知識"
      />
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import ReferenceKnowledgeForm from '@/Components/ReferenceKnowledge/ReferenceKnowledgeForm.vue'

defineProps({
  fish: Object,
  references: Array,
})
</script>

