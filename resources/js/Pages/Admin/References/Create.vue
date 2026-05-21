<template>
  <Head title="新增文獻" />

  <FishAppLayout pageTitle="新增文獻" mobileBackUrl="/admin/references" mobileBackText="文獻管理">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">新增文獻</h1>
      <ReferenceForm submit-url="/admin/references" submit-label="建立文獻" />
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import ReferenceForm from '@/Components/Reference/ReferenceForm.vue'
</script>

