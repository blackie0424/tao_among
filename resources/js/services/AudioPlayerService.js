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
   * 播放音頻
   * @param {number} audioId - 音頻 ID
   * @param {HTMLAudioElement} audioElement - 音頻元素
   * @param {string} audioUrl - 音頻 URL
   */
  async play(audioId, audioElement, audioUrl) {
    try {
      // 如果當前有其他音頻在播放，先停止
      if (this.currentAudioElement && this.currentPlayingId.value !== audioId) {
        this.stop()
      }

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

      // 設置音頻源
      if (audioElement.src !== audioUrl) {
        audioElement.src = audioUrl
      }

      // 添加事件監聽器
      this.addAudioEventListeners(audioElement)

      // 開始播放
      await audioElement.play()

      this.playbackState.isPlaying = true
      this.playbackState.isPaused = false

      // 觸發播放事件
      this.emit('play', { audioId, audioUrl })
    } catch (error) {
      console.error('播放音頻失敗:', error)
      this.playbackState.error = error.message
      this.emit('error', { audioId, error })

      // 重置狀態
      this.reset()
    }
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

      // 移除事件監聽器
      this.removeAudioEventListeners(this.currentAudioElement)

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
    this.reset()
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
