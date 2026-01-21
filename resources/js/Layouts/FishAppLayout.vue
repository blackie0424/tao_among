<template>
  <div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6 relative">
    
    <!-- 頂部導覽列 -->
    <AppHeader>
      <template #mobile-title>{{ pageTitle }}</template>
      <template #desktop-breadcrumb>{{ fish?.name }}</template>
    </AppHeader>

    <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 主要內容區域 -->
    <main class="container mx-auto max-w-7xl px-4 py-6">
      <slot />
    </main>

    <!-- 底部導覽列 (手機版) -->
    <BottomNavBar :fishId="fishId" :activeTab="activeTab" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppHeader from '@/Components/Global/AppHeader.vue'
import BottomNavBar from '@/Components/Global/BottomNavBar.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

// 從 Inertia page props 取得 fish 資料
const page = usePage()
const fish = computed(() => page.props.fish)

// Props 定義
const props = defineProps({
  pageTitle: {
    type: String,
    default: '基本資料'
  },
  activeTab: {
    type: String,
    default: 'basic' // 'basic' | 'media' | 'knowledge'
  }
})

// 計算 fishId，優先使用 fish 物件
const fishId = computed(() => fish.value?.id)
</script>
