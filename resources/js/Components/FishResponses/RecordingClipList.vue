<template>
  <div v-if="clips.length" data-testid="clip-list">
    <p v-if="showLabel" class="mt-2 mb-1 text-elder-aux text-elder-subtext">{{ label }}</p>
    <div
      v-for="(clip, index) in visibleClips"
      :key="clip.recording_url"
      class="flex min-h-[3rem] items-center justify-between border-t border-rule text-elder-body text-elder-text"
      data-testid="clip-row"
    >
      <span>第 {{ index + 1 }} 段</span>
      <RecordingPlayButton :src="clip.recording_url" :known-seconds="clip.recording_seconds" />
    </div>
    <button
      v-if="hiddenCount > 0"
      type="button"
      class="flex min-h-[3rem] w-full items-center justify-center border-t border-rule text-elder-body text-brand underline"
      aria-expanded="false"
      data-testid="clips-more"
      @click="expanded = true"
    >
      還有 {{ hiddenCount }} 段，全部展開 ▾
    </button>
    <button
      v-else-if="expanded && clips.length > PREVIEW_COUNT"
      type="button"
      class="flex min-h-[3rem] w-full items-center justify-center border-t border-rule text-elder-body text-brand underline"
      aria-expanded="true"
      data-testid="clips-less"
      @click="expanded = false"
    >
      收起 ▴
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import RecordingPlayButton from '@/Components/FishResponses/RecordingPlayButton.vue'

// 沒有文字的錄音一段一列；預設先顯示前 3 段，超過才收合
const PREVIEW_COUNT = 3

const props = defineProps({
  clips: { type: Array, default: () => [] }, // [{ recording_url, recording_seconds }]，最早的在前
  showLabel: { type: Boolean, default: true }, // 叫法下面的清單不需要標題
  label: { type: String, default: '有人只用說的' },
})

const expanded = ref(false)
// 段數降到 3 段以內時，「全部展開」的狀態一併清除；之後再超過 3 段，回到先顯示前 3 段
watch(
  () => props.clips.length,
  (count) => {
    if (count <= PREVIEW_COUNT) expanded.value = false
  }
)
const visibleClips = computed(() => (expanded.value ? props.clips : props.clips.slice(0, PREVIEW_COUNT)))
const hiddenCount = computed(() => (expanded.value ? 0 : Math.max(0, props.clips.length - PREVIEW_COUNT)))
</script>
