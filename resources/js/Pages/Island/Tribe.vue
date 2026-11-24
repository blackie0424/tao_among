<template>
  <Head :title="`魚島：${islandTribeLabel(tribe)}`" />

  <IslandLayout>
    <template #header>
      <h1 class="text-elder-name font-extrabold">{{ islandTribeLabel(tribe) }}</h1>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>
  </IslandLayout>
</template>

<script setup>
// 第 1 步只放頁面骨架，魚卡清單在第 2 步
import { Head } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
})
</script>
