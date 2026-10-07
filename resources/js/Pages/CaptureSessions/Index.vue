<template>
  <Head title="捕獲情境" /><FishAppLayout pageTitle="捕獲情境" mobileBackUrl="/workspace" mobileBackText="工作區">
    <div class="flex justify-end mb-4"><Link href="/capture-sessions/create" class="px-4 py-3 rounded-lg bg-teal-600 text-white font-bold">新增情境</Link></div>
    <div class="space-y-3">
      <article v-for="session in sessions.data" :key="session.id" class="bg-white border rounded-xl p-4">
        <div class="flex justify-between gap-4"><div><h2 class="font-bold">{{ locationLabel(session) }}</h2><p class="text-sm text-gray-600">{{ session.capture_date }} · {{ session.tribe }} · {{ session.capture_method }}</p><p class="text-sm">{{ session.record_count }} 筆</p></div><div class="flex gap-2"><Link :href="`/capture-sessions/${session.id}/edit`" class="text-blue-600">編輯</Link><Link v-if="session.can_delete" :href="`/capture-sessions/${session.id}`" method="delete" as="button" class="text-red-600">刪除</Link><span v-else class="text-xs text-gray-500">已有紀錄（含已刪除），不能刪除</span></div></div>
      </article>
      <p v-if="!sessions.data.length" class="text-gray-500">尚無捕獲情境</p>
    </div>
  </FishAppLayout>
</template>
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
defineProps({ sessions: { type: Object, required: true } })
function locationLabel(session) { if (session.place) return session.place.name; if (session.location_hint) return `地點未填（舊紀錄：${session.location_hint}）`; return '地點未填' }
</script>
