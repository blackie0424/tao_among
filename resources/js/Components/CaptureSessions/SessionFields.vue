<template>
  <div class="space-y-5">
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">日期</label>
      <input v-model="form.capture_date" type="date" :max="today" :disabled="disabled" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100" :class="{ 'border-red-500': errors.capture_date }" required />
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">部落</label>
      <select v-model="form.tribe" :disabled="disabled" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100" :class="{ 'border-red-500': errors.tribe }" required>
        <option value="" disabled>請選擇</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
      </select>
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">捕獲方式</label>
      <select v-model="form.capture_method" :disabled="disabled" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100" :class="{ 'border-red-500': errors.capture_method }" required>
        <option value="" disabled>請選擇</option>
        <option v-for="(label, key) in captureMethods" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>
    <PlacePicker v-model="form.place_id" v-model:place-name="form.place_name" :tribe="form.tribe" :initial-name="initialPlaceName" />
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">備註</label>
      <textarea v-model="form.notes" :disabled="disabled" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100" :class="{ 'border-red-500': errors.notes }" rows="3" />
    </div>
  </div>
</template>

<script setup>
import PlacePicker from '@/Components/CaptureSessions/PlacePicker.vue'
import { formatLocalDate } from '@/utils/localDate'

defineProps({
  form: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) },
  tribes: { type: Array, default: () => [] },
  captureMethods: { type: Object, default: () => ({}) },
  initialPlaceName: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const today = formatLocalDate()
</script>
