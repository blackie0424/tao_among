<template>
  <Head title="編輯文獻" />

  <FishAppLayout pageTitle="編輯文獻" mobileBackUrl="/admin/references" mobileBackText="文獻管理">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯文獻</h1>
      <ReferenceForm
        :reference="reference"
        :submit-url="`/admin/references/${reference.id}`"
        method="put"
        submit-label="更新文獻"
      />
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import ReferenceForm from '@/Components/Reference/ReferenceForm.vue'

defineProps({
  reference: Object,
})
</script>

