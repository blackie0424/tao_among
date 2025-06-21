<template>
  <div class="section section-name w-full max-w-3xl p-4 mb-4 flex flex-col items-end relative">
    <div class="flex items-center justify-center w-full mb-4">
      <div class="text text-xl text-secondary">ngaran no among</div>
    </div>
    <!-- 魚名與 icon 水平排列 -->
    <div
      class="section-title text-2xl font-bold text-primary flex items-center justify-center w-full"
    >
      <span>{{ fishName }}</span>
      <Volume class="ml-4" />
    </div>

    <OverflowMenu
      class="absolute right-0 top-0"
      :apiUrl="`/prefix/api/fish/${fishId}`"
      :redirectUrl="`/`"
    />
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue'
import OverflowMenu from '@/Components/OverflowMenu.vue'

defineProps({
  fishName: String,
  fishId: String,
})
</script>
