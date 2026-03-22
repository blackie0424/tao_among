<template>
  <div class="space-y-8 md:space-y-10 w-full">
    
    <!-- 頂部：核心識別 -->
    <div :class="[
      'w-full',
      hideLeftOnMobile ? 'hidden lg:block' : ''
    ]">
      <section>
        <FishDetailLeft :fish="fish" />
      </section>
      <!-- 左欄額外內容插槽 - 由頁面自行決定要顯示什麼 -->
      <div v-if="$slots['left-extra']" class="mt-6">
        <slot name="left-extra" />
      </div>
    </div>

    <!-- 中部：主要內容 -->
    <div v-if="$slots['middle']" class="w-full">
      <slot name="middle" />
    </div>

    <!-- 底部：次要內容 -->
    <div v-if="$slots['right']" class="w-full">
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
