/**
 * NetworkOptimizer - 網路優化工具
 *
 * 針對低網速環境提供音頻載入和播放優化
 */

class NetworkOptimizer {
  constructor() {
    this.connectionInfo = this.getConnectionInfo()
    this.adaptiveSettings = this.calculateAdaptiveSettings()
    this.loadingQueue = new Map()
    this.preloadCache = new Map()

    // 在測試環境中跳過網路監聽和監控
    if (process.env.NODE_ENV !== 'test') {
      // 監聽網路狀態變化
      this.setupNetworkListeners()

      // 定期更新網路資訊
      this.startNetworkMonitoring()
    }
  }

  /**
   * 獲取連線資訊
   * @returns {object}
   */
  getConnectionInfo() {
    const info = {
      isOnline: navigator.onLine,
      effectiveType: '4g',
      downlink: 10,
      rtt: 100,
      saveData: false,
      isSlowConnection: false,
      estimatedBandwidth: 10000, // kbps
    }

    if (navigator.connection) {
      const conn = navigator.connection
      info.effectiveType = conn.effectiveType || '4g'
      info.downlink = conn.downlink || 10
      info.rtt = conn.rtt || 100
      info.saveData = conn.saveData || false

      // 計算估計頻寬 (kbps)
      info.estimatedBandwidth = (conn.downlink || 10) * 1000

      // 判斷是否為慢速連線
      info.isSlowConnection =
        conn.effectiveType === 'slow-2g' ||
        conn.effectiveType === '2g' ||
        conn.downlink < 1.5 ||
        conn.saveData
    }

    return info
  }

  /**
   * 計算自適應設定
   * @returns {object}
   */
  calculateAdaptiveSettings() {
    const conn = this.connectionInfo

    const settings = {
      maxConcurrentLoads: 3,
      preloadStrategy: 'metadata',
      compressionLevel: 'medium',
      timeoutMultiplier: 1,
      retryStrategy: 'exponential',
      cacheStrategy: 'aggressive',
      progressiveLoading: false,
    }

    if (conn.isSlowConnection) {
      // 慢速網路設定
      settings.maxConcurrentLoads = 1
      settings.preloadStrategy = 'none'
      settings.compressionLevel = 'high'
      settings.timeoutMultiplier = 3
      settings.retryStrategy = 'linear'
      settings.cacheStrategy = 'conservative'
      settings.progressiveLoading = true
    } else if (conn.effectiveType === '3g') {
      // 中速網路設定
      settings.maxConcurrentLoads = 2
      settings.preloadStrategy = 'metadata'
      settings.compressionLevel = 'medium'
      settings.timeoutMultiplier = 2
      settings.retryStrategy = 'exponential'
      settings.cacheStrategy = 'moderate'
      settings.progressiveLoading = false
    } else {
      // 快速網路設定
      settings.maxConcurrentLoads = 4
      settings.preloadStrategy = 'auto'
      settings.compressionLevel = 'low'
      settings.timeoutMultiplier = 1
      settings.retryStrategy = 'exponential'
      settings.cacheStrategy = 'aggressive'
      settings.progressiveLoading = false
    }

    return settings
  }

  /**
   * 設置網路監聽器
   */
  setupNetworkListeners() {
    // 監聽線上/離線狀態
    window.addEventListener('online', () => {
      this.connectionInfo.isOnline = true
      this.handleNetworkChange()
    })

    window.addEventListener('offline', () => {
      this.connectionInfo.isOnline = false
      this.handleNetworkChange()
    })

    // 監聽連線變化
    if (navigator.connection) {
      navigator.connection.addEventListener('change', () => {
        this.connectionInfo = this.getConnectionInfo()
        this.handleNetworkChange()
      })
    }
  }

  /**
   * 處理網路狀態變化
   */
  handleNetworkChange() {
    this.adaptiveSettings = this.calculateAdaptiveSettings()

    // 觸發網路狀態變化事件
    window.dispatchEvent(
      new CustomEvent('network-optimized', {
        detail: {
          connectionInfo: this.connectionInfo,
          adaptiveSettings: this.adaptiveSettings,
        },
      })
    )

    console.log('網路狀態已更新:', this.connectionInfo)
  }

  /**
   * 開始網路監控
   */
  startNetworkMonitoring() {
    // 每30秒檢查一次網路狀態
    setInterval(() => {
      this.performNetworkTest()
    }, 30000)
  }

  /**
   * 執行網路測試
   * @returns {Promise<object>}
   */
  async performNetworkTest() {
    try {
      const startTime = performance.now()

      // 使用小檔案測試網路速度
      const response = await fetch('/favicon.ico?' + Date.now(), {
        method: 'HEAD',
        cache: 'no-cache',
      })

      const endTime = performance.now()
      const responseTime = endTime - startTime

      // 更新 RTT 估計
      this.connectionInfo.actualRtt = responseTime

      // 如果響應時間明顯變化，更新設定
      if (Math.abs(responseTime - this.connectionInfo.rtt) > 200) {
        this.connectionInfo.rtt = responseTime
        this.adaptiveSettings = this.calculateAdaptiveSettings()
      }

      return {
        success: true,
        responseTime,
        timestamp: Date.now(),
      }
    } catch (error) {
      console.warn('網路測試失敗:', error)
      return {
        success: false,
        error: error.message,
        timestamp: Date.now(),
      }
    }
  }

  /**
   * 優化音頻載入策略
   * @param {string} audioUrl - 音頻 URL
   * @param {object} options - 載入選項
   * @returns {Promise<HTMLAudioElement>}
   */
  async optimizedAudioLoad(audioUrl, options = {}) {
    const settings = this.adaptiveSettings
    const loadId = `${audioUrl}_${Date.now()}`

    // 檢查快取
    if (this.preloadCache.has(audioUrl)) {
      console.log('從快取載入音頻:', audioUrl)
      return this.preloadCache.get(audioUrl)
    }

    // 檢查並發載入限制
    if (this.loadingQueue.size >= settings.maxConcurrentLoads) {
      await this.waitForLoadingSlot()
    }

    // 開始載入
    this.loadingQueue.set(loadId, { audioUrl, startTime: Date.now() })

    try {
      const audio = await this.loadAudioWithStrategy(audioUrl, options)

      // 根據快取策略決定是否快取
      if (this.shouldCacheAudio(audioUrl)) {
        this.preloadCache.set(audioUrl, audio)
      }

      return audio
    } finally {
      this.loadingQueue.delete(loadId)
    }
  }

  /**
   * 使用策略載入音頻
   * @param {string} audioUrl - 音頻 URL
   * @param {object} options - 載入選項
   * @returns {Promise<HTMLAudioElement>}
   */
  async loadAudioWithStrategy(audioUrl, options) {
    const settings = this.adaptiveSettings
    const audio = new Audio()

    // 設置預載策略
    audio.preload = settings.preloadStrategy
    audio.crossOrigin = 'anonymous'

    // 低網速優化
    if (this.connectionInfo.isSlowConnection) {
      audio.preload = 'none'

      // 設置較小的緩衝區
      if ('mozAudioChannelType' in audio) {
        audio.mozAudioChannelType = 'content'
      }
    }

    // 設置超時
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
        // 重置進度超時
        clearTimeout(progressTimeoutId)
        progressTimeoutId = setTimeout(() => {
          cleanup()
          reject(new Error('音頻載入進度停滯'))
        }, timeout / 2)
      }

      // 設置事件監聽器
      audio.addEventListener('canplaythrough', handleCanPlay, { once: true })
      audio.addEventListener('error', handleError, { once: true })

      if (settings.progressiveLoading) {
        audio.addEventListener('progress', handleProgress)
        progressTimeoutId = setTimeout(() => {
          cleanup()
          reject(new Error('音頻載入超時'))
        }, timeout / 2)
      }

      // 設置總超時
      timeoutId = setTimeout(() => {
        cleanup()
        reject(new Error(`音頻載入超時 (${timeout}ms)`))
      }, timeout)

      // 開始載入
      audio.src = audioUrl
      if (settings.preloadStrategy !== 'none') {
        audio.load()
      }
    })
  }

  /**
   * 等待載入槽位
   * @returns {Promise<void>}
   */
  async waitForLoadingSlot() {
    return new Promise((resolve) => {
      const checkSlot = () => {
        if (this.loadingQueue.size < this.adaptiveSettings.maxConcurrentLoads) {
          resolve()
        } else {
          setTimeout(checkSlot, 100)
        }
      }
      checkSlot()
    })
  }

  /**
   * 判斷是否應該快取音頻
   * @param {string} audioUrl - 音頻 URL
   * @returns {boolean}
   */
  shouldCacheAudio(audioUrl) {
    const settings = this.adaptiveSettings

    switch (settings.cacheStrategy) {
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
   * 預載音頻
   * @param {Array<string>} audioUrls - 音頻 URL 陣列
   * @returns {Promise<void>}
   */
  async preloadAudios(audioUrls) {
    if (this.connectionInfo.isSlowConnection || this.connectionInfo.saveData) {
      console.log('慢速網路或省流量模式，跳過預載')
      return
    }

    const settings = this.adaptiveSettings
    const maxPreload = Math.min(audioUrls.length, settings.maxConcurrentLoads)

    console.log(`開始預載 ${maxPreload} 個音頻檔案`)

    const preloadPromises = audioUrls.slice(0, maxPreload).map(async (url) => {
      try {
        await this.optimizedAudioLoad(url, { timeout: 5000 })
        console.log('預載完成:', url)
      } catch (error) {
        console.warn('預載失敗:', url, error.message)
      }
    })

    await Promise.allSettled(preloadPromises)
  }

  /**
   * 清理快取
   * @param {number} maxAge - 最大快取時間（毫秒）
   */
  cleanupCache(maxAge = 300000) {
    // 預設5分鐘
    const now = Date.now()

    for (const [url, audio] of this.preloadCache.entries()) {
      if (audio._cacheTime && now - audio._cacheTime > maxAge) {
        this.preloadCache.delete(url)
        console.log('清理過期快取:', url)
      }
    }
  }

  /**
   * 獲取網路優化報告
   * @returns {object}
   */
  getOptimizationReport() {
    return {
      connectionInfo: this.connectionInfo,
      adaptiveSettings: this.adaptiveSettings,
      cacheStats: {
        size: this.preloadCache.size,
        urls: Array.from(this.preloadCache.keys()),
      },
      loadingStats: {
        activeLoads: this.loadingQueue.size,
        loadingUrls: Array.from(this.loadingQueue.values()).map((item) => item.audioUrl),
      },
      recommendations: this.getOptimizationRecommendations(),
    }
  }

  /**
   * 獲取優化建議
   * @returns {Array<string>}
   */
  getOptimizationRecommendations() {
    const recommendations = []
    const conn = this.connectionInfo

    if (conn.isSlowConnection) {
      recommendations.push('檢測到慢速網路，建議：')
      recommendations.push('• 減少同時播放的音頻數量')
      recommendations.push('• 使用較小的音頻檔案')
      recommendations.push('• 避免預載音頻')
    }

    if (conn.saveData) {
      recommendations.push('使用者啟用省流量模式，建議：')
      recommendations.push('• 僅在使用者明確要求時載入音頻')
      recommendations.push('• 提供音頻品質選擇')
    }

    if (this.preloadCache.size > 20) {
      recommendations.push('快取使用量較高，建議定期清理')
    }

    if (this.loadingQueue.size > 3) {
      recommendations.push('同時載入的音頻過多，可能影響性能')
    }

    return recommendations
  }

  /**
   * 重置優化器
   */
  reset() {
    this.connectionInfo = this.getConnectionInfo()
    this.adaptiveSettings = this.calculateAdaptiveSettings()
    this.loadingQueue.clear()
    this.preloadCache.clear()
  }
}

// 創建全域實例
const networkOptimizer = new NetworkOptimizer()

export default networkOptimizer
