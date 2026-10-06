<template>
  <Head title="主題導覽" />

  <AdminLayout title="主題導覽">
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">主題導覽</h1>
      <p class="mt-1 text-base text-gray-600">選擇分類管理項目內容，或編輯分類的顯示設定。</p>
    </div>

    <div v-if="topics.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <article
        v-for="topic in topics"
        :key="topic.id"
        data-testid="topic-card"
        class="relative overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-blue-300 hover:shadow-md"
      >
        <Link
          data-testid="topic-primary-link"
          :href="topicTarget(topic)"
          class="absolute inset-0 z-0"
          :aria-label="topic.is_fish_category ? `前往前台魚類清單：${topic.title}` : `管理${topic.title}的項目`"
        />

        <div v-if="topic.image_url" class="h-40 bg-gray-100">
          <img :src="topic.image_url" :alt="topic.title" class="h-full w-full object-cover" />
        </div>
        <div v-else data-testid="topic-image-placeholder" class="h-40 bg-gray-100" aria-hidden="true" />

        <div class="p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-xl font-bold text-gray-900">{{ topic.title }}</h2>
              <p class="mt-1 text-base font-medium" :class="topic.is_published ? `text-green-700` : `text-gray-500`">
                {{ topic.is_published ? `已發布` : `草稿` }} · {{ topic.items_count }} 筆項目
              </p>
              <p v-if="topic.is_fish_category" class="mt-2 text-base font-bold text-blue-700">前往前台魚類清單</p>
            </div>

            <Link
              data-testid="topic-edit-link"
              :href="`/admin/topics/${topic.id}/edit`"
              class="relative z-10 shrink-0 rounded-lg border border-blue-700 bg-white px-3 py-2 text-base font-bold text-blue-700 hover:bg-blue-50"
            >編輯分類</Link>
          </div>

          <div class="relative z-10 mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
            <button
              type="button"
              class="min-h-12 rounded-lg px-3 py-2 text-base font-bold transition"
              :class="topic.is_published ? `bg-green-100 text-green-700` : `bg-gray-100 text-gray-600`"
              @click="togglePublished(topic.id)"
            >{{ topic.is_published ? `設為草稿` : `發布` }}</button>
            <button
              v-if="topic.sort_order > 0"
              type="button"
              class="min-h-12 rounded-lg bg-gray-100 px-3 py-2 text-base font-bold text-gray-700 hover:bg-gray-200"
              @click="moveUp(topic.id)"
            >↑ 上移</button>
            <button
              v-if="topic.sort_order < topics.length - 1"
              type="button"
              class="min-h-12 rounded-lg bg-gray-100 px-3 py-2 text-base font-bold text-gray-700 hover:bg-gray-200"
              @click="moveDown(topic.id)"
            >↓ 下移</button>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-400">
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

function topicTarget(topic) {
  return topic.is_fish_category ? `/fishs` : `/admin/topic-items?topic_id=${topic.id}`
}

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
