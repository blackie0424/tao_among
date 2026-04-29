/**
 * AudioNetworkService - 音頻網路載入服務
 *
 * 負責：音頻佇列管理、快取管理、網路事件監聽、背景網路監控。
 * 副作用集中於 init()，由呼叫端（Volume.vue onMounted）明確觸發。
 *
 * 純環境偵測邏輯由 NetworkOptimizer（utils/）提供。
 */

import networkOptimizer from '../utils/NetworkOptimizer.js'

class AudioNetworkService {
  constructor() {
    this.loadingQueue = new Map()
    this.preloadCache = new Map()
  }

  /**
   * 初始化服務：設定網路事件監聽器並開始背景監控。
   * 應在應用程式掛載後呼叫一次（呼叫端負責時機，此服務不自動初始化）。
   */
  init() {
    this.setupNetworkListeners()
    this.startNetworkMonitoring()
  }

  /**
   * 設置網路狀態監聽器
   */
  setupNetworkListeners() {
    window.addEventListener('online', () => {
      networkOptimizer.refresh()
      this.handleNetworkChange()
    })

    window.addEventListener('offline', () => {
      networkOptimizer.connectionInfo.isOnline = false
      this.handleNetworkChange()
    })

    if (navigator.connection) {
      navigator.connection.addEventListener('change', () => {
        networkOptimizer.refresh()
        this.handleNetworkChange()
      })
    }
  }

  /**
   * 觸發網路狀態變化事件
   */
  handleNetworkChange() {
    window.dispatchEvent(
      new CustomEvent('network-optimized', {
        detail: {
          connectionInfo: networkOptimizer.connectionInfo,
          adaptiveSettings: networkOptimizer.adaptiveSettings,
        },
      })
    )
  }

  /**
   * 開始背景網路監控（每 30 秒）
   */
  startNetworkMonitoring() {
    setInterval(() => {
      this.performNetworkTest()
    }, 30000)
  }

  /**
   * 執行輕量網路測試（更新 RTT 估計）
   * @returns {Promise<object>}
   */
  async performNetworkTest() {
    try {
      const startTime = performance.now()
      const response = await fetch('/favicon.ico?' + Date.now(), {
        method: 'HEAD',
        cache: 'no-cache',
      })
      const endTime = performance.now()
      const responseTime = endTime - startTime

      networkOptimizer.connectionInfo.actualRtt = responseTime
      if (Math.abs(responseTime - networkOptimizer.connectionInfo.rtt) > 200) {
        networkOptimizer.connectionInfo.rtt = responseTime
        networkOptimizer.adaptiveSettings = networkOptimizer.calculateAdaptiveSettings()
      }

      return { success: true, responseTime, timestamp: Date.now() }
    } catch (error) {
      return { success: false, error: error.message, timestamp: Date.now() }
    }
  }

  /**
   * 以自適應策略載入音頻（帶佇列限制與快取）
   * @param {string} audioUrl - 音頻 URL
   * @param {object} options - 載入選項（timeout 等）
   * @returns {Promise<HTMLAudioElement>}
   */
  async optimizedAudioLoad(audioUrl, options = {}) {
    const settings = networkOptimizer.adaptiveSettings
    const loadId = `${audioUrl}_${Date.now()}`

    if (this.preloadCache.has(audioUrl)) {
      return this.preloadCache.get(audioUrl)
    }

    if (this.loadingQueue.size >= settings.maxConcurrentLoads) {
      await this.waitForLoadingSlot()
    }

    this.loadingQueue.set(loadId, { audioUrl, startTime: Date.now() })

    try {
      const audio = await this.loadAudioWithStrategy(audioUrl, options)

      if (this.shouldCacheAudio(audioUrl)) {
        audio._cacheTime = Date.now()
        this.preloadCache.set(audioUrl, audio)
      }

      return audio
    } finally {
      this.loadingQueue.delete(loadId)
    }
  }

  /**
   * 建立 Audio 元素並依網路策略載入
   * @param {string} audioUrl - 音頻 URL
   * @param {object} options - 選項
   * @returns {Promise<HTMLAudioElement>}
   */
  async loadAudioWithStrategy(audioUrl, options) {
    const settings = networkOptimizer.adaptiveSettings
    const connectionInfo = networkOptimizer.connectionInfo
    const audio = new Audio()

    audio.preload = settings.preloadStrategy
    audio.crossOrigin = 'anonymous'

    if (connectionInfo.isSlowConnection) {
      audio.preload = 'none'
    }

    const timeout = (options.timeout || 10000) * settings.timeoutMultiplier

    return new Promise((resolve, reject) => {
      let timeoutId
      let progressTimeoutId

      const cleanup = () => {
        clearTimeout(timeoutId)
        clearTimeout(progressTimeoutId)
        audio.removeEventListener('canplaythrough', handleCanPlay)
        audio.removeEventListener('error', handleError)
        audio.removeEventListener('progress', handleProgress)
      }

      const handleCanPlay = () => {
        cleanup()
        resolve(audio)
      }

      const handleError = (event) => {
        cleanup()
        reject(new Error(event.target.error?.message || '音頻載入失敗'))
      }

      const handleProgress = () => {
        clearTimeout(progressTimeoutId)
        progressTimeoutId = setTimeout(() => {
          cleanup()
          reject(new Error('音頻載入進度停滯'))
        }, timeout / 2)
      }

      audio.addEventListener('canplaythrough', handleCanPlay, { once: true })
      audio.addEventListener('error', handleError, { once: true })

      if (settings.progressiveLoading) {
        audio.addEventListener('progress', handleProgress)
        progressTimeoutId = setTimeout(() => {
          cleanup()
          reject(new Error('音頻載入超時'))
        }, timeout / 2)
      }

      timeoutId = setTimeout(() => {
        cleanup()
        reject(new Error(`音頻載入超時 (${timeout}ms)`))
      }, timeout)

      audio.src = audioUrl
      if (settings.preloadStrategy !== 'none') {
        audio.load()
      }
    })
  }

  /**
   * 等待載入槽位空出
   * @returns {Promise<void>}
   */
  async waitForLoadingSlot() {
    return new Promise((resolve) => {
      const checkSlot = () => {
        if (this.loadingQueue.size < networkOptimizer.adaptiveSettings.maxConcurrentLoads) {
          resolve()
        } else {
          setTimeout(checkSlot, 100)
        }
      }
      checkSlot()
    })
  }

  /**
   * 判斷是否快取此音頻 URL
   * @param {string} audioUrl
   * @returns {boolean}
   */
  shouldCacheAudio(audioUrl) {
    const strategy = networkOptimizer.adaptiveSettings.cacheStrategy
    switch (strategy) {
      case 'aggressive':
        return true
      case 'moderate':
        return this.preloadCache.size < 10
      case 'conservative':
        return this.preloadCache.size < 5
      default:
        return false
    }
  }

  /**
   * 批次預載音頻（慢速網路或省流量模式自動跳過）
   * @param {Array<string>} audioUrls
   * @returns {Promise<void>}
   */
  async preloadAudios(audioUrls) {
    const { isSlowConnection, saveData } = networkOptimizer.connectionInfo
    if (isSlowConnection || saveData) return

    const maxPreload = Math.min(
      audioUrls.length,
      networkOptimizer.adaptiveSettings.maxConcurrentLoads
    )

    await Promise.allSettled(
      audioUrls.slice(0, maxPreload).map((url) =>
        this.optimizedAudioLoad(url, { timeout: 5000 }).catch(() => {
          // 預載失敗不影響主流程
        })
      )
    )
  }

  /**
   * 清除超過 maxAge 的快取
   * @param {number} maxAge - 毫秒，預設 5 分鐘
   */
  cleanupCache(maxAge = 300000) {
    const now = Date.now()
    for (const [url, audio] of this.preloadCache.entries()) {
      if (audio._cacheTime && now - audio._cacheTime > maxAge) {
        this.preloadCache.delete(url)
      }
    }
  }

  /**
   * 取得優化報告（診斷用）
   * @returns {object}
   */
  getOptimizationReport() {
    const connectionInfo = networkOptimizer.connectionInfo
    const adaptiveSettings = networkOptimizer.adaptiveSettings
    const recommendations = []

    if (connectionInfo.isSlowConnection) {
      recommendations.push('檢測到慢速網路，建議：')
      recommendations.push('• 減少同時播放的音頻數量')
      recommendations.push('• 使用較小的音頻檔案')
      recommendations.push('• 避免預載音頻')
    }

    if (connectionInfo.saveData) {
      recommendations.push('使用者啟用省流量模式，建議：')
      recommendations.push('• 僅在使用者明確要求時載入音頻')
    }

    if (this.preloadCache.size > 20) {
      recommendations.push('快取使用量較高，建議定期清理')
    }

    if (this.loadingQueue.size > 3) {
      recommendations.push('同時載入的音頻過多，可能影響性能')
    }

    return {
      connectionInfo,
      adaptiveSettings,
      cacheStats: {
        size: this.preloadCache.size,
        urls: Array.from(this.preloadCache.keys()),
      },
      loadingStats: {
        activeLoads: this.loadingQueue.size,
        loadingUrls: Array.from(this.loadingQueue.values()).map((item) => item.audioUrl),
      },
      recommendations,
    }
  }

  /**
   * 重置服務狀態
   */
  reset() {
    networkOptimizer.refresh()
    this.loadingQueue.clear()
    this.preloadCache.clear()
  }
}

const audioNetworkService = new AudioNetworkService()

export default audioNetworkService
