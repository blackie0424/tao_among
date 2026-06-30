<template>
  <Head title="新增文獻" />

  <AdminLayout title="新增文獻">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">新增文獻</h1>
      <ReferenceForm submit-url="/admin/references" submit-label="建立文獻" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ReferenceForm from '@/Components/Reference/ReferenceForm.vue'
</script>

