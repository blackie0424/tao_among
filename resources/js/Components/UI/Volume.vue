<template>
  <span
    :class="[
      'inline-flex items-center justify-center rounded-full ml-2',
      large
        ? 'min-w-touch-primary min-h-touch-primary'
        : 'min-w-touch-secondary min-h-touch-secondary',
      shouldEnableAnimations ? 'transition-colors duration-200' : '',
      optimizedButtonClasses,
    ]"
    @click="handleClick"
    :disabled="isPlaying"
    :title="buttonTitle"
    role="button"
    :aria-label="buttonTitle"
    :aria-disabled="isPlaying"
    tabindex="0"
    @keydown.enter="handleClick"
    @keydown.space.prevent="handleClick"
  >
    <!-- 播放圖示 (預設狀態) -->
    <svg
      v-if="playbackState === 'idle'"
      class="w-5 h-5 text-brand"
      fill="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path d="M8 5v14l11-7z" />
    </svg>

    <!-- 播放中圖示 -->
    <svg
      v-else-if="playbackState === 'playing'"
      class="w-5 h-5 text-white"
      fill="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
      />
    </svg>

    <!-- 重試中圖示 -->
    <svg
      v-else-if="playbackState === 'retrying'"
      class="w-5 h-5 text-white animate-spin"
      fill="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z"
      />
    </svg>

    <!-- 錯誤圖示 -->
    <svg
      v-else-if="playbackState === 'error'"
      class="w-5 h-5 text-white"
      fill="currentColor"
      viewBox="0 0 24 24"
      aria-hidden="true"
    >
      <path
        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h2v-2h-2v2zm0-4h2V7h-2v6z"
      />
    </svg>
  </span>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useAudioPlayback } from '@/composables/useAudioPlayback.js'
import animationOptimizer from '@/utils/AnimationOptimizer.js'

const props = defineProps({
  audioUrl: {
    type: String,
    required: true,
  },
  audioId: {
    type: [String, Number],
    default: null,
  },
  large: {
    type: Boolean,
    default: false,
  },
})

// 使用音頻播放組合式函數
const {
  playbackState,
  buttonClasses,
  buttonTitle,
  isPlaying,
  hasError,
  isClickable,
  isRetrying,
  canRetry,
  retryProgress,
  error,
  retryCount,
  maxRetries,
  handleClick,
} = useAudioPlayback(props.audioUrl, props.audioId)

// 優化的按鈕類別
const optimizedButtonClasses = computed(() => {
  const baseClasses = buttonClasses.value
  const optimizationLevel = animationOptimizer.optimizationLevel

  // 根據優化等級調整類別
  if (optimizationLevel === 'aggressive') {
    return baseClasses.replace(/transition-|animate-|duration-/g, '')
  }

  return baseClasses
})

// 檢查是否應該啟用動畫
const shouldEnableAnimations = computed(() => {
  return animationOptimizer.shouldEnableAnimation('transition')
})

onMounted(() => {
  animationOptimizer.init()
})
</script>
