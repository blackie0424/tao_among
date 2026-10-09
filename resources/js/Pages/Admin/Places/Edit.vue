<template>
  <Head title="編輯地名" />
  <AdminLayout title="編輯地名">
    <form class="max-w-xl space-y-4 rounded-xl border bg-white p-6" @submit.prevent="form.put(`/admin/places/${place.id}`)">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">部落</label>
        <select v-model="form.tribe" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
          <option :value="null">共用</option>
          <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
        </select>
      </div>
      <div><label class="mb-1 block text-sm font-medium text-gray-700">名稱</label><input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" :class="{ 'border-red-500': form.errors.name }" /></div>
      <div><label class="mb-1 block text-sm font-medium text-gray-700">族語名稱</label><input v-model="form.tao_name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" /></div>
      <div><label class="mb-1 block text-sm font-medium text-gray-700">備註</label><textarea v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" /></div>
      <p v-if="firstError" class="text-red-600">{{ firstError }}</p>
      <button class="rounded-lg bg-blue-600 px-4 py-3 text-white">儲存{{ place.is_provisional ? '並確認' : '' }}</button>
    </form>
    <form class="mt-6 max-w-xl space-y-3 rounded-xl border bg-white p-6" @submit.prevent="mergeForm.post(`/admin/places/${place.id}/merge`)">
      <h2 class="font-bold">併入既有地名</h2>
      <select v-model="mergeForm.target_place_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
        <option value="" disabled>請選擇目標地名</option>
        <option v-for="target in mergeTargets" :key="target.id" :value="target.id">{{ target.tribe || '共用' }}｜{{ target.name }}</option>
      </select>
      <p v-if="mergeError" class="text-red-600">{{ mergeError }}</p>
      <button class="rounded-lg bg-amber-600 px-4 py-3 text-white">併入</button>
    </form>
  </AdminLayout>
</template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { firstValidationError } from '@/utils/validationErrors'
const props = defineProps({ place: Object, tribes: Array, mergeTargets: Array })
const form = useForm({ tribe: props.place.tribe, name: props.place.name, tao_name: props.place.tao_name || '', notes: props.place.notes || '' })
const mergeForm = useForm({ target_place_id: '' })
const firstError = computed(() => firstValidationError(form.errors))
const mergeError = computed(() => firstValidationError(mergeForm.errors))
</script>
