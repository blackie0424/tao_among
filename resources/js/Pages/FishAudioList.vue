<template>
  <div class="container mx-auto p-4 relative">
    <div class="pb-20">
      <!-- 魚類資訊 -->
      <div class="bg-white rounded-lg shadow-md p-4 mb-6">
        <div class="flex flex-col md:flex-row items-center gap-4">
          <!-- 魚類圖片 -->
          <div class="w-full md:w-1/3">
            <LazyImage
              :src="fish.image"
              :alt="fish.name"
              wrapperClass="w-full h-48 bg-gray-100 rounded-lg"
              imgClass="w-full h-full object-contain"
            />
          </div>

          <!-- 魚類資訊 -->
          <div class="w-full md:w-2/3">
            <h2 class="text-2xl font-bold mb-2">{{ fish.name }}</h2>
            <p class="text-gray-600 mb-4">發音列表管理</p>

            <!-- 統計資訊 -->
            <div class="flex flex-wrap gap-4 text-sm">
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-purple-500 rounded-full mr-2"></span>
                <span class="text-gray-700"> 已記錄 {{ audioCount }} 個發音檔案 </span>
              </div>
              <div class="flex items-center">
                <span class="inline-block w-3 h-3 bg-orange-500 rounded-full mr-2"></span>
                <span class="text-gray-700">
                  {{ playbackStatus }}
                </span>
              </div>
            </div>
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
            @updated="onAudioUpdated"
            @deleted="onAudioDeleted"
          />
        </div>
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

    <!-- 底部導航列 -->
    <BottomNavBar
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledgeList="`/fish/${fish.id}/knowledge-list`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="'audioList'"
    />
  </div>
</template>

<script setup>
import FishAudioCard from '../Components/FishAudioCard.vue'
import LazyImage from '../Components/LazyImage.vue'
import FabButton from '../Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import audioPlayerService from '../services/AudioPlayerService.js'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  fish: Object,
})

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

function onAudioUpdated() {
  // 重新載入頁面以顯示更新的發音
  router.reload()
}

function onAudioDeleted() {
  // 重新載入頁面以移除刪除的發音
  router.reload()
}
</script>
