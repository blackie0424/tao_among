<template>
  <div class="overflow-hidden">
    <Link
      :href="href"
      class="block h-full group focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
    >
      <!-- 圖片區域：無圖或載入失敗都顯示佔位區塊 -->
      <div
        v-if="imageUrl && !imageFailed"
        data-testid="item-image-wrapper"
        class="relative h-[190px] border border-rule-strong"
      >
        <LazyImage
          :src="imageUrl"
          :alt="title"
          :imgIndex="index"
          wrapperClass="w-full h-[190px] flex items-center justify-center"
          imgClass="w-full h-full object-cover"
          :imgStyle="imageStyle"
          @failed="imageFailed = true"
        />
      </div>
      <div
        v-else
        data-testid="item-image-placeholder"
        class="h-[190px] border border-rule-strong bg-rule"
        aria-hidden="true"
      ></div>
      <!-- 文字資訊區域：魚名下方一條細線 -->
      <div class="mt-2 border-b border-rule pb-1.5">
        <div class="flex items-center justify-between min-h-touch-primary">
          <div
            data-testid="item-title"
            class="text-elder-name font-serif truncate tracking-wide group-hover:text-brand flex-1 min-w-0"
          >
            {{ title }}
          </div>
          <!-- 音檔播放按鈕靠右 (選擇性) -->
          <div v-if="audioUrl" class="ml-2 flex-shrink-0" @click.stop.prevent>
            <Volume :audioUrl="audioUrl" :large="true" />
          </div>
        </div>
      </div>
    </Link>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import LazyImage from '@/Components/UI/LazyImage.vue'
import Volume from '@/Components/UI/Volume.vue'

const props = defineProps({
  href: {
    type: String,
    required: true,
  },
  imageUrl: {
    type: String,
    default: null,
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

const imageFailed = ref(false)
watch(
  () => props.imageUrl,
  () => {
    imageFailed.value = false
  }
)
</script>
