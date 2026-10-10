<template>
  <Head title="新增地名" />
  <AdminLayout title="新增地名">
    <form class="max-w-xl space-y-4 rounded-xl border bg-white p-6" @submit.prevent="form.post('/admin/places')">
      <div>
        <label for="tribe" class="mb-1 block text-sm font-medium text-gray-700">部落</label>
        <select id="tribe" v-model="form.tribe" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
          <option value="" disabled>請選擇部落</option>
          <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
        </select>
      </div>
      <div><label for="name" class="mb-1 block text-sm font-medium text-gray-700">名稱</label><input id="name" v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" :class="{ 'border-red-500': form.errors.name }" required maxlength="191" /></div>
      <div><label for="tao-name" class="mb-1 block text-sm font-medium text-gray-700">族語名稱</label><input id="tao-name" v-model="form.tao_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" maxlength="255" /></div>
      <div><label for="notes" class="mb-1 block text-sm font-medium text-gray-700">備註</label><textarea id="notes" v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" /></div>
      <p v-if="firstError" class="text-red-600">{{ firstError }}</p>
      <div class="flex items-center gap-4">
        <button class="rounded-lg bg-blue-600 px-4 py-3 text-white" :disabled="form.processing">新增</button>
        <Link href="/admin/places" class="text-sm text-gray-600">取消</Link>
      </div>
    </form>
  </AdminLayout>
</template>
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { firstValidationError } from '@/utils/validationErrors'
defineProps({ tribes: Array })
const form = useForm({ tribe: '', name: '', tao_name: '', notes: '' })
const firstError = computed(() => firstValidationError(form.errors))
</script>
