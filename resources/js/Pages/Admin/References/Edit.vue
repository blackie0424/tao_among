<template>
  <Head title="編輯文獻" />

  <AdminLayout title="編輯文獻">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯文獻</h1>
      <ReferenceForm
        :reference="reference"
        :submit-url="`/admin/references/${reference.id}`"
        method="put"
        submit-label="更新文獻"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ReferenceForm from '@/Components/Reference/ReferenceForm.vue'

defineProps({
  reference: Object,
})
</script>

