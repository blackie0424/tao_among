<template>
  <span
    :class="[
      'inline-flex items-center justify-center rounded-full ml-2 transition-colors duration-200',
      buttonClasses,
    ]"
    style="width: 2.2rem; height: 2.2rem"
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
      class="w-5 h-5 text-gray-600"
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
import { useAudioPlayback } from '../composables/useAudioPlayback.js'

const props = defineProps({
  audioUrl: {
    type: String,
    required: true,
  },
  audioId: {
    type: [String, Number],
    default: null,
  },
})

// 使用音頻播放組合式函數
const { playbackState, buttonClasses, buttonTitle, isPlaying, hasError, isClickable, handleClick } =
  useAudioPlayback(props.audioUrl, props.audioId)
</script>
<style scoped>
/* 基礎按鈕樣式 */
.volume-button {
  transition: all 0.2s ease-in-out;
}

/* 預設狀態樣式 */
.volume-button--idle {
  @apply bg-gray-200 hover:bg-gray-300 cursor-pointer;
}

.volume-button--idle:hover {
  transform: scale(1.05);
}

.volume-button--idle:active {
  transform: scale(0.95);
}

/* 播放中狀態樣式 */
.volume-button--playing {
  @apply bg-blue-500 cursor-not-allowed;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
}

.volume-button--playing:hover {
  @apply bg-blue-500;
  transform: none;
}

/* 錯誤狀態樣式 */
.volume-button--error {
  @apply bg-red-500 hover:bg-red-600 cursor-pointer;
  animation: pulse-error 2s infinite;
}

.volume-button--error:hover {
  transform: scale(1.05);
}

.volume-button--error:active {
  transform: scale(0.95);
}

/* 錯誤狀態脈衝動畫 */
@keyframes pulse-error {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
  }
  50% {
    box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
  }
}

/* 焦點狀態 */
.volume-button:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
}

/* 禁用狀態 */
.volume-button:disabled {
  pointer-events: none;
}

/* 圖示過渡動畫 */
.volume-button svg {
  transition: all 0.2s ease-in-out;
}

/* 響應式設計 */
@media (max-width: 640px) {
  .volume-button {
    width: 2rem;
    height: 2rem;
  }

  .volume-button svg {
    width: 1rem;
    height: 1rem;
  }
}

/* 高對比度模式支援 */
@media (prefers-contrast: high) {
  .volume-button--idle {
    @apply border-2 border-gray-600;
  }

  .volume-button--playing {
    @apply border-2 border-blue-700;
  }

  .volume-button--error {
    @apply border-2 border-red-700;
  }
}

/* 減少動畫偏好設定 */
@media (prefers-reduced-motion: reduce) {
  .volume-button,
  .volume-button svg {
    transition: none;
  }

  .volume-button--error {
    animation: none;
  }

  .volume-button--idle:hover,
  .volume-button--error:hover {
    transform: none;
  }

  .volume-button--idle:active,
  .volume-button--error:active {
    transform: none;
  }
}
</style>
