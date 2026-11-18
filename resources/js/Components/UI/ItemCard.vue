<template>
  <div
    :class="isFish ? 'overflow-hidden' : 'bg-white rounded-xl shadow-md overflow-hidden'"
    @click.capture="interceptIfGated"
  >
    <Link
      :href="href"
      class="block h-full group focus:outline-none"
      :class="isFish ? 'focus-visible:ring-4 focus-visible:ring-brand/30' : 'focus:ring-2 focus:ring-blue-500'"
    >
      <!-- 圖片區域：fish 版無圖或載入失敗都顯示佔位區塊 -->
      <div
        v-if="imageUrl && !(isFish && imageFailed)"
        data-testid="item-image-wrapper"
        class="relative"
        :class="isFish ? 'h-[190px] border border-rule-strong' : 'h-[170px]'"
      >
        <LazyImage
          :src="imageUrl"
          :alt="title"
          :imgIndex="index"
          :wrapperClass="
            isFish
              ? 'w-full h-[190px] flex items-center justify-center'
              : 'w-full h-[170px] flex items-center justify-center bg-gray-100'
          "
          imgClass="w-full h-full object-cover"
          :imgStyle="imageStyle"
          @failed="imageFailed = true"
        />
      </div>
      <div
        v-else
        data-testid="item-image-placeholder"
        :class="isFish ? 'h-[190px] border border-rule-strong bg-rule' : 'h-[170px] bg-gray-100'"
        aria-hidden="true"
      ></div>
      <!-- 文字資訊區域 -->
      <div :class="isFish ? 'mt-2 border-b border-rule pb-1.5' : 'p-4 bg-white'">
        <div class="flex items-center justify-between min-h-touch-primary">
          <div
            data-testid="item-title"
            class="text-elder-name truncate tracking-wide flex-1 min-w-0"
            :class="
              isFish
                ? 'font-serif group-hover:text-brand'
                : 'font-semibold group-hover:text-blue-600'
            "
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
import { computed, ref, watch } from 'vue'
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
  // 需要登入或開通才能看詳細頁：點擊不導頁，改 emit blocked 由頁面處理
  gated: { type: Boolean, default: false },
  // 'default'：原樣式（主題頁等）；'fish'：魚清單的博物誌樣式
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'fish'].includes(value),
  },
})

const emit = defineEmits(['blocked'])

const interceptIfGated = (event) => {
  if (!props.gated) return
  event.preventDefault()
  event.stopPropagation()
  emit('blocked', event.target.closest('a') ?? event.currentTarget)
}

const isFish = computed(() => props.variant === 'fish')

const imageFailed = ref(false)
watch(
  () => props.imageUrl,
  () => {
    imageFailed.value = false
  }
)
</script>
