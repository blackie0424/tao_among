<template>
  <LazyImage
    ref="lazyImage"
    :src="src"
    :alt="alt"
    :imgIndex="imgIndex"
    :wrapperClass="wrapperClass"
    :wrapperStyle="frameStyle"
    :imgClass="imgClass"
    :imgStyle="imgStyle"
    @failed="onFailed"
    @loaded="onLoaded"
  />
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { computeFramedLayout, framedImageStyle, rotatedSize } from '@/composables/useImageFraming'

/**
 * 套用照片調整值（魚身中心、放大、旋轉）的圖片框。
 * 沒有調整值（framing 為 null）時，維持各頁原本的 cover 置中樣式。
 * ratio="photo"：框沒有固定高度（高度由照片決定）的頁面，框比例取旋轉後的照片比例。
 */
const props = defineProps({
  src: String,
  alt: String,
  framing: { type: Object, default: null },
  wrapperClass: { type: String, default: '' },
  imgClass: { type: String, default: 'w-full h-full object-cover' },
  imgIndex: { type: Number, default: 0 },
  ratio: { type: String, default: 'frame' },
})

const emit = defineEmits(['failed', 'loaded'])

const lazyImage = ref(null)
const photo = ref(null)
const failed = ref(false) // 原照片載入失敗：顯示的是預設圖，不能套原照片的調整值
const frame = ref(null)
let observer = null

function onLoaded(size) {
  // 原照片失敗後載入的是預設圖，它的尺寸不是這張照片的，不採用也不往外送
  if (failed.value) return
  photo.value = size
  emit('loaded', size)
}

function onFailed() {
  failed.value = true
  photo.value = null
  emit('failed')
}

// 換照片：舊照片的尺寸與失敗狀態都不能沿用，等新照片載入後再套用
watch(
  () => props.src,
  () => {
    photo.value = null
    failed.value = false
  }
)

const framingActive = computed(() => !!props.framing && !failed.value && !!photo.value)

function measureFrame() {
  const el = lazyImage.value?.$el
  if (el?.clientWidth && el?.clientHeight) {
    frame.value = { width: el.clientWidth, height: el.clientHeight }
  }
}

onMounted(() => {
  measureFrame()
  const el = lazyImage.value?.$el
  if (el && typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(measureFrame)
    observer.observe(el)
  }
})

onBeforeUnmount(() => observer?.disconnect())

const frameStyle = computed(() => {
  if (props.ratio !== 'photo' || !framingActive.value) return ''

  const { width, height } = rotatedSize(photo.value.width, photo.value.height, props.framing.rotation)
  return { aspectRatio: `${width} / ${height}` }
})

const imgStyle = computed(() => {
  if (!framingActive.value || !frame.value) return ''

  return framedImageStyle(
    computeFramedLayout({
      photoWidth: photo.value.width,
      photoHeight: photo.value.height,
      frameWidth: frame.value.width,
      frameHeight: frame.value.height,
      framing: props.framing,
    })
  )
})
</script>
