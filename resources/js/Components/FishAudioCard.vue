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
        :disabled="playbackState.error && currentAudioId === audio.id"
        :class="[
          'flex items-center justify-center w-12 h-12 rounded-full transition-colors',
          playbackState.error && currentAudioId === audio.id
            ? 'bg-gray-400 cursor-not-allowed text-white'
            : currentAudioIsPlaying
              ? 'bg-red-500 hover:bg-red-600 text-white'
              : currentAudioIsPaused
                ? 'bg-orange-500 hover:bg-orange-600 text-white'
                : 'bg-blue-500 hover:bg-blue-600 text-white',
        ]"
        :title="getButtonTitle()"
      >
        <!-- 播放圖示 -->
        <svg
          v-if="!currentAudioIsPlaying && !currentAudioIsPaused"
          class="w-5 h-5 ml-0.5"
          fill="currentColor"
          viewBox="0 0 24 24"
        >
          <path d="M8 5v14l11-7z" />
        </svg>
        <!-- 暫停圖示 -->
        <svg
          v-else-if="currentAudioIsPlaying"
          class="w-5 h-5"
          fill="currentColor"
          viewBox="0 0 24 24"
        >
          <path d="M6 6h4v12H6zm8-6v12h4V6h-4z" />
        </svg>
        <!-- 恢復播放圖示 -->
        <svg
          v-else-if="currentAudioIsPaused"
          class="w-5 h-5 ml-0.5"
          fill="currentColor"
          viewBox="0 0 24 24"
        >
          <path d="M8 5v14l11-7z" />
        </svg>
        <!-- 錯誤圖示 -->
        <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
          <path
            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"
          />
        </svg>
      </button>

      <div class="flex-1">
        <div class="text-sm font-medium text-gray-800">{{ audio.name }}</div>
        <div class="text-xs text-gray-500">
          {{ getStatusText() }}
        </div>
        <!-- 播放進度 -->
        <div v-if="currentAudioId === audio.id && playbackState.duration > 0" class="mt-1">
          <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>{{ formatTime(playbackState.currentTime) }}</span>
            <div class="flex-1 bg-gray-200 rounded-full h-1">
              <div
                class="bg-blue-500 h-1 rounded-full transition-all duration-300"
                :style="{ width: `${(playbackState.currentTime / playbackState.duration) * 100}%` }"
              ></div>
            </div>
            <span>{{ formatTime(playbackState.duration) }}</span>
          </div>
        </div>
      </div>

      <!-- 播放狀態指示器 -->
      <div v-if="currentAudioIsPlaying" class="flex items-center space-x-1">
        <div class="w-1 h-4 bg-blue-500 rounded animate-pulse"></div>
        <div class="w-1 h-6 bg-blue-500 rounded animate-pulse" style="animation-delay: 0.1s"></div>
        <div class="w-1 h-4 bg-blue-500 rounded animate-pulse" style="animation-delay: 0.2s"></div>
      </div>

      <!-- 暫停狀態指示器 -->
      <div v-else-if="currentAudioIsPaused" class="flex items-center">
        <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
      </div>

      <!-- 錯誤狀態指示器 -->
      <div v-else-if="playbackState.error && currentAudioId === audio.id" class="flex items-center">
        <div class="w-2 h-2 bg-red-500 rounded-full"></div>
      </div>
    </div>

    <!-- 錯誤訊息 -->
    <div
      v-if="playbackState.error && currentAudioId === audio.id"
      class="mb-3 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-600"
    >
      播放失敗: {{ playbackState.error }}
      <button @click="retryPlay" class="ml-2 underline hover:no-underline">重試</button>
    </div>

    <!-- 音頻檔案資訊 -->
    <div v-if="audio.locate" class="mb-2">
      <span class="text-xs font-medium text-gray-500">檔案</span>
      <p class="text-sm text-gray-700 truncate">{{ audio.locate }}</p>
    </div>

    <div class="text-xs text-gray-400">記錄時間: {{ formatDateTime(audio.created_at) }}</div>

    <!-- 隱藏的音頻元素 -->
    <audio ref="audioElement" :src="audioUrl" preload="none" crossorigin="anonymous"></audio>
  </div>
</template>

<script setup>
import OverflowMenu from './OverflowMenu.vue'
import audioPlayerService from '../services/AudioPlayerService.js'
import { computed, ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  audio: Object,
  fishId: Number,
})

const emit = defineEmits(['updated', 'deleted'])

const audioElement = ref(null)

// 從 AudioPlayerService 獲取播放狀態
const currentAudioId = computed(() => audioPlayerService.currentPlayingId.value)
const playbackState = computed(() => audioPlayerService.playbackState)

// 計算當前音頻的播放狀態
const currentAudioIsPlaying = computed(() => audioPlayerService.isPlaying(props.audio.id))

const currentAudioIsPaused = computed(() => audioPlayerService.isPaused(props.audio.id))

// 計算音頻 URL
const audioUrl = computed(() => {
  // 優先使用後端提供的 URL
  if (props.audio.url) {
    return props.audio.url
  }

  // 如果沒有 URL 但有 locate，則手動構建
  if (props.audio.locate) {
    const supabaseUrl = import.meta.env.VITE_SUPABASE_STORAGE_URL
    const bucket = import.meta.env.VITE_SUPABASE_BUCKET
    return `${supabaseUrl}/object/public/${bucket}/audio/${props.audio.locate}`
  }

  return null
})

// 組件掛載時設置事件監聽器
onMounted(() => {
  // 監聽播放服務事件
  audioPlayerService.on('error', handleAudioServiceError)
  audioPlayerService.on('ended', handleAudioServiceEnded)
})

// 組件卸載時清理事件監聽器
onUnmounted(() => {
  audioPlayerService.off('error', handleAudioServiceError)
  audioPlayerService.off('ended', handleAudioServiceEnded)
})

/**
 * 切換播放狀態
 */
async function togglePlay() {
  if (!audioUrl.value) {
    console.warn('音頻 URL 不存在')
    return
  }

  if (!audioElement.value) {
    console.warn('音頻元素不存在')
    return
  }

  try {
    await audioPlayerService.play(props.audio.id, audioElement.value, audioUrl.value)
  } catch (error) {
    console.error('播放音頻失敗:', error)
  }
}

/**
 * 重試播放
 */
async function retryPlay() {
  if (!audioUrl.value || !audioElement.value) return

  try {
    // 重置錯誤狀態
    audioPlayerService.playbackState.error = null
    await audioPlayerService.play(props.audio.id, audioElement.value, audioUrl.value)
  } catch (error) {
    console.error('重試播放失敗:', error)
  }
}

/**
 * 處理音頻服務錯誤事件
 */
function handleAudioServiceError(data) {
  if (data.audioId === props.audio.id) {
    console.error(`音頻 ${props.audio.id} 播放錯誤:`, data.error)
  }
}

/**
 * 處理音頻服務播放結束事件
 */
function handleAudioServiceEnded(data) {
  if (data.audioId === props.audio.id) {
    console.log(`音頻 ${props.audio.id} 播放結束`)
  }
}

/**
 * 獲取按鈕標題
 */
function getButtonTitle() {
  if (playbackState.value.error && currentAudioId.value === props.audio.id) {
    return '播放失敗，點擊重試'
  }

  if (currentAudioIsPlaying.value) {
    return '暫停播放'
  }

  if (currentAudioIsPaused.value) {
    return '恢復播放'
  }

  return '播放音頻'
}

/**
 * 獲取狀態文字
 */
function getStatusText() {
  if (playbackState.value.error && currentAudioId.value === props.audio.id) {
    return '播放失敗'
  }

  if (currentAudioIsPlaying.value) {
    return '正在播放...'
  }

  if (currentAudioIsPaused.value) {
    return '已暫停'
  }

  return '點擊播放'
}

/**
 * 格式化時間
 */
function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00'

  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = Math.floor(seconds % 60)
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
}

/**
 * 格式化日期時間
 */
function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
