<template>
  <section class="space-y-4" data-testid="image-framing-editor">
    <h3 class="text-xl font-semibold text-gray-700">調整照片</h3>
    <p v-if="photoFailed" data-testid="framing-photo-failed" class="text-base text-red-600">
      照片讀不到，暫時無法調整。請稍後重新整理，或更換照片。
    </p>
    <p v-else class="text-base text-gray-600">用手指拖曳照片，讓魚在框的中間</p>

    <!-- 調整框（4:3）：拖曳移動、雙指放大；十字虛線的交點是魚身中心 -->
    <div
      ref="frameEl"
      data-testid="framing-frame"
      class="relative aspect-[4/3] w-full overflow-hidden rounded-lg border-4 border-brand bg-gray-100 touch-none select-none cursor-grab"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerEnd"
      @pointercancel="onPointerEnd"
    >
      <FramedImage
        :src="src"
        alt="當前捕獲照片"
        :framing="modelValue"
        wrapperClass="absolute inset-0 w-full h-full"
        @loaded="photo = $event"
        @failed="onPhotoFailed"
      />
      <div class="pointer-events-none absolute inset-y-0 left-1/2 border-l border-dashed border-white/90"></div>
      <div class="pointer-events-none absolute inset-x-0 top-1/2 border-t border-dashed border-white/90"></div>
      <div
        data-testid="zoom-label"
        class="pointer-events-none absolute bottom-2 right-2 rounded-full bg-black/60 px-2 text-base text-white"
      >
        {{ zoom.toFixed(1) }}x
      </div>
    </div>

    <div>
      <label for="framing_zoom" class="block text-xl font-medium text-gray-700 mb-2">放大</label>
      <input
        id="framing_zoom"
        data-testid="zoom-slider"
        type="range"
        :min="MIN_ZOOM"
        :max="MAX_ZOOM"
        step="0.1"
        :value="zoom"
        :disabled="photoFailed"
        class="w-full"
        @input="onZoomInput"
      />
      <div class="flex justify-between text-sm text-gray-500"><span>1.0x</span><span>3.0x</span></div>
    </div>

    <div class="flex flex-wrap gap-3">
      <button type="button" data-testid="rotate-left" :disabled="photoFailed" class="min-h-touch-primary rounded-lg border-2 border-brand px-4 text-xl text-brand" @click="rotate(-1)">⟲ 向左轉</button>
      <button type="button" data-testid="rotate-right" :disabled="photoFailed" class="min-h-touch-primary rounded-lg border-2 border-brand px-4 text-xl text-brand" @click="rotate(1)">向右轉 ⟳</button>
      <button type="button" data-testid="framing-reset" class="min-h-touch-primary rounded-lg border-2 border-gray-400 px-4 text-xl text-gray-700" @click="reset">還原</button>
    </div>

    <div>
      <p class="text-xl font-semibold text-gray-700 mb-2">實際會看到的樣子（即時）</p>
      <div class="flex items-start gap-3">
        <div class="flex-1">
          <div class="aspect-[4/3] overflow-hidden rounded-lg bg-gray-100">
            <FramedImage data-testid="preview-4x3" :src="src" alt="4:3 預覽" :framing="modelValue" wrapperClass="w-full h-full" />
          </div>
          <p class="mt-1 text-sm text-gray-600">列表卡片、捕獲紀錄、魚島（4:3）</p>
        </div>
        <div class="flex-1">
          <div class="aspect-video overflow-hidden rounded-lg bg-gray-100">
            <FramedImage data-testid="preview-16x9" :src="src" alt="16:9 預覽" :framing="modelValue" wrapperClass="w-full h-full" />
          </div>
          <p class="mt-1 text-sm text-gray-600">魚頁（16:9）</p>
        </div>
      </div>
      <p class="mt-2 text-sm text-gray-500">各頁的框大小不一，這裡是代表性的比例；按「更新」才會存檔。</p>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import FramedImage from '@/Components/UI/FramedImage.vue'
import {
  MAX_ZOOM,
  MIN_ZOOM,
  dragFraming,
  rotateFraming,
  zoomFraming,
} from '@/composables/useImageFraming'

/**
 * 調整照片：拖曳移動、雙指或滑桿放大、旋轉、還原。
 * v-model 是 { x, y, zoom, rotation }；null＝沒調整。原圖不動，只改這四個數字。
 */
const props = defineProps({
  src: { type: String, required: true },
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const frameEl = ref(null)
const photo = ref(null)
const photoFailed = ref(false)
const pointers = new Map()
let gesture = null

const zoom = computed(() => props.modelValue?.zoom ?? MIN_ZOOM)

const frameSize = () => ({ width: frameEl.value.clientWidth, height: frameEl.value.clientHeight })
const ready = () => !photoFailed.value && !!photo.value && frameEl.value?.clientWidth > 0 && frameEl.value?.clientHeight > 0

function onPhotoFailed() {
  photo.value = null
  photoFailed.value = true
  pointers.clear()
  gesture = null
}

// 換照片：舊照片的尺寸與失敗狀態不能沿用
watch(
  () => props.src,
  () => {
    photo.value = null
    photoFailed.value = false
  }
)

function onPointerDown(event) {
  if (!ready()) return
  frameEl.value.setPointerCapture?.(event.pointerId)
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
  gesture = {
    framing: props.modelValue,
    start: [...pointers.values()],
  }
}

function onPointerMove(event) {
  if (!gesture || !pointers.has(event.pointerId)) return
  pointers.set(event.pointerId, { x: event.clientX, y: event.clientY })
  const current = [...pointers.values()]

  if (current.length === 1 && gesture.start.length === 1) {
    const dx = current[0].x - gesture.start[0].x
    const dy = current[0].y - gesture.start[0].y
    emit('update:modelValue', dragFraming(gesture.framing, photo.value, frameSize(), dx, dy))
  } else if (current.length >= 2 && gesture.start.length >= 2) {
    const ratio = distance(current) / distance(gesture.start)
    const baseZoom = gesture.framing?.zoom ?? MIN_ZOOM
    emit('update:modelValue', zoomFraming(gesture.framing, photo.value, frameSize(), baseZoom * ratio))
  }
}

function onPointerEnd(event) {
  pointers.delete(event.pointerId)
  gesture = pointers.size
    ? { framing: props.modelValue, start: [...pointers.values()] }
    : null
}

const distance = ([a, b]) => Math.hypot(a.x - b.x, a.y - b.y) || 1

function onZoomInput(event) {
  if (!ready()) return
  emit('update:modelValue', zoomFraming(props.modelValue, photo.value, frameSize(), Number(event.target.value)))
}

function rotate(direction) {
  emit('update:modelValue', rotateFraming(props.modelValue, direction))
}

function reset() {
  emit('update:modelValue', null)
}
</script>
