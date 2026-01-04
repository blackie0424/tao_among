<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :submitNote="handleNext"
      :submitting="submitting"
      title="建立魚類錄音"
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
import { ref, onBeforeUnmount, nextTick } from 'vue'
import TopNavBar from '@/Components/Global/TopNavBar.vue'
import LazyImage from '../Components/LazyImage.vue'

import { router } from '@inertiajs/vue3'

const props = defineProps({
  fish: Object,
})

const isRecording = ref(false)
const audioBlob = ref(null)
const audioUrl = ref('')
const submitting = ref(false)
const recordingError = ref('')
const timerCount = ref(5)
const waveCanvas = ref(null)
let mediaRecorder = null
let chunks = []
let timer = null
let interval = null
let audioContext = null
let analyser = null
let source = null
let animationId = null
let stream = null

function isSafari() {
  return /^((?!chrome|android).)*safari/i.test(navigator.userAgent)
}

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

function drawWave() {
  if (!analyser || !waveCanvas.value) return
  const canvas = waveCanvas.value
  const ctx = canvas.getContext('2d')
  const bufferLength = analyser.fftSize
  const dataArray = new Uint8Array(bufferLength)
  analyser.getByteTimeDomainData(dataArray)

  ctx.clearRect(0, 0, canvas.width, canvas.height)
  ctx.lineWidth = 2
  ctx.strokeStyle = '#4d7f99'
  ctx.beginPath()

  // 振幅放大倍率，原本是 /2，這裡可調整為 /1.2 或直接 *1.5
  const amplitude = 1.5 // 建議可調整 1.5~2.5
  const sliceWidth = canvas.width / bufferLength
  let x = 0
  for (let i = 0; i < bufferLength; i++) {
    const v = dataArray[i] / 128.0
    // 放大振幅
    const y = (v - 1) * (canvas.height / amplitude) + canvas.height / 2
    if (i === 0) {
      ctx.moveTo(x, y)
    } else {
      ctx.lineTo(x, y)
    }
    x += sliceWidth
  }
  ctx.stroke()
  animationId = requestAnimationFrame(drawWave)
}

function startRecording() {
  recordingError.value = ''
  audioBlob.value = null
  audioUrl.value = ''
  chunks = []
  timerCount.value = 5

  navigator.mediaDevices
    .getUserMedia({ audio: true })
    .then(async (_stream) => {
      stream = _stream
      // 判斷支援格式，全平台統一優先使用 M4A (AAC 編碼)
      let mimeType = ''

      // 優先使用 M4A 格式，確保跨平台相容性
      // M4A (audio/mp4) 支援：iOS、Chrome、Firefox、Edge、Safari
      // 不再使用 WebM，避免 iOS Safari 不相容問題
      if (MediaRecorder.isTypeSupported('audio/mp4')) {
        mimeType = 'audio/mp4' // M4A 格式 (AAC 編碼)
      } else if (MediaRecorder.isTypeSupported('audio/aac')) {
        mimeType = 'audio/aac' // 純 AAC 格式 (降級選項)
      } else {
        recordingError.value =
          '瀏覽器不支援錄音格式，請使用現代瀏覽器 (Chrome、Firefox、Safari、Edge)'
        stream.getTracks().forEach((track) => track.stop())
        return
      }
      try {
        mediaRecorder = new MediaRecorder(stream, { mimeType })
      } catch (e) {
        recordingError.value = '瀏覽器不支援錄音，請改用 Chrome 或 Edge。'
        stream.getTracks().forEach((track) => track.stop())
        return
      }
      mediaRecorder.start()
      isRecording.value = true
      timer = setTimeout(() => {
        stopRecording()
      }, 5000)
      interval = setInterval(() => {
        if (timerCount.value > 0) {
          timerCount.value--
        }
      }, 1000)
      mediaRecorder.ondataavailable = (e) => {
        chunks.push(e.data)
      }
      mediaRecorder.onstop = () => {
        const blob = new Blob(chunks, { type: mimeType })
        audioBlob.value = blob
        audioUrl.value = URL.createObjectURL(blob)
        isRecording.value = false
        stream.getTracks().forEach((track) => track.stop())
        clearTimeout(timer)
        clearInterval(interval)
        safeCloseAudioContext()
        if (animationId) cancelAnimationFrame(animationId)
      }
      // 聲音波形分析
      audioContext = new (window.AudioContext || window.webkitAudioContext)()
      analyser = audioContext.createAnalyser()
      analyser.fftSize = 256
      source = audioContext.createMediaStreamSource(stream)
      source.connect(analyser)
      await nextTick() // 確保 canvas 已渲染
      drawWave()
    })
    .catch(() => {
      recordingError.value = '無法取得麥克風權限'
      isRecording.value = false
    })
}

function safeCloseAudioContext() {
  if (audioContext) {
    try {
      audioContext.close()
    } catch (e) {}
    audioContext = null
  }
}

function stopRecording() {
  if (mediaRecorder && isRecording.value) {
    mediaRecorder.stop()
    isRecording.value = false
    clearTimeout(timer)
    clearInterval(interval)
    timerCount.value = 0
    if (animationId) cancelAnimationFrame(animationId)
    safeCloseAudioContext()
  }
}

function resetRecording() {
  audioBlob.value = null
  audioUrl.value = ''
  recordingError.value = ''
  timerCount.value = 5
}

// 取得 fish id（從網址 /fish/:id/createAudio 取出）
function getFishIdFromUrl() {
  const match = window.location.pathname.match(/\/fish\/(\d+)\/createAudio/)
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
    // 根據錄製的 MIME 類型決定副檔名
    // 統一使用 M4A 格式 (audio/mp4 with AAC encoding)
    // 確保跨平台相容性 (iOS、Android、Desktop 全支援)
    let ext = 'm4a' // 預設 M4A 格式
    if (audioBlob.value.type === 'audio/mp4') ext = 'm4a' // M4A 格式（AAC 編碼）
    if (audioBlob.value.type === 'audio/aac') ext = 'aac' // 純 AAC（降級）

    // 取得 fish id
    const fishId = getFishIdFromUrl()
    if (!fishId) throw new Error('無法取得魚類編號')

    // 第一步：取得上傳資訊
    const uploadRes = await fetch(`/prefix/api/fish/${fishId}/storage/signed-upload-audio-url`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        filename: `audio_${Date.now()}.${ext}`,
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

    // 上傳成功後導回特定魚類資訊頁
    router.visit(`/fish/${fishId}`)
  } catch (e) {
    recordingError.value = e.message || '音訊上傳失敗'
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  if (animationId) cancelAnimationFrame(animationId)
  safeCloseAudioContext()
})
</script>
