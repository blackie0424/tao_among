<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <!-- 手機使用較小底距，桌面維持較大空間以保持排版 -->
    <div class="w-full relative bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <!-- 圖片區域：移除個別圓角，由外層容器控制 -->
      <div class="relative aspect-[4/3] bg-gray-100">
        <LazyImage
          :src="fish.display_image_url || fish.image_url"
          :alt="fish.name"
          wrapperClass="w-full h-full"
          imgClass="w-full h-full object-cover"
        />
      </div>

      <!-- 魚名與發音區塊：移除頂部圓角與邊框 -->
      <div class="flex items-center justify-between p-4 bg-white border-t border-gray-100">
         <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ fish.name }}</h1>
         <div v-if="fish.audio_url" class="flex-shrink-0">
            <Volume :audioUrl="fish.audio_url" />
         </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Volume from '@/Components/Volume.vue'
import LazyImage from '@/Components/LazyImage.vue'

defineProps({
  fish: Object,
})
</script>
