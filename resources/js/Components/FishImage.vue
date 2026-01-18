<template>
  <div
    class="show_image w-full max-w-3xl mx-auto flex items-center justify-center mb-6 p-4 rounded-lg shadow-custom h-60"
  >
    <!-- 使用 picture 標籤支援響應式圖片 -->
    <picture v-if="responsiveUrls">
      <!-- 手機版本：< 768px -->
      <source :srcset="responsiveUrls.mobile" media="(max-width: 767px)" type="image/webp" />
      <!-- 平板版本：768px - 1023px -->
      <source
        :srcset="responsiveUrls.tablet"
        media="(min-width: 768px) and (max-width: 1023px)"
        type="image/webp"
      />
      <!-- 桌機版本：>= 1024px -->
      <source :srcset="responsiveUrls.desktop" media="(min-width: 1024px)" type="image/webp" />
      <!-- fallback img -->
      <img
        :src="image"
        :alt="name"
        loading="lazy"
        class="max-h-full max-w-full object-contain rounded-lg"
      />
    </picture>
    <!-- 非響應式圖片使用一般 img 標籤 -->
    <img
      v-else
      :src="image"
      :alt="name"
      loading="lazy"
      class="max-h-full max-w-full object-contain rounded-lg"
    />
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { getResponsiveImageUrls, isResponsiveWebp } from '@/composables/useResponsiveImage.js'

const props = defineProps({
  image: String,
  name: String,
})

/**
 * 計算響應式圖片 URL 集合
 */
const responsiveUrls = computed(() => {
  if (!isResponsiveWebp(props.image)) {
    return null
  }
  return getResponsiveImageUrls(props.image)
})
</script>
