<template>
<div class="min-h-screen bg-gray-50 pb-[calc(6rem+env(safe-area-inset-bottom))] lg:pb-6 relative">
  
  <slot name="header"></slot>

   
    <main class="container mx-auto max-w-7xl px-4 py-6">
       <!-- 全局 Flash Message -->
    <FlashMessage />

    <!-- 頁面內容 -->

      <slot />
    </main>

    <slot name="bottom-nav" />

  </div>
</template>

<script setup>
import FlashMessage from '@/Components/FlashMessage.vue'
</script>

    
    
    

    
