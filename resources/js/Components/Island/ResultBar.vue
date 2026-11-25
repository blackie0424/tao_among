<template>
  <!-- 一條堆疊長條：每段的伸縮比例＝人數、最少 6px、段間 2px；文字不寫在色塊裡 -->
  <div
    data-testid="result-bar"
    role="img"
    :aria-label="ariaLabel"
    class="flex gap-[2px]"
    :class="heightClass"
    :style="{ width, minWidth: `${minWidthPx}px` }"
  >
    <div
      v-for="segment in segments"
      :key="segment.value"
      data-testid="result-segment"
      :data-value="segment.value"
      class="rounded"
      :style="{
        flexGrow: segment.count,
        flexBasis: 0,
        minWidth: `${SEGMENT_MIN_PX}px`,
        backgroundColor: segment.color,
      }"
    />
  </div>
</template>

<script>
export const SEGMENT_MIN_PX = 6
export const SEGMENT_GAP_PX = 2

/** 長條最短長度：段數 × 6px ＋（段數 − 1）× 2px，比例算出來太短時用它 */
export const barMinWidthPx = (segmentCount) =>
  segmentCount * SEGMENT_MIN_PX + Math.max(segmentCount - 1, 0) * SEGMENT_GAP_PX
</script>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // [{ value, count, color }]，只放有人選的選項
  segments: { type: Array, required: true },
  width: { type: String, default: '100%' },
  heightClass: { type: String, default: 'h-3.5' },
})

const minWidthPx = computed(() => barMinWidthPx(props.segments.length))
const ariaLabel = computed(() => props.segments.map((s) => `${s.value} ${s.count} 人`).join('、'))
</script>
