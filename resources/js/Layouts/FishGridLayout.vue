<template>
  <div class="lg:grid lg:grid-cols-3 lg:gap-8 lg:items-start">
    
    <!-- 左欄：核心識別 (Desktop Sticky) -->
    <div :class="[
      'space-y-6 lg:sticky lg:top-20',
      hideLeftOnMobile ? 'hidden lg:block' : ''
    ]">
      <section>
        <FishDetailLeft :fish="fish" />
      </section>
      <!-- 左欄額外內容插槽 - 由頁面（Fish.vue, MediaManager.vue, KnowledgeManager.vue）自行決定要顯示什麼 -->
      <slot name="left-extra" />
    </div>

    <!-- 中欄：主要內容 (Desktop Scrollable) -->
    <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:px-2 scrollbar-hide">
      <slot name="middle" />
    </div>

    <!-- 右欄：次要內容 (Desktop Scrollable) -->
    <div class="space-y-6 mt-6 lg:mt-0 lg:h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pl-2 scrollbar-hide">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'

// 從 Inertia page props 取得資料
const page = usePage()
const fish = computed(() => page.props.fish)

// Props 定義
defineProps({
  hideLeftOnMobile: {
    type: Boolean,
    default: false
  }
})
</script>
