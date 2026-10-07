<template>
  <form class="space-y-5" @submit.prevent="$emit('submit')">
    <div><label class="block text-sm font-medium">日期</label><input v-model="form.capture_date" type="date" class="w-full rounded-lg border-gray-300" required /></div>
    <div><label class="block text-sm font-medium">部落</label><select v-model="form.tribe" class="w-full rounded-lg border-gray-300" required><option value="" disabled>請選擇</option><option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option></select></div>
    <div><label class="block text-sm font-medium">捕獲方式</label><select v-model="form.capture_method" class="w-full rounded-lg border-gray-300" required><option value="" disabled>請選擇</option><option v-for="(label, key) in captureMethods" :key="key" :value="key">{{ label }}</option></select></div>
    <PlacePicker v-model="form.place_id" :initial-name="initialPlaceName" />
    <div><label class="block text-sm font-medium">備註</label><textarea v-model="form.notes" class="w-full rounded-lg border-gray-300" rows="3" /></div>
    <div v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-600">{{ Object.values(form.errors)[0] }}</div>
    <button type="submit" class="px-5 py-3 rounded-lg bg-teal-600 text-white font-bold" :disabled="form.processing">儲存</button>
  </form>
</template>
<script setup>
import PlacePicker from '@/Components/CaptureSessions/PlacePicker.vue'
defineProps({ form: { type: Object, required: true }, tribes: { type: Array, default: () => [] }, captureMethods: { type: Object, default: () => ({}) }, initialPlaceName: { type: String, default: '' } })
defineEmits(['submit'])
</script>
