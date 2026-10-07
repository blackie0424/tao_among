<template><Head title="編輯地名" /><AdminLayout title="編輯地名"><form class="max-w-xl space-y-4 bg-white border rounded-xl p-6" @submit.prevent="form.put(`/admin/places/${place.id}`)"><div><label>名稱</label><input v-model="form.name" class="w-full rounded-lg border-gray-300" /></div><div><label>族語名稱</label><input v-model="form.tao_name" class="w-full rounded-lg border-gray-300" /></div><div><label>備註</label><textarea v-model="form.notes" class="w-full rounded-lg border-gray-300" /></div><p v-if="Object.keys(form.errors).length" class="text-red-600">{{ Object.values(form.errors)[0] }}</p><button class="px-4 py-3 bg-blue-600 text-white rounded-lg">儲存</button></form></AdminLayout></template>
<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
const props = defineProps({ place: Object })
const form = useForm({ name: props.place.name, tao_name: props.place.tao_name || '', notes: props.place.notes || '' })
</script>
