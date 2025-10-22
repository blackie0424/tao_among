<!-- filepath: resources/js/Components/FishDetailLeft.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <!-- 手機使用較小底距，桌面維持較大空間以保持排版 -->
    <div class="pb-6 md:pb-20">
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-col md:flex-row items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full">
            <FishName
              :fish-name="fish.name"
              :fish-id="fish.id"
              :audios="fish.audios"
              class="w-full max-w-2xl text-2xl mb-4"
            />
          </div>
        </div>
      </div>
      <LazyImage
        :src="fish.image"
        :alt="fish.name"
        wrapperClass="fish-image-wrapper"
        imgClass="fish-image"
      />
    </div>
  </div>
</template>

<script setup>
import FishName from '@/Components/FishName.vue'
import LazyImage from '@/Components/LazyImage.vue'

defineProps({
  fish: Object,
})
</script>
