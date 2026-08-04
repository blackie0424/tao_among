<template>
  <Head title="首頁投影片管理" />

  <AdminLayout title="首頁投影片管理">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">首頁投影片管理</h1>
        <p class="mt-1 text-sm text-gray-500">管理首頁展示的投影片內容。</p>
      </div>
      <Link
        href="/admin/intro-slides/create"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增投影片
      </Link>
    </div>

    <div v-if="slides.data.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
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
          <tr v-for="slide in slides.data" :key="slide.id">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ slide.title }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="slide.media_type === 'youtube' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'"
              >
                {{ slide.media_type === 'youtube' ? 'YouTube' : '圖片' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ slide.sort_order }}</td>
            <td class="px-6 py-4 text-sm">
              <button
                class="rounded-full px-2 py-0.5 text-xs font-medium transition"
                :class="slide.is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                @click="togglePublished(slide.id)"
              >
                {{ slide.is_published ? '已發布' : '草稿' }}
              </button>
            </td>
            <td class="px-6 py-4 text-right text-sm">
              <Link :href="`/admin/intro-slides/${slide.id}/edit`" class="text-blue-600 hover:text-blue-700 mr-4">
                編輯
              </Link>
              <button
                class="text-red-600 hover:text-red-700"
                @click="destroy(slide.id)"
              >
                刪除
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="slides.last_page > 1" class="flex justify-center gap-2 border-t border-gray-200 px-6 py-4">
        <Link
          v-for="page in slides.last_page"
          :key="page"
          :href="`/admin/intro-slides?page=${page}`"
          class="rounded px-3 py-1 text-sm"
          :class="page === slides.current_page ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
        >
          {{ page }}
        </Link>
      </div>
    </div>

    <div
      v-else
      class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-400"
    >
      尚未建立投影片
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  slides: Object,
})

function togglePublished(id) {
  router.patch(`/admin/intro-slides/${id}/toggle-published`)
}

function destroy(id) {
  if (!confirm('確定刪除此投影片？')) return
  router.delete(`/admin/intro-slides/${id}`)
}
</script>
