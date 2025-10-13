<template>
  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow">
    <div class="flex justify-between items-start mb-3">
      <h4 class="font-medium text-gray-900">{{ audio.name }}</h4>
      <OverflowMenu
        :apiUrl="`/fish/${fishId}/audio/${audio.id}`"
        :fishId="fishId.toString()"
        :editUrl="`/fish/${fishId}/audio/${audio.id}/edit`"
        @deleted="$emit('deleted')"
      />
    </div>

    <!-- 音頻播放控制 -->
    <div class="flex items-center gap-3 mb-3">
      <button
        @click="togglePlay"
        :class="[
          'flex items-center justify-center w-12 h-12 rounded-full transition-colors',
          isPlaying
            ? 'bg-red-500 hover:bg-red-600 text-white'
            : 'bg-blue-500 hover:bg-blue-600 text-white',
        ]"
        :title="isPlaying ? '停止播放' : '播放音頻'"
      >
        <!-- 播放圖示 -->
        <svg v-if="!isPlaying" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M8 5v14l11-7z" />
        </svg>
        <!-- 停止圖示 -->
        <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path d="M6 6h4v12H6zm8-6v12h4V6h-4z" />
        </svg>
      </button>

      <div class="flex-1">
        <div class="text-sm font-medium text-gray-800">{{ audio.name }}</div>
        <div class="text-xs text-gray-500">
          {{ isPlaying ? '正在播放...' : '點擊播放' }}
        </div>
      </div>

      <!-- 播放狀態指示器 -->
      <div v-if="isPlaying" class="flex items-center space-x-1">
        <div class="w-1 h-4 bg-blue-500 rounded animate-pulse"></div>
        <div class="w-1 h-6 bg-blue-500 rounded animate-pulse" style="animation-delay: 0.1s"></div>
        <div class="w-1 h-4 bg-blue-500 rounded animate-pulse" style="animation-delay: 0.2s"></div>
      </div>
    </div>

    <!-- 音頻檔案資訊 -->
    <div v-if="audio.locate" class="mb-2">
      <span class="text-xs font-medium text-gray-500">檔案</span>
      <p class="text-sm text-gray-700 truncate">{{ audio.locate }}</p>
    </div>

    <div class="text-xs text-gray-400">記錄時間: {{ formatDateTime(audio.created_at) }}</div>

    <!-- 隱藏的音頻元素 -->
    <audio
      ref="audioElement"
      :src="audioUrl"
      @ended="onAudioEnded"
      @error="onAudioError"
      preload="none"
    ></audio>
  </div>
</template>

<script setup>
import OverflowMenu from './OverflowMenu.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  audio: Object,
  fishId: Number,
  isPlaying: Boolean,
})

const emit = defineEmits(['play', 'updated', 'deleted'])

const audioElement = ref(null)

// 計算音頻 URL
const audioUrl = computed(() => {
  if (!props.audio.locate) return null
  // 假設音頻檔案存放在 storage/app/public/audio 目錄
  return `/storage/audio/${props.audio.locate}`
})

// 監聽播放狀態變化
watch(
  () => props.isPlaying,
  (newValue) => {
    if (newValue) {
      playAudio()
    } else {
      stopAudio()
    }
  }
)

function togglePlay() {
  emit('play', props.audio.id)
}

function playAudio() {
  if (audioElement.value && audioUrl.value) {
    audioElement.value.play().catch((error) => {
      console.error('播放音頻失敗:', error)
      // 發送錯誤事件，讓父組件處理
      emit('play', null)
    })
  }
}

function stopAudio() {
  if (audioElement.value) {
    audioElement.value.pause()
    audioElement.value.currentTime = 0
  }
}

function onAudioEnded() {
  // 音頻播放結束，通知父組件停止播放狀態
  emit('play', null)
}

function onAudioError(error) {
  console.error('音頻播放錯誤:', error)
  // 通知父組件停止播放狀態
  emit('play', null)
}

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
