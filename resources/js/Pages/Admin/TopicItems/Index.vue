<template>
  <Head title="知識項目管理" />

  <AdminLayout title="知識項目管理">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">知識項目管理</h1>
        <p class="mt-1 text-sm text-gray-500">管理知識分類底下的項目內容。</p>
      </div>
      <Link
        href="/admin/knowledge-items/create"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增項目
      </Link>
    </div>

    <!-- 分類篩選 -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-gray-700 mb-2">篩選分類</label>
      <div class="flex gap-2 flex-wrap">
        <Link
          href="/admin/knowledge-items"
          class="rounded-lg px-4 py-2 text-sm font-medium transition"
          :class="!selectedCategoryId ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        >
          全部
        </Link>
        <Link
          v-for="category in categories"
          :key="category.id"
          :href="`/admin/knowledge-items?category_id=${category.id}`"
          class="rounded-lg px-4 py-2 text-sm font-medium transition"
          :class="selectedCategoryId === category.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
        >
          {{ category.title }}
        </Link>
      </div>
    </div>

    <div v-if="items.data.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">標題</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">分類</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">排序</th>
            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">發布</th>
            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="item in items.data" :key="item.id">
            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ item.title }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">
                {{ item.category?.title }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
              <div class="flex items-center gap-2">
                <span>{{ item.sort_order }}</span>
                <div class="flex gap-1">
                  <button
                    @click="moveUp(item.id)"
                    class="rounded px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 text-gray-600"
                    title="上移"
                  >
                    ↑
                  </button>
                  <button
                    @click="moveDown(item.id)"
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
                :class="item.is_published ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                @click="togglePublished(item.id)"
              >
                {{ item.is_published ? '已發布' : '草稿' }}
              </button>
            </td>
            <td class="px-6 py-4 text-right text-sm">
              <Link :href="`/admin/knowledge-items/${item.id}/edit`" class="text-blue-600 hover:text-blue-700 mr-4">
                編輯
              </Link>
              <button
                class="text-red-600 hover:text-red-700"
                @click="destroy(item.id)"
              >
                刪除
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="items.last_page > 1" class="flex justify-center gap-2 border-t border-gray-200 px-6 py-4">
        <Link
          v-for="page in items.last_page"
          :key="page"
          :href="getPaginationUrl(page)"
          class="rounded px-3 py-1 text-sm"
          :class="page === items.current_page ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
        >
          {{ page }}
        </Link>
      </div>
    </div>

    <div
      v-else
      class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-400"
    >
      尚未建立知識項目
    </div>
  </AdminLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  items: Object,
  categories: Array,
  selectedCategoryId: Number,
})

function togglePublished(id) {
  router.patch(`/admin/knowledge-items/${id}/toggle-published`, {}, {
    preserveScroll: true,
  })
}

function moveUp(id) {
  router.patch(`/admin/knowledge-items/${id}/move-up`, {}, {
    preserveScroll: true,
  })
}

function moveDown(id) {
  router.patch(`/admin/knowledge-items/${id}/move-down`, {}, {
    preserveScroll: true,
  })
}

function destroy(id) {
  if (!confirm('確定刪除此項目？')) return
  router.delete(`/admin/knowledge-items/${id}`)
}

function getPaginationUrl(page) {
  const baseUrl = `/admin/knowledge-items?page=${page}`
  return props.selectedCategoryId ? `${baseUrl}&category_id=${props.selectedCategoryId}` : baseUrl
}
</script>
