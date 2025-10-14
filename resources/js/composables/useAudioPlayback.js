/**
 * useAudioPlayback - 音頻播放組合式函數
 *
 * 封裝音頻播放邏輯，提供簡化的狀態管理和視覺回饋
 * 整合現有的 AudioPlayerService 進行音頻播放控制
 */

import { ref, computed, onUnmounted, readonly } from 'vue'
import audioPlayerService from '../services/AudioPlayerService.js'

/**
 * 播放狀態枚舉
 */
export const PlaybackState = {
  IDLE: 'idle', // 初始狀態，可點擊
  PLAYING: 'playing', // 播放中，不可點擊
  ERROR: 'error', // 錯誤狀態，可點擊重試
  RETRYING: 'retrying', // 重試中，不可點擊
}

/**
 * 音頻播放組合式函數
 * @param {string} audioUrl - 音頻 URL
 * @param {string|number} audioId - 音頻 ID（可選，預設使用 URL）
 * @returns {object} 播放狀態和控制方法
 */
export function useAudioPlayback(audioUrl, audioId = null) {
  // 使用 audioUrl 作為預設 ID
  const id = audioId || audioUrl

  // 響應式狀態
  const playbackState = ref(PlaybackState.IDLE)
  const error = ref(null)
  const retryCount = ref(0)

  // 配置
  const maxRetries = 3
  const playTimeout = 10000 // 10秒超時

  // 音頻元素引用
  let audioElement = null

  /**
   * 計算按鈕樣式類別
   */
  const buttonClasses = computed(() => {
    switch (playbackState.value) {
      case PlaybackState.PLAYING:
        return 'bg-blue-500 cursor-not-allowed'
      case PlaybackState.ERROR:
        return 'bg-red-500 hover:bg-red-600 cursor-pointer'
      case PlaybackState.RETRYING:
        return 'bg-yellow-500 cursor-not-allowed animate-pulse'
      default:
        return 'bg-gray-200 hover:bg-gray-300 cursor-pointer'
    }
  })

  /**
   * 計算按鈕標題
   */
  const buttonTitle = computed(() => {
    switch (playbackState.value) {
      case PlaybackState.PLAYING:
        return '正在播放...'
      case PlaybackState.ERROR:
        return `播放失敗，點擊重試 (${retryCount.value}/${maxRetries})`
      case PlaybackState.RETRYING:
        return `正在重試... (${retryCount.value}/${maxRetries})`
      default:
        return '點擊播放音頻'
    }
  })

  /**
   * 計算是否正在播放
   */
  const isPlaying = computed(() => playbackState.value === PlaybackState.PLAYING)

  /**
   * 計算是否有錯誤
   */
  const hasError = computed(() => playbackState.value === PlaybackState.ERROR)

  /**
   * 計算是否可以點擊
   */
  const isClickable = computed(
    () =>
      playbackState.value !== PlaybackState.PLAYING &&
      playbackState.value !== PlaybackState.RETRYING
  )

  /**
   * 計算是否正在重試
   */
  const isRetrying = computed(() => playbackState.value === PlaybackState.RETRYING)

  /**
   * 計算是否可以重試
   */
  const canRetry = computed(
    () => playbackState.value === PlaybackState.ERROR && retryCount.value < maxRetries
  )

  /**
   * 計算重試進度百分比
   */
  const retryProgress = computed(() => {
    if (maxRetries === 0) return 0
    return Math.round((retryCount.value / maxRetries) * 100)
  })

  /**
   * 驗證音頻 URL
   * @param {string} url - 音頻 URL
   * @returns {boolean}
   */
  function validateAudioUrl(url) {
    if (!url || typeof url !== 'string') {
      throw new Error('音頻 URL 無效')
    }

    // 檢查是否為有效的音頻檔案擴展名
    const audioExtensions = ['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac']
    const extension = url.split('.').pop()?.toLowerCase()

    if (!extension || !audioExtensions.includes(extension)) {
      console.warn('音頻檔案格式可能不受支援:', extension)
    }

    return true
  }

  /**
   * 創建音頻元素
   * @returns {HTMLAudioElement}
   */
  function createAudioElement() {
    if (audioElement) {
      return audioElement
    }

    audioElement = new Audio()
    audioElement.preload = 'none' // 不預載，節省頻寬
    audioElement.crossOrigin = 'anonymous' // 支援跨域音頻

    return audioElement
  }

  /**
   * 播放音頻
   * @returns {Promise<void>}
   */
  async function playAudio() {
    // 如果正在播放，直接返回
    if (playbackState.value === PlaybackState.PLAYING) {
      return
    }

    try {
      // 驗證音頻 URL
      validateAudioUrl(audioUrl)

      // 設置播放狀態
      playbackState.value = PlaybackState.PLAYING
      error.value = null

      // 創建音頻元素
      const audio = createAudioElement()

      // 設置音頻源（如果需要）
      if (audio.src !== audioUrl) {
        audio.src = audioUrl
      }

      // 設置事件監聽器
      setupAudioEventListeners(audio)

      // 使用 AudioPlayerService 播放音頻
      await audioPlayerService.play(id, audio, audioUrl)
    } catch (err) {
      console.error('音頻播放失敗:', err)
      await handleAudioError(err)
    }
  }

  /**
   * 設置音頻事件監聽器
   * @param {HTMLAudioElement} audio - 音頻元素
   */
  function setupAudioEventListeners(audio) {
    // 播放結束事件
    const handleEnded = () => {
      handleAudioEnded()
    }

    // 播放錯誤事件
    const handleError = async (event) => {
      const audioError = event.target.error
      await handleAudioError(audioError || new Error('音頻播放錯誤'))
    }

    // 添加事件監聽器
    audio.addEventListener('ended', handleEnded, { once: true })
    audio.addEventListener('error', handleError, { once: true })

    // 儲存清理函數
    audio._cleanup = () => {
      audio.removeEventListener('ended', handleEnded)
      audio.removeEventListener('error', handleError)
    }
  }

  /**
   * 處理音頻播放結束
   */
  function handleAudioEnded() {
    playbackState.value = PlaybackState.IDLE
    retryCount.value = 0
    error.value = null

    // 清理音頻元素事件監聽器
    if (audioElement && audioElement._cleanup) {
      audioElement._cleanup()
    }
  }

  /**
   * 處理音頻播放錯誤
   * @param {Error} err - 錯誤對象
   */
  async function handleAudioError(err) {
    playbackState.value = PlaybackState.ERROR
    error.value = await getErrorMessage(err)
    retryCount.value++

    console.error('音頻播放錯誤:', {
      audioId: id,
      audioUrl,
      error: err.message,
      retryCount: retryCount.value,
      friendlyMessage: error.value,
    })

    // 清理音頻元素事件監聽器
    if (audioElement && audioElement._cleanup) {
      audioElement._cleanup()
    }
  }

  /**
   * 檢查網路連線狀態
   * @returns {Promise<boolean>}
   */
  async function checkNetworkStatus() {
    // 基本的線上狀態檢查
    if (!navigator.onLine) {
      return false
    }

    // 嘗試發送一個小的網路請求來驗證連線
    try {
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), 3000) // 3秒超時

      const response = await fetch('/api/health-check', {
        method: 'HEAD',
        signal: controller.signal,
        cache: 'no-cache',
      })

      clearTimeout(timeoutId)
      return response.ok
    } catch (error) {
      // 如果健康檢查失敗，嘗試更簡單的檢查
      try {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), 2000)

        await fetch('/', {
          method: 'HEAD',
          signal: controller.signal,
          cache: 'no-cache',
        })

        clearTimeout(timeoutId)
        return true
      } catch (fallbackError) {
        return false
      }
    }
  }

  /**
   * 檢查音頻格式相容性
   * @param {string} audioUrl - 音頻 URL
   * @returns {object} 相容性檢查結果
   */
  function checkAudioCompatibility(audioUrl) {
    const audio = new Audio()
    const parts = audioUrl.split('.')
    const extension = parts.length > 1 ? parts.pop()?.toLowerCase() : null

    const formatInfo = {
      extension,
      isSupported: false,
      canPlay: 'no',
      mimeType: null,
      recommendation: null,
    }

    // MIME 類型映射
    const mimeTypes = {
      mp3: 'audio/mpeg',
      wav: 'audio/wav',
      ogg: 'audio/ogg',
      webm: 'audio/webm',
      m4a: 'audio/mp4',
      aac: 'audio/aac',
      flac: 'audio/flac',
    }

    formatInfo.mimeType = mimeTypes[extension]

    if (formatInfo.mimeType && audio.canPlayType) {
      formatInfo.canPlay = audio.canPlayType(formatInfo.mimeType)
      formatInfo.isSupported = formatInfo.canPlay !== ''
    }

    // 提供建議
    if (!formatInfo.isSupported) {
      if (extension === 'ogg') {
        formatInfo.recommendation = '建議使用 MP3 格式以獲得更好的相容性'
      } else if (extension === 'flac') {
        formatInfo.recommendation = '建議使用 MP3 或 AAC 格式'
      } else if (!extension) {
        formatInfo.recommendation = '音頻檔案缺少副檔名，無法判斷格式'
      } else {
        formatInfo.recommendation = '建議使用 MP3 格式以獲得最佳相容性'
      }
    }

    return formatInfo
  }

  /**
   * 獲取友善的錯誤訊息
   * @param {Error} err - 錯誤對象
   * @returns {Promise<string>}
   */
  async function getErrorMessage(err) {
    if (!err) return '未知錯誤'

    const message = err.message || err.toString()
    const errorCode = err.code || err.name

    // 網路相關錯誤 - 進行詳細檢查
    if (
      message.includes('NetworkError') ||
      message.includes('網路') ||
      errorCode === 'NetworkError'
    ) {
      const isOnline = await checkNetworkStatus()
      if (!isOnline) {
        return '網路連線中斷，請檢查網路設定後重試'
      } else {
        return '網路不穩定，請稍後重試'
      }
    }

    // 離線狀態
    if (!navigator.onLine) {
      return '目前處於離線狀態，請檢查網路連線'
    }

    // 格式不支援錯誤 - 進行相容性檢查
    if (
      message.includes('NotSupportedError') ||
      message.includes('不支援') ||
      errorCode === 'NotSupportedError'
    ) {
      const compatibility = checkAudioCompatibility(audioUrl)
      if (!compatibility.isSupported && compatibility.recommendation) {
        return `瀏覽器不支援 ${compatibility.extension?.toUpperCase()} 格式。${compatibility.recommendation}`
      }
      return '瀏覽器不支援此音頻格式'
    }

    // 播放被阻止錯誤
    if (
      message.includes('NotAllowedError') ||
      message.includes('阻止') ||
      errorCode === 'NotAllowedError'
    ) {
      return '瀏覽器阻止了音頻播放，請先點擊頁面任意位置後重試'
    }

    // 載入中斷錯誤
    if (message.includes('AbortError') || message.includes('中斷') || errorCode === 'AbortError') {
      return '音頻載入被中斷，請重試'
    }

    // 解碼錯誤
    if (
      message.includes('DecodeError') ||
      message.includes('decode') ||
      errorCode === 'DecodeError'
    ) {
      const compatibility = checkAudioCompatibility(audioUrl)
      if (compatibility.recommendation) {
        return `音頻檔案損壞或格式錯誤。${compatibility.recommendation}`
      }
      return '音頻檔案損壞或格式錯誤'
    }

    // 超時錯誤
    if (message.includes('超時') || message.includes('timeout')) {
      const isOnline = await checkNetworkStatus()
      if (!isOnline) {
        return '網路連線超時，請檢查網路狀態後重試'
      }
      return '載入超時，可能是網路較慢，請重試'
    }

    // URL 無效錯誤 (更具體的錯誤，需要先檢查)
    if (message.includes('URL 無效')) {
      return '音頻檔案路徑無效'
    }

    // URL 相關錯誤
    if (message.includes('音頻 URL') || message.includes('路徑')) {
      return '找不到音頻檔案，請確認檔案是否存在'
    }

    // 404 錯誤
    if (message.includes('404') || message.includes('Not Found')) {
      return '音頻檔案不存在，請確認檔案路徑'
    }

    // 403 錯誤
    if (message.includes('403') || message.includes('Forbidden')) {
      return '沒有權限存取此音頻檔案'
    }

    // 500 錯誤
    if (message.includes('500') || message.includes('Internal Server Error')) {
      return '伺服器錯誤，請稍後重試'
    }

    // CORS 錯誤
    if (message.includes('CORS') || message.includes('跨域')) {
      return '跨域存取被阻止，請聯繫管理員'
    }

    // 預設錯誤訊息
    return message || '播放失敗，請稍後再試'
  }

  /**
   * 重試播放（帶指數退避策略）
   * @returns {Promise<void>}
   */
  async function retryPlay() {
    // 檢查重試次數限制
    if (retryCount.value >= maxRetries) {
      console.warn('已達最大重試次數:', maxRetries)
      error.value = `已達最大重試次數 (${maxRetries})，請檢查網路連線或音頻檔案`
      return
    }

    console.log('重試播放音頻:', {
      audioId: id,
      retryCount: retryCount.value,
      maxRetries,
    })

    // 設置重試狀態（用於視覺指示）
    playbackState.value = 'retrying'

    // 指數退避策略：1秒、2秒、4秒，最大8秒
    const baseDelay = 1000
    const delay = Math.min(baseDelay * Math.pow(2, retryCount.value - 1), 8000)

    console.log(`重試延遲: ${delay}ms`)

    // 在重試前進行預檢查
    const networkOk = await checkNetworkStatus()
    if (!networkOk) {
      error.value = '網路連線問題，請檢查網路後重試'
      playbackState.value = PlaybackState.ERROR
      return
    }

    // 檢查音頻格式相容性
    const compatibility = checkAudioCompatibility(audioUrl)
    if (!compatibility.isSupported) {
      error.value = `瀏覽器不支援此音頻格式 (${compatibility.extension})`
      playbackState.value = PlaybackState.ERROR
      return
    }

    // 延遲重試
    await new Promise((resolve) => setTimeout(resolve, delay))

    // 重置狀態並重新播放
    playbackState.value = PlaybackState.IDLE

    try {
      await playAudio()
    } catch (retryError) {
      console.error('重試播放失敗:', retryError)
      await handleAudioError(retryError)
    }
  }

  /**
   * 停止播放
   */
  function stopAudio() {
    if (audioPlayerService.currentPlayingId.value === id) {
      audioPlayerService.stop()
    }

    playbackState.value = PlaybackState.IDLE
    error.value = null
    retryCount.value = 0
  }

  /**
   * 處理點擊事件
   * @returns {Promise<void>}
   */
  async function handleClick() {
    // 如果正在播放或重試中，不響應點擊
    if (
      playbackState.value === PlaybackState.PLAYING ||
      playbackState.value === PlaybackState.RETRYING
    ) {
      return
    }

    // 如果是錯誤狀態，執行重試
    if (playbackState.value === PlaybackState.ERROR) {
      await retryPlay()
    } else {
      // 正常播放
      await playAudio()
    }
  }

  /**
   * 監聽 AudioPlayerService 的全域事件
   */
  function setupGlobalEventListeners() {
    // 監聽播放結束事件
    audioPlayerService.on('ended', (data) => {
      if (data.audioId === id) {
        handleAudioEnded()
      }
    })

    // 監聽錯誤事件
    audioPlayerService.on('error', (data) => {
      if (data.audioId === id) {
        handleAudioError(new Error(data.error))
      }
    })

    // 監聽停止事件
    audioPlayerService.on('stop', (data) => {
      if (data.audioId === id) {
        handleAudioEnded()
      }
    })
  }

  /**
   * 清理資源
   */
  function cleanup() {
    // 停止播放
    stopAudio()

    // 清理音頻元素
    if (audioElement) {
      if (audioElement._cleanup) {
        audioElement._cleanup()
      }
      audioElement.src = ''
      audioElement = null
    }
  }

  // 設置全域事件監聽器
  setupGlobalEventListeners()

  // 組件卸載時清理資源
  onUnmounted(() => {
    cleanup()
  })

  // 返回公開的 API
  return {
    // 只讀狀態
    playbackState: readonly(playbackState),
    error: readonly(error),
    retryCount: readonly(retryCount),

    // 計算屬性
    buttonClasses,
    buttonTitle,
    isPlaying,
    hasError,
    isClickable,
    isRetrying,
    canRetry,
    retryProgress,

    // 方法
    handleClick,
    playAudio,
    stopAudio,
    retryPlay,

    // 配置
    maxRetries,

    // 工具方法
    checkNetworkStatus,
    checkAudioCompatibility,
  }
}

export default useAudioPlayback
