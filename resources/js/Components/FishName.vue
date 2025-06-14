<template>
  <div class="section section-name w-full max-w-3xl text-center p-4 rounded-lg shadow-custom mb-4 flex flex-col items-center">
    <div class="flex items-center justify-center w-full gap-2 mb-1">
      <div class="text text-xl text-secondary">ngaran no among</div>
    </div>
    <!-- 魚名與 icon 水平排列 -->
    <div class="section-title text-2xl font-bold text-primary mb-2 flex items-center justify-center w-full">
      <span>{{ name }}</span>
      <Volume class="ml-2" />
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue';  
defineProps({
  name: String
});
</script>