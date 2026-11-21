<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      :submitNote="handleNext"
      :submitting="submitting"
      :title="`新增${fish.name}的發音`"
      :showSubmit="!!audioBlob"
      :submitLabel="submitting ? '送出中...' : '下一步'"
      :showLoading="submitting"
    />

    <div class="pt-16 flex flex-col items-center">
      <!-- 魚類提醒 -->
      <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
        <LazyImage
          :src="fish.display_image_url || fish.image_url"
          :alt="fish.name"
          wrapperClass="fish-image-wrapper"
          imgClass="fish-image"
        />
      </div>
      <div class="mb-6">
        <button
          v-if="!isRecording"
          class="bg-blue-600 text-white px-6 py-2 rounded font-bold hover:bg-blue-700 transition"
          @click="startRecording"
          :disabled="submitting"
        >
          開始錄音
        </button>
        <button
          v-else
          class="bg-red-600 text-white px-6 py-2 rounded font-bold hover:bg-red-700 transition"
          @click="stopRecording"
        >
          停止錄音
        </button>
      </div>
      <div v-if="isRecording" class="mb-4 flex flex-col items-center w-full">
        <div class="text-red-500 font-bold mb-2">錄音中... 最長 5 秒</div>
        <div class="flex items-center gap-2 mb-2">
          <span class="text-gray-700">取樣狀態：</span>
          <span class="text-green-600 font-bold">執行中</span>
        </div>
        <div class="w-full flex justify-center mb-2">
          <span class="text-lg font-mono text-blue-700">{{ timerCount }}</span>
        </div>
        <!-- 聲音波形 -->
        <canvas ref="waveCanvas" width="300" height="60" class="bg-gray-100 rounded"></canvas>
      </div>
      <div v-if="audioBlob" class="mb-4 flex flex-col items-center">
        <audio :src="audioUrl" controls class="mb-2"></audio>
        <button
          class="bg-yellow-500 text-white px-4 py-2 rounded font-bold hover:bg-yellow-600 transition"
          @click="resetRecording"
          :disabled="submitting || isRecording"
        >
          重錄
        </button>
      </div>
      <div v-if="recordingError" class="text-red-600 mt-2">{{ recordingError }}</div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import FormActionBar from '@/Components/Global/FormActionBar.vue'
import LazyImage from '../Components/UI/LazyImage.vue'

import { router } from '@inertiajs/vue3'
import { markFishStale } from '@/utils/fishListCache'
import { apiFetch } from '@/utils/apiFetch'
import { useAudioRecorder } from '@/composables/useAudioRecorder.js'

const props = defineProps({
  fish: Object,
})

const PAGE_RECORDING_SECONDS = 5

const submitting = ref(false)
const recordingError = ref('')
const waveCanvas = ref(null)
const recorder = useAudioRecorder({ maxSeconds: PAGE_RECORDING_SECONDS, canvasRef: waveCanvas })
const { isRecording, audioBlob, audioUrl, remaining: timerCount } = recorder

watch(recorder.error, (message) => {
  recordingError.value = message
})

function goBack() {
  router.visit(`/fish/${props.fish.id}?tab=audio`)
}

const startRecording = () => recorder.start()
const stopRecording = () => recorder.stop()

function resetRecording() {
  recorder.reset()
  recordingError.value = ''
}

// 取得 fish id（從網址 /fish/:id/audio/create 取出）
function getFishIdFromUrl() {
  const match = window.location.pathname.match(/\/fish\/(\d+)\/audio\/create/)
  return match ? match[1] : null
}

// 上傳音訊並導回魚類資訊頁
async function handleNext() {
  if (!audioBlob.value) {
    recordingError.value = '請先錄音'
    return
  }
  submitting.value = true
  try {
    // 取得 fish id
    const fishId = getFishIdFromUrl()
    if (!fishId) throw new Error('無法取得魚類編號')

    // 第一步：取得上傳資訊
    const uploadRes = await apiFetch(`/prefix/api/fish/${fishId}/storage/signed-upload-audio-url`, {
      method: 'POST',
      body: JSON.stringify({
        filename: `audio_${Date.now()}.${recorder.ext.value}`,
      }),
    })
    const uploadData = await uploadRes.json()
    if (!uploadRes.ok || !uploadData.url) throw new Error('取得上傳資訊失敗')

    // 第二步：PUT 音訊物件到 Supabase
    const putRes = await fetch(uploadData.url, {
      method: 'PUT',
      body: audioBlob.value,
    })
    if (!putRes.ok) throw new Error('音訊上傳失敗')

    // 標記魚類資料需要更新（清除快取）
    markFishStale(fishId)

    // 上傳成功後導回特定魚類資訊頁
    router.visit(`/fish/${fishId}?tab=audio`)
  } catch (e) {
    recordingError.value = e.message || '音訊上傳失敗'
  } finally {
    submitting.value = false
  }
}
</script>
