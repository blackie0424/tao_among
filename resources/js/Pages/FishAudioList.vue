<template>
  <Head :title="`${fish.name}的發音列表`" />

  <div class="space-y-8">
      <!-- Section Header -->
      <div class="flex items-center justify-between">
         <div>
            <h2 class="text-2xl font-serif font-bold text-stone-800">語音存檔</h2>
            <p class="text-stone-500 mt-1">累積的族語發音紀錄</p>
         </div>

         <!-- FAB / Action (Desktop) -->
         <Link
            :href="`/fish/${fish.id}/createAudio`"
            class="hidden md:inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow-sm transition-colors text-sm font-medium"
         >
            <span class="mr-2 text-lg">🎵</span> 新增發音
         </Link>
      </div>

      <!-- Network Status -->
      <div v-if="!isOnline" class="p-4 bg-amber-50 border border-amber-200 rounded-xl flex items-center gap-3">
        <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
        </svg>
        <div>
          <p class="text-sm font-bold text-amber-800">網路連線中斷</p>
          <p class="text-xs text-amber-600">音頻播放功能可能無法正常使用，請檢查網路連線</p>
        </div>
      </div>

      <div v-if="wasOffline" class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
        <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
        <div>
          <p class="text-sm font-bold text-emerald-800">網路連線已恢復</p>
          <p class="text-xs text-emerald-600">音頻播放功能已恢復正常</p>
        </div>
      </div>

      <!-- Stats -->
      <div class="flex flex-wrap gap-4 text-sm">
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span class="w-2.5 h-2.5 bg-purple-500 rounded-full"></span>
             已記錄 {{ audioCount }} 筆
          </div>
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span class="w-2.5 h-2.5 bg-orange-500 rounded-full"></span>
             {{ playbackStatus }}
          </div>
      </div>

      <!-- Audio List -->
      <div v-if="audioCount > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

      <!-- Empty State -->
      <div v-else class="text-center py-16 bg-white rounded-2xl border border-dashed border-stone-200">
             <div class="text-stone-300 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                </svg>
             </div>
             <h3 class="text-lg font-bold text-stone-700">尚未記錄語音</h3>
             <p class="text-stone-500 mt-2 mb-6">點擊下方按鈕開始記錄</p>
             <Link
                :href="`/fish/${fish.id}/createAudio`"
                class="inline-flex items-center px-6 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow-sm transition-colors font-medium"
             >
                立即新增
             </Link>
      </div>
  </div>

  <!-- FAB for Mobile -->
  <FabButton
    bgClass="bg-purple-600"
    hoverClass="hover:bg-purple-700"
    textClass="text-white"
    label="新增"
    icon="🎵"
    :to="`/fish/${fish.id}/createAudio`"
    position="right-bottom"
  />
</template>

<script>
import FishLayout from '@/Layouts/FishLayout.vue'

export default {
  layout: FishLayout,
}
</script>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import FishAudioCard from '@/Components/FishAudioCard.vue'
import FabButton from '@/Components/FabButton.vue'
import audioPlayerService from '@/services/AudioPlayerService.js'
import { useNetworkStatus } from '@/composables/useNetworkStatus.js' // Ensure path is correct

const props = defineProps({
  fish: Object,
})

const { isOnline, wasOffline } = useNetworkStatus()

const audioCount = computed(() => {
  return props.fish.audios ? props.fish.audios.length : 0
})

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

const baseAudioBasename = computed(() => {
  const raw = (props.fish && props.fish.audio_filename) || ''
  return raw ? String(raw).split('/').pop() : ''
})

const getAudioBasename = (audio) => {
  if (!audio) return ''
  const candidate = audio.name || audio.filename || audio.file_name || ''
  return candidate ? String(candidate).split('/').pop() : ''
}

onMounted(() => {
  const handleReconnect = () => {
    router.reload({ only: ['fish'] })
  }

  window.addEventListener('network-reconnected', handleReconnect)

  onUnmounted(() => {
    window.removeEventListener('network-reconnected', handleReconnect)
  })
})

function onAudioUpdated() {
  // router.reload()
}

function onAudioDeleted() {
  router.reload()
}
</script>
