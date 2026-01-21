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
      <section v-if="showTribalClassifications && tribalClassifications?.length">
        <TribalClassificationSummary 
          :classifications="tribalClassifications" 
          :fishId="fish.id" 
        />
      </section>
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
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'

// 從 Inertia page props 取得資料
const page = usePage()
const fish = computed(() => page.props.fish)
const tribalClassifications = computed(() => page.props.tribalClassifications || [])

// Props 定義
defineProps({
  showTribalClassifications: {
    type: Boolean,
    default: true
  },
  hideLeftOnMobile: {
    type: Boolean,
    default: false
  }
})
</script>
