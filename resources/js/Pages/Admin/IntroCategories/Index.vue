<template>
  <Head title="投影片分類管理" />

  <AdminLayout title="投影片分類管理">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">投影片分類管理</h1>
        <p class="mt-1 text-sm text-gray-500">管理首頁投影片的分類標籤。</p>
      </div>
      <Link
        href="/admin/intro-categories/create"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增分類
      </Link>
    </div>

    <div v-if="categories.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">名稱</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">排序</th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="category in categories" :key="category.id">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ category.name }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ category.sort_order }}</td>
            <td class="px-6 py-4 text-right text-sm">
              <Link :href="`/admin/intro-categories/${category.id}/edit`" class="text-blue-600 hover:text-blue-700 mr-4">
                編輯
              </Link>
              <button
                class="text-red-600 hover:text-red-700"
                @click="destroy(category.id)"
              >
                刪除
              </button>
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
  categories: Array,
})

function destroy(id) {
  if (!confirm('確定刪除此分類？')) return
  router.delete(`/admin/intro-categories/${id}`)
}
</script>
