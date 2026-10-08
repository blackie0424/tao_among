<template>
  <Head title="編輯地名" />
  <AdminLayout title="編輯地名">
    <form class="max-w-xl space-y-4 rounded-xl border bg-white p-6" @submit.prevent="form.put(`/admin/places/${place.id}`)">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">名稱</label>
        <input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.name }" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">族語名稱</label>
        <input v-model="form.tao_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.tao_name }" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">備註</label>
        <textarea v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.notes }" />
      </div>
      <p v-if="firstError" class="text-red-600">{{ firstError }}</p>
      <button class="rounded-lg bg-blue-600 px-4 py-3 text-white">儲存</button>
    </form>
  </AdminLayout>
</template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { firstValidationError } from '@/utils/validationErrors'
const props = defineProps({ place: Object })
const form = useForm({ name: props.place.name, tao_name: props.place.tao_name || '', notes: props.place.notes || '' })
const firstError = computed(() => firstValidationError(form.errors))
</script>
