/**
 * AudioPlayerService - 音頻播放服務
 *
 * 提供全域音頻播放狀態管理，支援播放、暫停、停止功能
 * 並提供播放狀態事件監聽機制
 */

import { ref, reactive } from 'vue'

class AudioPlayerService {
  constructor() {
    // 當前播放的音頻 ID
    this.currentPlayingId = ref(null)

    // 播放狀態
    this.playbackState = reactive({
      isPlaying: false,
      isPaused: false,
      currentTime: 0,
      duration: 0,
      volume: 1.0,
      error: null,
    })

    // 當前音頻元素
    this.currentAudioElement = null

    // 事件監聽器
    this.eventListeners = {
      play: [],
      pause: [],
      stop: [],
      ended: [],
      error: [],
      timeupdate: [],
      loadedmetadata: [],
      stateSync: [], // 全域狀態同步事件
    }

    // 綁定事件處理方法
    this.handlePlay = this.handlePlay.bind(this)
    this.handlePause = this.handlePause.bind(this)
    this.handleEnded = this.handleEnded.bind(this)
    this.handleError = this.handleError.bind(this)
    this.handleTimeUpdate = this.handleTimeUpdate.bind(this)
    this.handleLoadedMetadata = this.handleLoadedMetadata.bind(this)
  }

  /**
   * 簡化的播放方法，適用於短音頻檔案
   * @param {string|number} audioId - 音頻 ID
   * @param {HTMLAudioElement} audioElement - 音頻元素
   * @param {string} audioUrl - 音頻 URL
   * @returns {Promise<void>}
   */
  async playShortAudio(audioId, audioElement, audioUrl) {
    try {
      // 驗證參數
      if (!audioUrl) {
        throw new Error('音頻 URL 不存在')
      }
      if (!audioElement) {
        throw new Error('音頻元素不存在')
      }

      // 確保音頻播放的互斥性
      this.ensureMutualExclusion(audioId)

      // 設置新的播放狀態
      this.currentPlayingId.value = audioId
      this.currentAudioElement = audioElement
      this.playbackState.error = null

      // 檢查網路連線狀態
      if (!navigator.onLine) {
        throw new Error('NetworkError: 無網路連線')
      }

      // 設置音頻源（簡化版本，適用於短音頻）
      if (audioElement.src !== audioUrl) {
        audioElement.src = audioUrl
      }

      // 添加基本事件監聽器
      this.addShortAudioEventListeners(audioElement)

      // 開始播放
      await audioElement.play()

      this.playbackState.isPlaying = true
      this.playbackState.isPaused = false

      // 觸發播放事件
      this.emit('play', { audioId, audioUrl })
    } catch (error) {
      console.error('短音頻播放失敗:', error)

      // 提供友善的錯誤訊息
      let errorMessage = await this.getErrorMessage(error)

      this.playbackState.error = errorMessage
      this.emit('error', { audioId, error: errorMessage })

      // 重置狀態
      this.reset()
      throw error
    }
  }

  /**
   * 播放音頻
   * @param {number} audioId - 音頻 ID
   * @param {HTMLAudioElement} audioElement - 音頻元素
   * @param {string} audioUrl - 音頻 URL
   */
  async play(audioId, audioElement, audioUrl) {
    try {
      // 驗證音頻 URL
      if (!audioUrl) {
        throw new Error('音頻 URL 不存在')
      }

      // 驗證音頻元素
      if (!audioElement) {
        throw new Error('音頻元素不存在')
      }

      // 確保音頻播放的互斥性
      this.ensureMutualExclusion(audioId)

      // 如果是同一個音頻，切換播放/暫停狀態
      if (this.currentPlayingId.value === audioId) {
        if (this.playbackState.isPlaying) {
          this.pause()
        } else if (this.playbackState.isPaused) {
          await this.resume()
        }
        return
      }

      // 設置新的音頻
      this.currentPlayingId.value = audioId
      this.currentAudioElement = audioElement
      this.playbackState.error = null

      // 檢查網路連線狀態
      if (!navigator.onLine) {
        throw new Error('NetworkError: 無網路連線')
      }

      // 設置音頻源
      if (audioElement.src !== audioUrl) {
        audioElement.src = audioUrl
        // 重新載入音頻元素
        audioElement.load()
      }

      // 添加事件監聽器
      this.addAudioEventListeners(audioElement)

      // 檢查瀏覽器是否支援該音頻格式
      if (audioElement.canPlayType) {
        const mimeType = this.getMimeTypeFromUrl(audioUrl)
        if (mimeType && audioElement.canPlayType(mimeType) === '') {
          throw new Error(`瀏覽器不支援此音頻格式: ${mimeType}`)
        }
      }

      // 設置超時處理
      const playPromise = audioElement.play()
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('播放超時，請檢查網路連線')), 10000)
      })

      // 開始播放（帶超時）
      await Promise.race([playPromise, timeoutPromise])

      this.playbackState.isPlaying = true
      this.playbackState.isPaused = false

      // 觸發播放事件
      this.emit('play', { audioId, audioUrl })
    } catch (error) {
      console.error('播放音頻失敗:', error)

      // 提供更友善的錯誤訊息
      let errorMessage = await this.getErrorMessage(error)

      this.playbackState.error = errorMessage
      this.emit('error', { audioId, error: errorMessage })

      // 重置狀態
      this.reset()
    }
  }

  /**
   * 檢查網路連線狀態
   * @returns {Promise<boolean>}
   */
  async checkNetworkStatus() {
    if (!navigator.onLine) {
      return false
    }

    try {
      const controller = new AbortController()
      const timeoutId = setTimeout(() => controller.abort(), 3000)

      const response = await fetch('/api/health-check', {
        method: 'HEAD',
        signal: controller.signal,
        cache: 'no-cache',
      })

      clearTimeout(timeoutId)
      return response.ok
    } catch (error) {
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
  checkAudioCompatibility(audioUrl) {
    const audio = new Audio()
    const extension = audioUrl.split('.').pop()?.toLowerCase()

    const formatInfo = {
      extension,
      isSupported: false,
      canPlay: 'no',
      mimeType: null,
      recommendation: null,
    }

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
   * @param {Error} error - 錯誤對象
   * @returns {Promise<string>} 友善的錯誤訊息
   */
  async getErrorMessage(error) {
    const message = error.message || error.toString()
    const errorCode = error.code || error.name

    // 網路相關錯誤
    if (
      message.includes('NetworkError') ||
      message.includes('網路') ||
      errorCode === 'NetworkError'
    ) {
      const isOnline = await this.checkNetworkStatus()
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

    // 格式不支援錯誤
    if (error.name === 'NotSupportedError' || message.includes('不支援')) {
      return '瀏覽器不支援此音頻格式或來源'
    }

    // 播放被阻止錯誤
    if (error.name === 'NotAllowedError') {
      return '瀏覽器阻止了音頻播放，請先點擊頁面任意位置後重試'
    }

    // 載入中斷錯誤
    if (error.name === 'AbortError') {
      return '音頻載入被中斷，請重試'
    }

    // 解碼錯誤
    if (message.includes('DecodeError') || message.includes('decode')) {
      return '音頻檔案損壞或格式錯誤'
    }

    // 超時錯誤
    if (message.includes('超時') || message.includes('timeout')) {
      const isOnline = await this.checkNetworkStatus()
      if (!isOnline) {
        return '網路連線超時，請檢查網路狀態後重試'
      }
      return '載入超時，可能是網路較慢，請重試'
    }

    // URL 相關錯誤
    if (message.includes('音頻 URL 不存在')) {
      return '音頻檔案路徑錯誤'
    }

    if (message.includes('音頻元素不存在')) {
      return '音頻播放器初始化失敗'
    }

    // HTTP 狀態碼錯誤
    if (message.includes('404') || message.includes('Not Found')) {
      return '音頻檔案不存在，請確認檔案路徑'
    }

    if (message.includes('403') || message.includes('Forbidden')) {
      return '沒有權限存取此音頻檔案'
    }

    if (message.includes('500') || message.includes('Internal Server Error')) {
      return '伺服器錯誤，請稍後重試'
    }

    // CORS 錯誤
    if (message.includes('CORS') || message.includes('跨域')) {
      return '跨域存取被阻止，請聯繫管理員'
    }

    return error.message || '未知錯誤'
  }

  /**
   * 暫停播放
   */
  pause() {
    if (this.currentAudioElement && this.playbackState.isPlaying) {
      this.currentAudioElement.pause()
      this.playbackState.isPlaying = false
      this.playbackState.isPaused = true

      this.emit('pause', { audioId: this.currentPlayingId.value })
    }
  }

  /**
   * 恢復播放
   */
  async resume() {
    if (this.currentAudioElement && this.playbackState.isPaused) {
      try {
        await this.currentAudioElement.play()
        this.playbackState.isPlaying = true
        this.playbackState.isPaused = false

        this.emit('play', { audioId: this.currentPlayingId.value })
      } catch (error) {
        console.error('恢復播放失敗:', error)
        this.playbackState.error = error.message
        this.emit('error', { audioId: this.currentPlayingId.value, error })
      }
    }
  }

  /**
   * 停止播放
   */
  stop() {
    if (this.currentAudioElement) {
      this.currentAudioElement.pause()
      this.currentAudioElement.currentTime = 0

      // 移除事件監聽器（嘗試移除所有可能的監聽器）
      this.removeAudioEventListeners(this.currentAudioElement)
      this.removeShortAudioEventListeners(this.currentAudioElement)

      const audioId = this.currentPlayingId.value
      this.reset()

      this.emit('stop', { audioId })
    }
  }

  /**
   * 設置音量
   * @param {number} volume - 音量 (0.0 - 1.0)
   */
  setVolume(volume) {
    const clampedVolume = Math.max(0, Math.min(1, volume))
    this.playbackState.volume = clampedVolume

    if (this.currentAudioElement) {
      this.currentAudioElement.volume = clampedVolume
    }
  }

  /**
   * 跳轉到指定時間
   * @param {number} time - 時間（秒）
   */
  seekTo(time) {
    if (this.currentAudioElement) {
      this.currentAudioElement.currentTime = time
      this.playbackState.currentTime = time
    }
  }

  /**
   * 強制停止所有音頻播放並重置狀態
   */
  forceStopAll() {
    if (this.currentAudioElement) {
      try {
        this.currentAudioElement.pause()
        this.currentAudioElement.currentTime = 0

        // 移除所有事件監聽器
        this.removeAudioEventListeners(this.currentAudioElement)
        this.removeShortAudioEventListeners(this.currentAudioElement)
      } catch (error) {
        console.warn('強制停止音頻時發生錯誤:', error)
      }
    }

    const audioId = this.currentPlayingId.value
    this.reset()

    if (audioId) {
      this.emit('stop', { audioId })
    }
  }

  /**
   * 確保音頻播放的互斥性
   * @param {string|number} newAudioId - 新的音頻 ID
   */
  ensureMutualExclusion(newAudioId) {
    // 如果當前有其他音頻在播放，強制停止
    if (this.currentPlayingId.value && this.currentPlayingId.value !== newAudioId) {
      this.forceStopAll()
    }

    // 如果是同一個音頻且正在播放，先停止
    if (this.currentPlayingId.value === newAudioId && this.playbackState.isPlaying) {
      this.stop()
    }
  }

  /**
   * 檢查指定音頻是否正在播放
   * @param {number} audioId - 音頻 ID
   * @returns {boolean}
   */
  isPlaying(audioId) {
    return this.currentPlayingId.value === audioId && this.playbackState.isPlaying
  }

  /**
   * 檢查指定音頻是否已暫停
   * @param {number} audioId - 音頻 ID
   * @returns {boolean}
   */
  isPaused(audioId) {
    return this.currentPlayingId.value === audioId && this.playbackState.isPaused
  }

  /**
   * 獲取當前播放狀態
   * @returns {object}
   */
  getPlaybackState() {
    return {
      currentPlayingId: this.currentPlayingId.value,
      ...this.playbackState,
    }
  }

  /**
   * 獲取全域播放狀態（用於狀態同步）
   * @returns {object}
   */
  getGlobalState() {
    return {
      hasActivePlayback: this.currentPlayingId.value !== null,
      currentPlayingId: this.currentPlayingId.value,
      isPlaying: this.playbackState.isPlaying,
      isPaused: this.playbackState.isPaused,
      hasError: this.playbackState.error !== null,
      error: this.playbackState.error,
    }
  }

  /**
   * 檢查是否有任何音頻正在播放
   * @returns {boolean}
   */
  hasActivePlayback() {
    return this.currentPlayingId.value !== null && this.playbackState.isPlaying
  }

  /**
   * 添加事件監聽器
   * @param {string} event - 事件名稱
   * @param {function} callback - 回調函數
   */
  on(event, callback) {
    if (this.eventListeners[event]) {
      this.eventListeners[event].push(callback)
    }
  }

  /**
   * 移除事件監聽器
   * @param {string} event - 事件名稱
   * @param {function} callback - 回調函數
   */
  off(event, callback) {
    if (this.eventListeners[event]) {
      const index = this.eventListeners[event].indexOf(callback)
      if (index > -1) {
        this.eventListeners[event].splice(index, 1)
      }
    }
  }

  /**
   * 觸發事件
   * @param {string} event - 事件名稱
   * @param {object} data - 事件數據
   */
  emit(event, data) {
    if (this.eventListeners[event]) {
      this.eventListeners[event].forEach((callback) => {
        try {
          callback(data)
        } catch (error) {
          console.error(`事件監聽器執行錯誤 (${event}):`, error)
        }
      })
    }

    // 觸發全域狀態同步事件
    this.emitStateSync(event, data)
  }

  /**
   * 觸發狀態同步事件
   * @param {string} event - 原始事件名稱
   * @param {object} data - 事件數據
   */
  emitStateSync(event, data) {
    const globalState = this.getGlobalState()

    // 觸發狀態同步事件，供其他組件監聽
    if (this.eventListeners['stateSync']) {
      this.eventListeners['stateSync'].forEach((callback) => {
        try {
          callback({
            originalEvent: event,
            originalData: data,
            globalState,
            timestamp: Date.now(),
          })
        } catch (error) {
          console.error('狀態同步事件監聽器執行錯誤:', error)
        }
      })
    }
  }

  /**
   * 添加短音頻元素事件監聽器（簡化版本）
   * @param {HTMLAudioElement} audioElement - 音頻元素
   */
  addShortAudioEventListeners(audioElement) {
    audioElement.addEventListener('play', this.handlePlay)
    audioElement.addEventListener('ended', this.handleEnded)
    audioElement.addEventListener('error', this.handleError)
  }

  /**
   * 添加音頻元素事件監聽器
   * @param {HTMLAudioElement} audioElement - 音頻元素
   */
  addAudioEventListeners(audioElement) {
    audioElement.addEventListener('play', this.handlePlay)
    audioElement.addEventListener('pause', this.handlePause)
    audioElement.addEventListener('ended', this.handleEnded)
    audioElement.addEventListener('error', this.handleError)
    audioElement.addEventListener('timeupdate', this.handleTimeUpdate)
    audioElement.addEventListener('loadedmetadata', this.handleLoadedMetadata)
  }

  /**
   * 移除短音頻元素事件監聽器
   * @param {HTMLAudioElement} audioElement - 音頻元素
   */
  removeShortAudioEventListeners(audioElement) {
    audioElement.removeEventListener('play', this.handlePlay)
    audioElement.removeEventListener('ended', this.handleEnded)
    audioElement.removeEventListener('error', this.handleError)
  }

  /**
   * 移除音頻元素事件監聽器
   * @param {HTMLAudioElement} audioElement - 音頻元素
   */
  removeAudioEventListeners(audioElement) {
    audioElement.removeEventListener('play', this.handlePlay)
    audioElement.removeEventListener('pause', this.handlePause)
    audioElement.removeEventListener('ended', this.handleEnded)
    audioElement.removeEventListener('error', this.handleError)
    audioElement.removeEventListener('timeupdate', this.handleTimeUpdate)
    audioElement.removeEventListener('loadedmetadata', this.handleLoadedMetadata)
  }

  /**
   * 處理播放事件
   */
  handlePlay() {
    this.playbackState.isPlaying = true
    this.playbackState.isPaused = false
  }

  /**
   * 處理暫停事件
   */
  handlePause() {
    this.playbackState.isPlaying = false
  }

  /**
   * 處理播放結束事件
   */
  handleEnded() {
    const audioId = this.currentPlayingId.value

    // 移除事件監聽器
    if (this.currentAudioElement) {
      this.removeAudioEventListeners(this.currentAudioElement)
      this.removeShortAudioEventListeners(this.currentAudioElement)
    }

    // 重置狀態
    this.reset()

    // 觸發結束事件
    this.emit('ended', { audioId })
  }

  /**
   * 處理錯誤事件
   */
  handleError(event) {
    const error = event.target.error
    console.error('音頻播放錯誤:', error)

    this.playbackState.error = error ? error.message : '未知錯誤'
    this.emit('error', {
      audioId: this.currentPlayingId.value,
      error: this.playbackState.error,
    })

    this.reset()
  }

  /**
   * 處理時間更新事件
   */
  handleTimeUpdate(event) {
    this.playbackState.currentTime = event.target.currentTime
    this.emit('timeupdate', {
      audioId: this.currentPlayingId.value,
      currentTime: this.playbackState.currentTime,
      duration: this.playbackState.duration,
    })
  }

  /**
   * 處理元數據載入事件
   */
  handleLoadedMetadata(event) {
    this.playbackState.duration = event.target.duration
    this.emit('loadedmetadata', {
      audioId: this.currentPlayingId.value,
      duration: this.playbackState.duration,
    })
  }

  /**
   * 從 URL 獲取 MIME 類型
   * @param {string} url - 音頻 URL
   * @returns {string|null}
   */
  getMimeTypeFromUrl(url) {
    const extension = url.split('.').pop()?.toLowerCase()
    const mimeTypes = {
      mp3: 'audio/mpeg',
      wav: 'audio/wav',
      ogg: 'audio/ogg',
      webm: 'audio/webm',
      m4a: 'audio/mp4',
      aac: 'audio/aac',
    }
    return mimeTypes[extension] || null
  }

  /**
   * 重置播放狀態
   */
  reset() {
    this.currentPlayingId.value = null
    this.currentAudioElement = null
    this.playbackState.isPlaying = false
    this.playbackState.isPaused = false
    this.playbackState.currentTime = 0
    this.playbackState.duration = 0
    this.playbackState.error = null
  }

  /**
   * 銷毀服務，清理資源
   */
  destroy() {
    this.stop()

    // 清空所有事件監聽器
    Object.keys(this.eventListeners).forEach((event) => {
      this.eventListeners[event] = []
    })
  }
}

// 創建單例實例
const audioPlayerService = new AudioPlayerService()

export default audioPlayerService
