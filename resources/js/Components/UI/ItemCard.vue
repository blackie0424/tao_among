<template>
  <div class="bg-white rounded-xl shadow-md overflow-hidden">
    <Link
      :href="href"
      class="block h-full group focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
      <!-- 圖片區域 - 完整貼合上半部 -->
      <div class="relative">
        <LazyImage
          :src="imageUrl"
          :alt="title"
          :imgIndex="index"
          wrapperClass="w-full h-[170px] flex items-center justify-center bg-gray-100"
          imgClass="w-full h-full object-cover"
          :imgStyle="imageStyle"
        />
      </div>
      <!-- 文字資訊區域 - 白色背景帶 padding -->
      <div class="p-4 bg-white">
        <div class="flex items-center justify-between min-h-touch-primary">
          <div
            class="text-elder-name font-semibold truncate tracking-wide group-hover:text-blue-600 flex-1 min-w-0"
          >
            {{ title }}
          </div>
          <!-- 音檔播放按鈕靠右 (選擇性) -->
          <div 
            v-if="audioUrl" 
            class="ml-2 flex-shrink-0 flex flex-col items-center gap-0.5" 
            @click.stop.prevent
          >
            <Volume :audioUrl="audioUrl" :large="true" />
            <span class="text-[11px] text-gray-500 leading-none">聽發音</span>
          </div>
        </div>
      </div>
    </Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import LazyImage from '@/Components/UI/LazyImage.vue'
import Volume from '@/Components/UI/Volume.vue'

defineProps({
  href: {
    type: String,
    required: true,
  },
  imageUrl: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  imageStyle: {
    type: Object,
    default: () => ({}),
  },
  audioUrl: {
    type: String,
    default: null,
  },
  index: {
    type: Number,
    default: 0,
  },
})
</script>
