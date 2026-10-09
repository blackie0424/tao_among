<template>
  <form class="space-y-5" @submit.prevent="$emit('submit')">
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">日期</label>
      <input v-model="form.capture_date" type="date" :max="today" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.capture_date }" required />
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">部落</label>
      <select v-model="form.tribe" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.tribe }" required>
        <option value="" disabled>請選擇</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
      </select>
    </div>
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">捕獲方式</label>
      <select v-model="form.capture_method" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.capture_method }" required>
        <option value="" disabled>請選擇</option>
        <option v-for="(label, key) in captureMethods" :key="key" :value="key">{{ label }}</option>
      </select>
    </div>
    <PlacePicker v-model="form.place_id" v-model:place-name="form.place_name" :tribe="form.tribe" :initial-name="initialPlaceName" />
    <div>
      <label class="mb-1 block text-sm font-medium text-gray-700">備註</label>
      <textarea v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.notes }" rows="3" />
    </div>
    <div v-if="firstError" role="alert" class="text-sm text-red-600">{{ firstError }}</div>
    <button type="submit" class="rounded-lg bg-teal-600 px-5 py-3 font-bold text-white" :disabled="form.processing">儲存</button>
  </form>
</template>
<script setup>
import { computed } from 'vue'
import PlacePicker from '@/Components/CaptureSessions/PlacePicker.vue'
import { formatLocalDate } from '@/utils/localDate'
import { firstValidationError } from '@/utils/validationErrors'
const props = defineProps({ form: { type: Object, required: true }, tribes: { type: Array, default: () => [] }, captureMethods: { type: Object, default: () => ({}) }, initialPlaceName: { type: String, default: '' } })
defineEmits(['submit'])
const today = formatLocalDate()
const firstError = computed(() => firstValidationError(props.form.errors))
</script>
