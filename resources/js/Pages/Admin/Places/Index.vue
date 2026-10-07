<template>
  <Head title="地名管理" />
  <AdminLayout title="地名管理">
    <div v-if="places.data.length" class="space-y-3">
      <article v-for="place in places.data" :key="place.id" class="flex items-center justify-between gap-4 rounded-xl border bg-white p-4">
        <div>
          <h2 class="font-bold">{{ place.name }}</h2>
          <p v-if="place.tao_name" class="text-gray-600">{{ place.tao_name }}</p>
          <p class="text-sm">使用中 {{ place.capture_sessions_count }} 次出海</p>
        </div>
        <div data-testid="place-actions" class="flex items-center gap-3 text-sm">
          <Link :href="`/admin/places/${place.id}/edit`" class="px-2 py-1 text-blue-600 hover:text-blue-700">編輯</Link>
          <Link v-if="place.capture_sessions_count === 0" :href="`/admin/places/${place.id}`" method="delete" as="button" class="px-2 py-1 text-red-600 hover:text-red-700">刪除</Link>
        </div>
      </article>
    </div>
    <div v-else data-testid="places-empty-state" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
      <h2 class="text-lg font-bold text-gray-700">尚無地名</h2>
      <p class="mt-2 text-sm text-gray-500">地名會在新增或編輯情境時建立</p>
      <Link href="/capture-sessions/create" class="mt-4 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">新增情境</Link>
    </div>
  </AdminLayout>
</template>
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
defineProps({ places: { type: Object, required: true } })
</script>
