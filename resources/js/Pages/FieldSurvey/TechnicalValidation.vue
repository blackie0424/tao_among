<template>
  <main class="mx-auto max-w-2xl space-y-5 p-4 pb-16 text-gray-900">
    <header>
      <p class="text-sm font-semibold text-blue-700">階段 0｜臨時頁面</p>
      <h1 class="text-2xl font-bold">田調長時間錄音技術驗證</h1>
      <p class="mt-2 text-sm text-gray-600">
        只驗證 iPhone 17 PWA 錄音、分段本機保存及恢復，不會上傳或寫入正式資料。
      </p>
    </header>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
      <h2 class="font-bold">環境</h2>
      <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
        <dt>Standalone</dt>
        <dd>{{ standalone ? '是' : '否' }}</dd>
        <dt>錄音格式</dt>
        <dd class="font-mono">{{ mimeType || '不支援' }}</dd>
        <dt>Wake Lock API</dt>
        <dd>{{ wakeLockSupported ? '支援' : '不支援' }}</dd>
        <dt>IndexedDB</dt>
        <dd>{{ indexedDbReady ? '可用' : '未就緒' }}</dd>
        <dt>暫存用量</dt>
        <dd>{{ storageUsage }}</dd>
        <dt>暫存配額</dt>
        <dd>{{ storageQuota }}</dd>
        <dt>持久儲存</dt>
        <dd>{{ persistedStorage }}</dd>
      </dl>
      <label class="mt-4 flex items-center gap-3 rounded bg-blue-50 p-3 text-sm font-semibold">
        <input
          v-model="useWakeLock"
          type="checkbox"
          :disabled="status === 'recording'"
          class="h-5 w-5"
        />
        本次錄音使用 Wake Lock（測試自動鎖定時請取消勾選）
      </label>
    </section>

    <section class="rounded-xl border-2 p-4" :class="statusClasses">
      <div class="flex items-center justify-between gap-3">
        <div>
          <p class="text-sm font-semibold">目前狀態</p>
          <p class="text-xl font-bold">{{ statusLabel }}</p>
        </div>
        <span
          v-if="status === 'recording'"
          class="animate-pulse rounded-full bg-red-600 px-3 py-1 text-sm font-bold text-white"
        >
          錄音中
        </span>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
        <div>
          錄音經過：<strong>{{ formatDuration(elapsedMs) }}</strong>
        </div>
        <div>
          已存段數：<strong>{{ chunkCount }}</strong>
        </div>
        <div>
          已存容量：<strong>{{ formatBytes(totalBytes) }}</strong>
        </div>
        <div>
          Wake Lock：<strong>{{ wakeLockActive ? '有效' : '無' }}</strong>
        </div>
      </div>

      <p v-if="elapsedMs >= 300_000" class="mt-3 rounded bg-green-100 p-2 font-bold text-green-800">
        已連續錄製至少 5 分鐘，可以停止並驗證組檔播放。
      </p>
      <p
        v-if="errorMessage"
        class="mt-3 rounded bg-red-100 p-3 font-bold text-red-800"
        role="alert"
      >
        {{ errorMessage }}
      </p>

      <div class="mt-4 flex flex-wrap gap-3">
        <button
          v-if="['idle', 'ready', 'error'].includes(status)"
          class="rounded bg-blue-700 px-5 py-3 font-bold text-white disabled:opacity-40"
          :disabled="!canStart"
          @click="startRecording"
        >
          開始新的驗證錄音
        </button>
        <button
          v-if="status === 'recording'"
          class="rounded bg-red-700 px-5 py-3 font-bold text-white"
          @click="stopRecording"
        >
          停止並組回音檔
        </button>
        <button
          v-if="recoverableRecordingId && !['recording', 'stopping'].includes(status)"
          class="rounded bg-amber-600 px-5 py-3 font-bold text-white"
          @click="recoverRecording"
        >
          恢復本機暫存
        </button>
      </div>
    </section>

    <section v-if="playbackUrl" class="rounded-xl border bg-white p-4 shadow-sm">
      <h2 class="font-bold">組檔播放驗證</h2>
      <audio
        class="mt-3 w-full"
        :src="playbackUrl"
        controls
        @loadedmetadata="capturePlaybackDuration"
      />
      <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
        <dt>錄製計時</dt>
        <dd>{{ formatDuration(recordedElapsedMs) }}</dd>
        <dt>播放檔時長</dt>
        <dd>{{ playbackDuration === null ? '載入中' : formatDuration(playbackDuration) }}</dd>
        <dt>時長差距</dt>
        <dd>{{ durationDifference }}</dd>
      </dl>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
      <h2 class="font-bold">iPhone 17 測試順序</h2>
      <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm">
        <li>從主畫面開啟，確認 Standalone 顯示「是」。</li>
        <li>持續錄音超過 5 分鐘，停止後從頭播放到尾，比較錄製與播放時長。</li>
        <li>錄音中等待螢幕原本應休眠的時間，記錄是否保持亮屏。</li>
        <li>再錄一次並讓螢幕自動鎖定，解鎖後確認已存段數是否繼續增加。</li>
        <li>錄音中切到其他 App 約 30 秒再回來。</li>
        <li>錄音中接一通電話，再回來停止與播放。</li>
        <li>記錄暫存用量、配額，以及是否出現寫入失敗。</li>
        <li>錄音中從左緣滑動返回，再回此頁，確認是否跳頁及能否恢復本機暫存。</li>
      </ol>
      <p class="mt-3 rounded bg-yellow-100 p-3 text-sm font-semibold text-yellow-900">
        第 2 項若不能組回、不能完整播放或時長明顯不正確，請立即停止並回報。
      </p>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm">
      <div class="flex items-center justify-between">
        <h2 class="font-bold">生命週期紀錄</h2>
        <button class="text-sm text-blue-700 underline" @click="copyReport">複製回報</button>
      </div>
      <pre
        class="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded bg-gray-950 p-3 text-xs text-green-200"
        >{{ eventLog.join('\n') }}</pre
      >
      <p v-if="copyMessage" class="mt-2 text-sm font-semibold text-green-700">{{ copyMessage }}</p>
    </section>
  </main>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
  assembleAudioChunks,
  formatBytes,
  formatDuration,
  loadTechnicalAudioChunks,
  openTechnicalAudioDatabase,
  saveTechnicalAudioChunk,
  selectRecordingMimeType,
} from '@/utils/fieldSurveyTechnicalAudio.js'

const ACTIVE_RECORDING_KEY = 'field-survey-validation-recording-id'
const EVENT_LOG_KEY = 'field-survey-validation-event-log'
const TIMESLICE_MS = 5_000

const status = ref('idle')
const errorMessage = ref('')
const mimeType = ref(null)
const indexedDbReady = ref(false)
const wakeLockSupported = ref('wakeLock' in navigator)
const wakeLockActive = ref(false)
const useWakeLock = ref(true)
const standalone = ref(false)
const chunkCount = ref(0)
const totalBytes = ref(0)
const elapsedMs = ref(0)
const recordedElapsedMs = ref(0)
const playbackDuration = ref(null)
const playbackUrl = ref('')
const recoverableRecordingId = ref(localStorage.getItem(ACTIVE_RECORDING_KEY))
const storageUsage = ref('讀取中')
const storageQuota = ref('讀取中')
const persistedStorage = ref('讀取中')
const eventLog = ref(JSON.parse(localStorage.getItem(EVENT_LOG_KEY) || '[]'))
const copyMessage = ref('')

let database = null
let recorder = null
let stream = null
let wakeLock = null
let recordingId = null
let sequence = 0
let startedAt = 0
let elapsedTimer = null
let pendingWrites = []

const statusLabel = computed(
  () =>
    ({
      idle: '尚未開始',
      requesting: '正在要求麥克風權限',
      recording: '錄音中，分段保存至本機',
      stopping: '停止中，等待最後一段落地',
      ready: '已組回，可播放驗證',
      error: '驗證失敗',
    })[status.value]
)

const statusClasses = computed(() => {
  if (status.value === 'recording') return 'border-red-500 bg-red-50'
  if (status.value === 'error') return 'border-red-700 bg-red-50'
  return 'border-blue-300 bg-blue-50'
})
const canStart = computed(
  () => indexedDbReady.value && mimeType.value && status.value !== 'requesting'
)
const durationDifference = computed(() => {
  if (playbackDuration.value === null) return '載入中'
  const difference = Math.abs(playbackDuration.value - recordedElapsedMs.value)
  return `${formatDuration(difference)}（${difference} ms）`
})

function addEvent(message) {
  const entry = `${new Date().toISOString()} ${message}`
  eventLog.value = [...eventLog.value.slice(-99), entry]
  localStorage.setItem(EVENT_LOG_KEY, JSON.stringify(eventLog.value))
}

async function refreshStorageEstimate() {
  if (!navigator.storage?.estimate) {
    storageUsage.value = '不支援查詢'
    storageQuota.value = '不支援查詢'
    return
  }
  const estimate = await navigator.storage.estimate()
  storageUsage.value = formatBytes(estimate.usage ?? 0)
  storageQuota.value = formatBytes(estimate.quota ?? 0)
  persistedStorage.value = navigator.storage.persisted
    ? (await navigator.storage.persisted())
      ? '是'
      : '否'
    : '不支援查詢'
}

async function requestWakeLock() {
  if (!useWakeLock.value || !wakeLockSupported.value || document.visibilityState !== 'visible')
    return
  try {
    wakeLock = await navigator.wakeLock.request('screen')
    wakeLockActive.value = true
    wakeLock.addEventListener('release', () => {
      wakeLockActive.value = false
      addEvent('Wake Lock released')
    })
    addEvent('Wake Lock acquired')
  } catch (error) {
    addEvent(`Wake Lock failed: ${error.message}`)
  }
}

async function releaseWakeLock() {
  if (wakeLock) await wakeLock.release().catch(() => {})
  wakeLock = null
  wakeLockActive.value = false
}

function createRecordingId() {
  return crypto.randomUUID?.() ?? `validation-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

async function persistChunk(blob) {
  if (!blob.size) return
  const currentSequence = ++sequence
  const write = saveTechnicalAudioChunk(database, {
    recordingId,
    sequence: currentSequence,
    blob,
    mimeType: mimeType.value,
    createdAt: Date.now(),
  })
    .then(async () => {
      chunkCount.value += 1
      totalBytes.value += blob.size
      addEvent(`Chunk ${currentSequence} saved (${blob.size} bytes)`)
      await refreshStorageEstimate()
    })
    .catch((error) => {
      errorMessage.value = `本機暫存寫入失敗：${error.message}`
      status.value = 'error'
      addEvent(errorMessage.value)
      if (recorder?.state === 'recording') recorder.stop()
    })
  pendingWrites.push(write)
  await write
}

async function startRecording() {
  if (!canStart.value) return
  errorMessage.value = ''
  status.value = 'requesting'
  revokePlaybackUrl()
  chunkCount.value = 0
  totalBytes.value = 0
  elapsedMs.value = 0
  sequence = 0
  pendingWrites = []
  recordingId = createRecordingId()
  localStorage.setItem(ACTIVE_RECORDING_KEY, recordingId)
  recoverableRecordingId.value = recordingId

  try {
    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    recorder = new MediaRecorder(stream, { mimeType: mimeType.value })
    recorder.addEventListener('dataavailable', (event) => void persistChunk(event.data))
    recorder.addEventListener('error', (event) => {
      errorMessage.value = `錄音器錯誤：${event.error?.message ?? '未知錯誤'}`
      status.value = 'error'
      addEvent(errorMessage.value)
    })
    recorder.addEventListener('stop', () => void finishRecording())
    startedAt = performance.now()
    recorder.start(TIMESLICE_MS)
    status.value = 'recording'
    elapsedTimer = window.setInterval(() => {
      elapsedMs.value = performance.now() - startedAt
    }, 250)
    await requestWakeLock()
    addEvent(`Recording started (${mimeType.value}, timeslice ${TIMESLICE_MS} ms)`)
  } catch (error) {
    errorMessage.value = `無法開始錄音：${error.message}`
    status.value = 'error'
    addEvent(errorMessage.value)
    cleanupMedia()
  }
}

function stopRecording() {
  if (recorder?.state !== 'recording') return
  status.value = 'stopping'
  recordedElapsedMs.value = performance.now() - startedAt
  elapsedMs.value = recordedElapsedMs.value
  clearInterval(elapsedTimer)
  recorder.requestData()
  recorder.stop()
  addEvent('Stop requested; waiting for final chunk')
}

async function finishRecording() {
  try {
    await Promise.all(pendingWrites)
    if (errorMessage.value) return
    await buildPlayback(recordingId)
    status.value = 'ready'
    addEvent('All chunks persisted and assembled')
  } catch (error) {
    errorMessage.value = `組檔失敗：${error.message}`
    status.value = 'error'
    addEvent(errorMessage.value)
  } finally {
    cleanupMedia()
    await releaseWakeLock()
  }
}

async function buildPlayback(id) {
  const chunks = await loadTechnicalAudioChunks(database, id)
  if (!chunks.length) throw new Error('找不到任何已保存的音訊分段')
  sequence = Math.max(...chunks.map((chunk) => chunk.sequence))
  chunkCount.value = chunks.length
  totalBytes.value = chunks.reduce((total, chunk) => total + chunk.blob.size, 0)
  mimeType.value = chunks[0].mimeType
  revokePlaybackUrl()
  playbackUrl.value = URL.createObjectURL(assembleAudioChunks(chunks, mimeType.value))
  await refreshStorageEstimate()
}

async function recoverRecording() {
  try {
    await buildPlayback(recoverableRecordingId.value)
    status.value = 'ready'
    addEvent(`Recovered recording ${recoverableRecordingId.value}`)
  } catch (error) {
    errorMessage.value = `恢復失敗：${error.message}`
    status.value = 'error'
  }
}

function capturePlaybackDuration(event) {
  playbackDuration.value = Math.round(event.target.duration * 1000)
  addEvent(`Playback metadata duration: ${playbackDuration.value} ms`)
}

function cleanupMedia() {
  clearInterval(elapsedTimer)
  stream?.getTracks().forEach((track) => track.stop())
  stream = null
  recorder = null
}

function revokePlaybackUrl() {
  if (playbackUrl.value) URL.revokeObjectURL(playbackUrl.value)
  playbackUrl.value = ''
  playbackDuration.value = null
}

async function copyReport() {
  const report = [
    `Standalone: ${standalone.value ? '是' : '否'}`,
    `MIME: ${mimeType.value ?? '不支援'}`,
    `Wake Lock supported: ${wakeLockSupported.value ? '是' : '否'}`,
    `Wake Lock enabled: ${useWakeLock.value ? '是' : '否'}`,
    `Chunks: ${chunkCount.value}`,
    `Bytes: ${totalBytes.value}`,
    `Recorded: ${recordedElapsedMs.value} ms`,
    `Playback: ${playbackDuration.value ?? '尚未取得'} ms`,
    `Storage: ${storageUsage.value} / ${storageQuota.value}`,
    '',
    ...eventLog.value,
  ].join('\n')
  await navigator.clipboard.writeText(report)
  copyMessage.value = '已複製，請連同 ①～⑦結果貼回 thread。'
}

function handleVisibilityChange() {
  addEvent(`visibilitychange: ${document.visibilityState}`)
  if (
    document.visibilityState === 'visible' &&
    status.value === 'recording' &&
    !wakeLockActive.value
  ) {
    void requestWakeLock()
  }
}

function requestFinalData() {
  if (recorder?.state === 'recording') recorder.requestData()
}
function handlePageHide(event) {
  addEvent(`pagehide: persisted=${event.persisted}`)
  requestFinalData()
}
function handlePageShow(event) {
  addEvent(`pageshow: persisted=${event.persisted}`)
}
function handlePopState() {
  addEvent('popstate received; navigation cannot be cancelled reliably')
  requestFinalData()
}

onMounted(async () => {
  standalone.value =
    window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true
  mimeType.value =
    typeof MediaRecorder === 'undefined'
      ? null
      : selectRecordingMimeType((type) => MediaRecorder.isTypeSupported(type))
  try {
    database = await openTechnicalAudioDatabase()
    indexedDbReady.value = true
    await refreshStorageEstimate()
    if (navigator.storage?.persist) {
      persistedStorage.value = (await navigator.storage.persist()) ? '是' : '否'
    }
    addEvent('Validation page ready')
  } catch (error) {
    errorMessage.value = `IndexedDB 初始化失敗：${error.message}`
    status.value = 'error'
  }

  document.addEventListener('visibilitychange', handleVisibilityChange)
  window.addEventListener('pagehide', handlePageHide)
  window.addEventListener('pageshow', handlePageShow)
  window.addEventListener('popstate', handlePopState)
})

onBeforeUnmount(() => {
  requestFinalData()
  cleanupMedia()
  void releaseWakeLock()
  revokePlaybackUrl()
  database?.close()
  document.removeEventListener('visibilitychange', handleVisibilityChange)
  window.removeEventListener('pagehide', handlePageHide)
  window.removeEventListener('pageshow', handlePageShow)
  window.removeEventListener('popstate', handlePopState)
})
</script>
