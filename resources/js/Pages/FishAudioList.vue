<template>
  <Head :title="`${fish.name}的發音列表`" />

  <FishAppLayout
    :pageTitle="`${fish.name}的發音列表`"
    mobileBackUrl="/fishs"
    :mobileBackText="fish.name"
  >
    <div class="container mx-auto p-4 relative">
      <div class="pb-20">
        <!-- 網路狀態提示 -->
        <div v-if="!isOnline" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
            </svg>
            <div>
              <p class="text-sm font-medium text-yellow-800">網路連線中斷</p>
              <p class="text-xs text-yellow-600">音頻播放功能可能無法正常使用，請檢查網路連線</p>
            </div>
          </div>
        </div>

        <div v-if="wasOffline" class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
              <path
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clip-rule="evenodd"
              />
            </svg>
            <div>
              <p class="text-sm font-medium text-green-800">網路連線已恢復</p>
              <p class="text-xs text-green-600">音頻播放功能已恢復正常</p>
            </div>
          </div>
        </div>

        <!-- 發音列表 -->
        <div class="bg-white rounded-lg shadow-md p-4">
          <h3 class="text-lg font-semibold mb-4">發音列表</h3>

          <!-- 空狀態 -->
          <div v-if="audioCount === 0" class="text-center py-8">
            <div class="text-gray-400 mb-4">
              <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
                ></path>
              </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">尚未記錄發音檔案</h3>
            <p class="text-gray-500">點擊右下角的按鈕開始記錄這條魚的發音資訊</p>
          </div>

          <!-- 發音卡片列表 -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <FishAudioCard
              v-for="audio in fish.audios"
              :key="audio.id"
              :audio="audio"
              :fishId="fish.id"
              :is-base="baseAudioBasename === getAudioBasename(audio)"
              :enable-custom-option="true"
              :enable-edit="false"
              @updated="onAudioUpdated"
              @deleted="onAudioDeleted"
            />
          </div>
        </div>

        <!-- 新增發音 FAB 按鈕 -->
        <FabButton
          bgClass="bg-purple-600"
          hoverClass="hover:bg-purple-700"
          textClass="text-white"
          label="新增發音"
          icon="🎵"
          :to="`/fish/${fish.id}/createAudio`"
          position="right-bottom"
        />
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishAudioCard from '../Components/Fish/FishAudioCard.vue'
import FabButton from '../Components/UI/FabButton.vue'
import audioPlayerService from '../services/AudioPlayerService.js'
import { router } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted } from 'vue'
import { useNetworkStatus } from '../composables/useNetworkStatus.js'

const props = defineProps({
  fish: Object,
})

// 網路狀態監控
const { isOnline, wasOffline } = useNetworkStatus()

// 計算發音數量
const audioCount = computed(() => {
  return props.fish.audios ? props.fish.audios.length : 0
})

// 計算播放狀態文字
const playbackStatus = computed(() => {
  const playbackState = audioPlayerService.getPlaybackState()

  if (playbackState.currentPlayingId) {
    if (playbackState.isPlaying) {
      return '正在播放'
    } else if (playbackState.isPaused) {
      return '已暫停'
    } else if (playbackState.error) {
      return '播放錯誤'
    }
  }

  return '待播放'
})

// 取 fish 中代表「基本發音」的欄位，並只取最後的檔名
const baseAudioBasename = computed(() => {
  const raw = (props.fish && props.fish.audio_filename) || ''
  return raw ? String(raw).split('/').pop() : ''
})

// 輔助：從 audio 物件取可能的檔名欄位 (優先使用 audio.name)
const getAudioBasename = (audio) => {
  if (!audio) return ''
  const candidate = audio.name || audio.filename || audio.file_name || ''
  return candidate ? String(candidate).split('/').pop() : ''
}

// 監聽網路重連事件
onMounted(() => {
  const handleReconnect = () => {
    // 網路重連後重新載入資料
    router.reload({ only: ['fish'] })
  }

  window.addEventListener('network-reconnected', handleReconnect)

  onUnmounted(() => {
    window.removeEventListener('network-reconnected', handleReconnect)
  })
})

function onAudioUpdated() {
  // 重新載入頁面以顯示更新的發音
  //router.reload()
}

function onAudioDeleted() {
  // 重新載入頁面以移除刪除的發音
  router.reload()
}
</script>
