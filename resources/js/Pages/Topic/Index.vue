<template>
  <Head :title="topic.title" />

  <FishAppLayout :pageTitle="topic.title" :mobileBackText="topic.title" mobileBackUrl="/">
    <div class="mx-auto max-w-4xl px-4 py-6">
      
      <!-- 分類標題 -->
      <div class="mb-6 text-center">
        <h1 class="text-elder-title font-bold text-gray-900">{{ topic.title }}</h1>
      </div>

      <!-- 項目列表 -->
      <div v-if="items.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <ItemCard
          v-for="(item, index) in items"
          :key="item.id"
          :href="`/topics/${topic.slug}/${item.id}`"
          :imageUrl="item.image_url"
          :title="item.title"
          :index="index"
        />
      </div>

      <!-- 空狀態 -->
      <div v-else class="text-center py-12">
        <p class="text-gray-500 text-elder-body">目前沒有任何項目</p>
      </div>

    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import ItemCard from '@/Components/UI/ItemCard.vue'

defineProps({
  topic: {
    type: Object,
    required: true,
  },
  items: {
    type: Array,
    default: () => [],
  },
})
</script>
