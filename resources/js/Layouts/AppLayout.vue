<template>
  <div>
    <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 頁面內容 -->
    <slot />
  </div>
</template>

<script setup>
import FlashMessage from '@/Components/FlashMessage.vue'
</script>
