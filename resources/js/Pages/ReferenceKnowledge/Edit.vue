<template>
  <Head :title="`編輯${fish.name}的文獻知識`" />

  <FishAppLayout
    :pageTitle="`編輯${fish.name}的文獻知識`"
    :mobileBackUrl="`/fish/${fish.id}/reference-knowledge`"
    mobileBackText="文獻知識"
  >
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯文獻知識</h1>
      <ReferenceKnowledgeForm
        :knowledge="knowledge"
        :references="references"
        :submit-url="`/fish/${fish.id}/reference-knowledge/${knowledge.id}`"
        :cancel-url="`/fish/${fish.id}/reference-knowledge`"
        method="put"
        submit-label="更新文獻知識"
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
  knowledge: Object,
  references: Array,
})
</script>

