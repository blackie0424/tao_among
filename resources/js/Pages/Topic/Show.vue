<template>
  <Head :title="item.title" />

  <FishAppLayout :pageTitle="item.title">
    <div class="mx-auto max-w-3xl px-4 py-6">
      
      <!-- 圖片 -->
      <div v-if="item.image_url" class="mb-6 rounded-xl overflow-hidden shadow-lg">
        <img
          :src="item.image_url"
          :alt="item.title"
          class="w-full max-h-96 object-cover"
        />
      </div>

      <!-- 標題 -->
      <h1 class="text-elder-title font-bold text-gray-900 mb-4 text-center">
        {{ item.title }}
      </h1>

      <!-- 說明文字 -->
      <div
        v-if="item.description"
        class="prose prose-lg max-w-none text-elder-body text-gray-700 leading-relaxed whitespace-pre-wrap"
      >
        {{ item.description }}
      </div>

      <!-- 無說明時的提示 -->
      <div v-else class="text-center text-gray-500 text-elder-body py-8">
        暫無詳細說明
      </div>

      <!-- 返回按鈕 -->
      <div class="mt-8 text-center">
        <Link
          :href="`/topics/${topic.slug}`"
          class="inline-flex items-center gap-2 px-6 py-3 bg-gray-100 hover:bg-gray-200 rounded-lg transition min-h-touch-primary"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          <span class="text-elder-body font-medium">返回列表</span>
        </Link>
      </div>

    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

defineProps({
  topic: {
    type: Object,
    required: true,
  },
  item: {
    type: Object,
    required: true,
  },
})
</script>
