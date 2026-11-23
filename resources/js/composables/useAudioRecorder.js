/**
 * useAudioRecorder - 錄音組合式函數
 *
 * 抽自新增魚發音頁（CreateFishAudio.vue）與「大家怎麼說」共用：
 * 全平台優先用 M4A（audio/mp4），其次 audio/aac；滿 maxSeconds 秒自動停止。
 * 傳入 canvasRef 時會在錄音中畫出聲音波形。
 * reset() 會讓進行中的錄音作廢：之後才完成的 onstop 或麥克風授權不會再寫回錄音。
 */
import { ref, computed, nextTick, onBeforeUnmount } from 'vue'

export const RecordingErrorKind = {
  UNSUPPORTED: 'unsupported', // 瀏覽器不支援錄音或格式
  PERMISSION: 'permission', // 沒有麥克風權限或沒有麥克風
}

const UNSUPPORTED_FORMAT_MESSAGE = '瀏覽器不支援錄音格式，請使用現代瀏覽器 (Chrome、Firefox、Safari、Edge)'
const UNSUPPORTED_RECORDER_MESSAGE = '瀏覽器不支援錄音，請改用 Chrome 或 Edge。'
const PERMISSION_MESSAGE = '無法取得麥克風權限'
const WAVE_AMPLITUDE = 1.5 // 振幅放大倍率，建議 1.5~2.5
const WAVE_FFT_SIZE = 256
const WAVE_COLOR = '#4d7f99'
const MS_PER_SECOND = 1000
const LOG_PREFIX = '[audio-recorder]'

function pickMimeType() {
  if (MediaRecorder.isTypeSupported('audio/mp4')) return 'audio/mp4' // M4A (AAC)，iOS、Chrome、Firefox、Edge、Safari 皆可
  if (MediaRecorder.isTypeSupported('audio/aac')) return 'audio/aac' // 純 AAC（降級）
  return ''
}

const stopTracks = (stream) => stream?.getTracks().forEach((track) => track.stop())

export function useAudioRecorder({ maxSeconds = 5, canvasRef = null } = {}) {
  const isRecording = ref(false)
  const audioBlob = ref(null)
  const audioUrl = ref('')
  const error = ref('')
  const errorKind = ref('')
  const remaining = ref(maxSeconds)
  const autoStopped = ref(false)
  const recordedSeconds = ref(0) // 這段錄音實際錄了幾秒（試聽顯示總秒數用）

  let session = 0 // 每次開始或重設就加一；舊的非同步工作發現編號不同就放棄
  let mediaRecorder = null
  let timer = null
  let interval = null
  let audioContext = null
  let analyser = null
  let animationId = null
  let currentStream = null

  // 錄成 audio/aac 時存成 aac，其餘一律 m4a
  const ext = computed(() => (audioBlob.value?.type === 'audio/aac' ? 'aac' : 'm4a'))

  function fail(kind, message, stream = currentStream) {
    errorKind.value = kind
    error.value = message
    stopTracks(stream)
  }

  function drawWave() {
    if (!analyser || !canvasRef?.value) return
    const canvas = canvasRef.value
    const ctx = canvas.getContext('2d')
    const bufferLength = analyser.fftSize
    const dataArray = new Uint8Array(bufferLength)
    analyser.getByteTimeDomainData(dataArray)

    ctx.clearRect(0, 0, canvas.width, canvas.height)
    ctx.lineWidth = 2
    ctx.strokeStyle = WAVE_COLOR
    ctx.beginPath()

    const sliceWidth = canvas.width / bufferLength
    let x = 0
    for (let i = 0; i < bufferLength; i++) {
      const v = dataArray[i] / 128.0
      const y = (v - 1) * (canvas.height / WAVE_AMPLITUDE) + canvas.height / 2
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

  function safeCloseAudioContext() {
    if (!audioContext) return
    try {
      audioContext.close()
    } catch (e) {
      console.warn(`${LOG_PREFIX} 關閉 AudioContext 失敗`, e)
    }
    audioContext = null
  }

  function clearTimers() {
    clearTimeout(timer)
    clearInterval(interval)
    if (animationId) cancelAnimationFrame(animationId)
  }

  async function startWaveform(stream) {
    if (!canvasRef) return
    audioContext = new (window.AudioContext || window.webkitAudioContext)()
    analyser = audioContext.createAnalyser()
    analyser.fftSize = WAVE_FFT_SIZE
    audioContext.createMediaStreamSource(stream).connect(analyser)
    await nextTick() // 確保 canvas 已渲染
    drawWave()
  }

  function startCountdown() {
    timer = setTimeout(() => {
      autoStopped.value = true
      stop()
      recordedSeconds.value = maxSeconds // 滿時間：計時器與倒數同時觸發，倒數可能還差一秒
    }, maxSeconds * MS_PER_SECOND)
    interval = setInterval(() => {
      if (remaining.value > 0) remaining.value--
    }, MS_PER_SECOND)
  }

  function attachRecorderHandlers(recorder, stream, mimeType, mySession) {
    const chunks = [] // 每段錄音各自一份，被作廢的舊錄音延遲送來的片段不會混進新的錄音
    recorder.ondataavailable = (e) => {
      chunks.push(e.data)
    }
    recorder.onstop = () => {
      stopTracks(stream)
      if (mySession !== session) return // 這段錄音已被作廢（返回或重設），不寫回
      audioBlob.value = new Blob(chunks, { type: mimeType })
      audioUrl.value = URL.createObjectURL(audioBlob.value)
      isRecording.value = false
      clearTimers()
      safeCloseAudioContext()
    }
  }

  async function beginRecording(stream, mySession) {
    if (mySession !== session) return stopTracks(stream) // 等授權的時候已經返回或重設
    currentStream = stream
    const mimeType = pickMimeType()
    if (!mimeType) return fail(RecordingErrorKind.UNSUPPORTED, UNSUPPORTED_FORMAT_MESSAGE, stream)
    try {
      mediaRecorder = new MediaRecorder(stream, { mimeType })
    } catch (e) {
      return fail(RecordingErrorKind.UNSUPPORTED, UNSUPPORTED_RECORDER_MESSAGE, stream)
    }
    mediaRecorder.start()
    isRecording.value = true
    startCountdown()
    attachRecorderHandlers(mediaRecorder, stream, mimeType, mySession)
    await startWaveform(stream)
  }

  function start() {
    const mySession = ++session
    error.value = ''
    errorKind.value = ''
    autoStopped.value = false
    recordedSeconds.value = 0
    audioBlob.value = null
    audioUrl.value = ''
    remaining.value = maxSeconds

    if (typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      fail(RecordingErrorKind.UNSUPPORTED, UNSUPPORTED_RECORDER_MESSAGE)
      return Promise.resolve()
    }
    return navigator.mediaDevices
      .getUserMedia({ audio: true })
      .then((stream) => beginRecording(stream, mySession))
      .catch(() => {
        if (mySession !== session) return
        fail(RecordingErrorKind.PERMISSION, PERMISSION_MESSAGE)
        isRecording.value = false
      })
  }

  function stop() {
    if (mediaRecorder && isRecording.value) {
      recordedSeconds.value = maxSeconds - remaining.value
      mediaRecorder.stop()
      isRecording.value = false
      clearTimers()
      remaining.value = 0
      safeCloseAudioContext()
    }
  }

  // 丟掉目前的錄音（含進行中的）並回到初始狀態
  function reset() {
    const wasRecording = isRecording.value
    session++
    if (wasRecording) mediaRecorder?.stop()
    isRecording.value = false
    clearTimers()
    safeCloseAudioContext()
    audioBlob.value = null
    audioUrl.value = ''
    error.value = ''
    errorKind.value = ''
    autoStopped.value = false
    recordedSeconds.value = 0
    remaining.value = maxSeconds
  }

  onBeforeUnmount(() => {
    clearTimers()
    safeCloseAudioContext()
  })

  return { isRecording, audioBlob, audioUrl, error, errorKind, remaining, autoStopped, recordedSeconds, ext, start, stop, reset }
}
