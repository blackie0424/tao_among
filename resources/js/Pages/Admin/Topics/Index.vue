<template>
  <Head title="知識分類管理" />

  <AdminLayout title="知識分類管理">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">知識分類管理</h1>
      <p class="mt-1 text-sm text-gray-500">管理首頁展示的知識分類卡片（固定 4 張）。</p>
    </div>

    <div v-if="topics.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">標題</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">類型</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">排序</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">發布</th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="topic in topics" :key="topic.id">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ topic.title }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="topic.is_fish_category ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
              >
                {{ topic.is_fish_category ? '魚類圖鑑' : '知識項目' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <div class="flex items-center gap-2">
                <span>{{ topic.sort_order }}</span>
                <div class="flex gap-1">
                  <button
                    v-if="topic.sort_order > 0"
                    @click="moveUp(topic.id)"
                    class="rounded px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600"
                    title="上移"
                  >
                    ↑
                  </button>
                  <button
                    v-if="topic.sort_order < topics.length - 1"
                    @click="moveDown(topic.id)"
                    class="rounded px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600"
                    title="下移"
                  >
                    ↓
                  </button>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 text-sm">
              <button
                class="rounded-full px-2 py-0.5 text-xs font-medium transition"
                :class="topic.is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                @click="togglePublished(topic.id)"
              >
                {{ topic.is_published ? '已發布' : '草稿' }}
              </button>
            </td>
            <td class="px-6 py-4 text-right text-sm">
              <Link :href="`/admin/topics/${topic.id}/edit`" class="text-blue-600 hover:text-blue-700">
                編輯
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-else
      class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-400"
    >
      尚未建立分類
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  topics: Array,
})

function togglePublished(id) {
  router.patch(`/admin/topics/${id}/toggle-published`)
}

function moveUp(id) {
  router.patch(`/admin/topics/${id}/move-up`)
}

function moveDown(id) {
  router.patch(`/admin/topics/${id}/move-down`)
}
</script>
