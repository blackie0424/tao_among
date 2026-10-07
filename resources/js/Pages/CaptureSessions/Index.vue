<template>
  <Head title="捕獲情境" />
  <FishAppLayout pageTitle="捕獲情境" mobileBackUrl="/workspace" mobileBackText="工作區">
    <div class="mb-4 flex justify-end"><Link href="/capture-sessions/create" class="rounded-lg bg-teal-600 px-4 py-3 font-bold text-white">新增情境</Link></div>
    <div class="space-y-3">
      <article v-for="session in sessions.data" :key="session.id" class="rounded-xl border bg-white p-4">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h2 class="font-bold">{{ locationLabel(session) }}</h2>
            <p class="text-sm text-gray-600">{{ session.capture_date }} · {{ session.tribe }} · {{ session.capture_method }}</p>
            <p class="text-sm">{{ session.record_count }} 筆</p>
          </div>
          <div data-testid="session-actions" class="flex items-center gap-3 text-sm">
            <Link :href="`/capture-sessions/${session.id}/edit`" class="px-2 py-1 text-blue-600 hover:text-blue-700">編輯</Link>
            <Link v-if="session.can_delete" :href="`/capture-sessions/${session.id}`" method="delete" as="button" class="px-2 py-1 text-red-600 hover:text-red-700">刪除</Link>
            <span v-else class="max-w-48 px-2 py-1 text-xs leading-5 text-gray-500">已有紀錄（含已刪除），不能刪除</span>
          </div>
        </div>
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
