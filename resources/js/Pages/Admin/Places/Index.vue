<template><Head title="地名管理" /><AdminLayout title="地名管理"><div class="space-y-3"><article v-for="place in places.data" :key="place.id" class="bg-white border rounded-xl p-4 flex justify-between"><div><h2 class="font-bold">{{ place.name }}</h2><p v-if="place.tao_name" class="text-gray-600">{{ place.tao_name }}</p><p class="text-sm">使用中 {{ place.capture_sessions_count }} 次出海</p></div><div class="flex gap-3"><Link :href="`/admin/places/${place.id}/edit`" class="text-blue-600">編輯</Link><Link v-if="place.capture_sessions_count === 0" :href="`/admin/places/${place.id}`" method="delete" as="button" class="text-red-600">刪除</Link></div></article></div></AdminLayout></template>
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
defineProps({ places: { type: Object, required: true } })
</script>
