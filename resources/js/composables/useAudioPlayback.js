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
  const isClickable = computed(() => playbackState.value !== PlaybackState.PLAYING)

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
      handleAudioError(err)
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
    const handleError = (event) => {
      const audioError = event.target.error
      handleAudioError(audioError || new Error('音頻播放錯誤'))
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
  function handleAudioError(err) {
    playbackState.value = PlaybackState.ERROR
    error.value = getErrorMessage(err)
    retryCount.value++

    console.error('音頻播放錯誤:', {
      audioId: id,
      audioUrl,
      error: err.message,
      retryCount: retryCount.value,
    })

    // 清理音頻元素事件監聽器
    if (audioElement && audioElement._cleanup) {
      audioElement._cleanup()
    }
  }

  /**
   * 獲取友善的錯誤訊息
   * @param {Error} err - 錯誤對象
   * @returns {string}
   */
  function getErrorMessage(err) {
    if (!err) return '未知錯誤'

    const message = err.message || err.toString()

    // 網路相關錯誤
    if (message.includes('NetworkError') || message.includes('網路') || !navigator.onLine) {
      return '網路連線問題，請檢查網路狀態'
    }

    // 格式不支援錯誤
    if (message.includes('NotSupportedError') || message.includes('不支援')) {
      return '瀏覽器不支援此音頻格式'
    }

    // 播放被阻止錯誤
    if (message.includes('NotAllowedError') || message.includes('阻止')) {
      return '瀏覽器阻止了音頻播放，請先與頁面互動'
    }

    // 載入中斷錯誤
    if (message.includes('AbortError') || message.includes('中斷')) {
      return '音頻載入被中斷'
    }

    // 解碼錯誤
    if (message.includes('DecodeError') || message.includes('decode')) {
      return '音頻檔案損壞或格式錯誤'
    }

    // 超時錯誤
    if (message.includes('超時') || message.includes('timeout')) {
      return '播放超時，請檢查網路連線'
    }

    // URL 無效錯誤 (更具體的錯誤，需要先檢查)
    if (message.includes('URL 無效')) {
      return '音頻 URL 無效'
    }

    // URL 相關錯誤
    if (message.includes('音頻 URL') || message.includes('路徑')) {
      return '音頻檔案路徑錯誤'
    }

    // 預設錯誤訊息
    return message || '播放失敗，請稍後再試'
  }

  /**
   * 重試播放
   * @returns {Promise<void>}
   */
  async function retryPlay() {
    // 檢查重試次數限制
    if (retryCount.value >= maxRetries) {
      console.warn('已達最大重試次數:', maxRetries)
      error.value = `已達最大重試次數 (${maxRetries})`
      return
    }

    console.log('重試播放音頻:', { audioId: id, retryCount: retryCount.value })

    // 重置狀態並重新播放
    playbackState.value = PlaybackState.IDLE

    // 延遲重試，避免過於頻繁
    const delay = Math.min(1000 * Math.pow(2, retryCount.value - 1), 5000) // 指數退避，最大5秒
    await new Promise((resolve) => setTimeout(resolve, delay))

    await playAudio()
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
    // 如果正在播放，不響應點擊
    if (playbackState.value === PlaybackState.PLAYING) {
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

    // 方法
    handleClick,
    playAudio,
    stopAudio,
    retryPlay,

    // 配置
    maxRetries,
  }
}

export default useAudioPlayback
