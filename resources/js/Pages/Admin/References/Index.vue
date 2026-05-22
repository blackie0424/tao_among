<template>
  <Head title="文獻管理" />

  <FishAppLayout pageTitle="文獻管理" mobileBackUrl="/dashboard" mobileBackText="統計面板">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">文獻管理</h1>
        <p class="mt-1 text-sm text-gray-500">維護可供文獻知識引用的文獻主檔。</p>
      </div>
      <Link
        href="/admin/references/create"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
      >
        新增文獻
      </Link>
    </div>

    <div v-if="references.data.length" class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="reference in references.data"
        :key="reference.id"
        data-testid="reference-card"
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
      >
        <div class="flex justify-center bg-gray-50 px-4 pt-4">
          <div
            data-testid="reference-cover"
            class="aspect-[3/4] w-1/2 min-w-[96px] max-w-[144px] overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
          >
            <LazyImage
              v-if="reference.image_url"
              :src="reference.image_url"
              :alt="reference.name"
              wrapperClass="h-full w-full"
              imgClass="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full items-center justify-center text-sm font-medium text-gray-400"
            >
              暫無封面
            </div>
          </div>
        </div>
        <div class="space-y-3 p-4">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-bold text-gray-900">{{ reference.name }}</h2>
              <p class="mt-1 text-sm text-gray-500">{{ reference.author }}</p>
            </div>
            <span
              class="rounded-full px-2.5 py-1 text-xs font-medium"
              :class="reference.status === 'enabled' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
            >
              {{ reference.status === 'enabled' ? '啟用' : '停用' }}
            </span>
          </div>
          <div class="flex items-center justify-between gap-3 text-sm">
            <a
              v-if="reference.external_url"
              :href="reference.external_url"
              target="_blank"
              rel="noreferrer"
              class="text-blue-600 hover:text-blue-700"
            >
              查看連結
            </a>
            <span v-else class="text-gray-400">無外部連結</span>
            <Link :href="`/admin/references/${reference.id}/edit`" class="text-blue-600 hover:text-blue-700">
              編輯
            </Link>
          </div>
        </div>
      </article>
    </div>

    <div
      v-else
      class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-400"
    >
      尚未建立文獻資料
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import LazyImage from '@/Components/UI/LazyImage.vue'

defineProps({
  references: Object,
})
</script>
