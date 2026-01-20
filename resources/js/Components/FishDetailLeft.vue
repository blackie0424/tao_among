<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <!-- 手機使用較小底距，桌面維持較大空間以保持排版 -->
    <div class="pb-6 md:pb-20 w-full relative">
      <div class="relative rounded-lg overflow-hidden shadow-sm">
        <LazyImage
          :src="fish.display_image_url || fish.image_url"
          :alt="fish.name"
          wrapperClass="fish-image-wrapper aspect-[4/3] w-full bg-gray-100"
          imgClass="fish-image w-full h-full object-cover"
        />
        
        <!-- 發音按鈕浮動於右下角 -->
        <div v-if="fish.audio_url" class="absolute bottom-4 right-4 z-10">
          <div class="bg-white/80 backdrop-blur-sm rounded-full p-1 shadow-lg border border-white/50">
             <Volume :audioUrl="fish.audio_url" />
          </div>
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
