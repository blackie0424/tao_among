/**
 * NetworkOptimizer 單元測試
 *
 * NetworkOptimizer 只負責讀取網路環境並計算設定——純函式，無副作用。
 * 不含事件綁定、定時器、快取或音頻載入邏輯。
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

describe('NetworkOptimizer', () => {
  let networkOptimizer

  beforeEach(async () => {
    vi.resetModules()
    // 重置 navigator mock
    Object.defineProperty(navigator, 'onLine', { writable: true, value: true })
    Object.defineProperty(navigator, 'connection', { writable: true, value: null })
    // 每次重新 import 以取得乾淨的實例
    const mod = await import('../utils/NetworkOptimizer.js')
    networkOptimizer = mod.default
    networkOptimizer.refresh()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ─────────────────────────────────────────────
  // 無 NODE_ENV 守衛（純瀏覽器 API 讀取）
  // ─────────────────────────────────────────────
  describe('constructor 不含 NODE_ENV 守衛', () => {
    it('constructor 不應包含 NODE_ENV 判斷', async () => {
      // 此測試在修正前會失敗：舊版有 process.env.NODE_ENV !== 'test'
      const { default: mod } = await import('../utils/NetworkOptimizer.js')
      // 若建構子有 NODE_ENV 守衛，某些屬性會是預設值而非實際計算結果
      // 新版應永遠執行 getConnectionInfo() 和 calculateAdaptiveSettings()
      expect(typeof mod.connectionInfo).toBe('object')
      expect(typeof mod.adaptiveSettings).toBe('object')
      expect(typeof mod.adaptiveSettings.maxConcurrentLoads).toBe('number')
    })
  })

  // ─────────────────────────────────────────────
  // 無互動職責（不含佇列/快取/事件/定時器）
  // ─────────────────────────────────────────────
  describe('不含互動職責', () => {
    it('不應有 loadingQueue 屬性', () => {
      // 此測試在修正前會失敗：舊版有 this.loadingQueue = new Map()
      expect(networkOptimizer.loadingQueue).toBeUndefined()
    })

    it('不應有 preloadCache 屬性', () => {
      // 此測試在修正前會失敗：舊版有 this.preloadCache = new Map()
      expect(networkOptimizer.preloadCache).toBeUndefined()
    })

    it('不應有 setupNetworkListeners 方法', () => {
      // 此測試在修正前會失敗：舊版有此方法
      expect(networkOptimizer.setupNetworkListeners).toBeUndefined()
    })

    it('不應有 optimizedAudioLoad 方法', () => {
      // 此測試在修正前會失敗：舊版有此方法
      expect(networkOptimizer.optimizedAudioLoad).toBeUndefined()
    })

    it('不應有 startNetworkMonitoring 方法', () => {
      expect(networkOptimizer.startNetworkMonitoring).toBeUndefined()
    })
  })

  // ─────────────────────────────────────────────
  // getConnectionInfo()
  // ─────────────────────────────────────────────
  describe('getConnectionInfo()', () => {
    it('回傳包含 isOnline 的物件', () => {
      const info = networkOptimizer.getConnectionInfo()
      expect(typeof info.isOnline).toBe('boolean')
    })

    it('navigator.onLine = false 時 isOnline 為 false', () => {
      Object.defineProperty(navigator, 'onLine', { writable: true, value: false })
      const info = networkOptimizer.getConnectionInfo()
      expect(info.isOnline).toBe(false)
    })

    it('無 navigator.connection 時使用預設值', () => {
      const info = networkOptimizer.getConnectionInfo()
      expect(info.effectiveType).toBe('4g')
      expect(info.isSlowConnection).toBe(false)
    })

    it('navigator.connection.effectiveType = 2g 時 isSlowConnection 為 true', () => {
      Object.defineProperty(navigator, 'connection', {
        writable: true,
        value: { effectiveType: '2g', downlink: 0.5, rtt: 500, saveData: false },
      })
      networkOptimizer.refresh()
      expect(networkOptimizer.connectionInfo.isSlowConnection).toBe(true)
    })
  })

  // ─────────────────────────────────────────────
  // calculateAdaptiveSettings()
  // ─────────────────────────────────────────────
  describe('calculateAdaptiveSettings()', () => {
    it('快速網路回傳 maxConcurrentLoads = 4', () => {
      networkOptimizer.connectionInfo.isSlowConnection = false
      networkOptimizer.connectionInfo.effectiveType = '4g'
      expect(networkOptimizer.calculateAdaptiveSettings().maxConcurrentLoads).toBe(4)
    })

    it('慢速網路回傳 maxConcurrentLoads = 1', () => {
      networkOptimizer.connectionInfo.isSlowConnection = true
      expect(networkOptimizer.calculateAdaptiveSettings().maxConcurrentLoads).toBe(1)
    })

    it('3g 網路回傳 maxConcurrentLoads = 2', () => {
      networkOptimizer.connectionInfo.isSlowConnection = false
      networkOptimizer.connectionInfo.effectiveType = '3g'
      expect(networkOptimizer.calculateAdaptiveSettings().maxConcurrentLoads).toBe(2)
    })

    it('不呼叫 document 或 window（純計算）', () => {
      const docSpy = vi.spyOn(document, 'querySelectorAll')
      networkOptimizer.calculateAdaptiveSettings()
      expect(docSpy).not.toHaveBeenCalled()
    })
  })

  // ─────────────────────────────────────────────
  // refresh()
  // ─────────────────────────────────────────────
  describe('refresh()', () => {
    it('方法存在', () => {
      expect(typeof networkOptimizer.refresh).toBe('function')
    })

    it('呼叫後更新 connectionInfo', () => {
      Object.defineProperty(navigator, 'onLine', { writable: true, value: false })
      networkOptimizer.refresh()
      expect(networkOptimizer.connectionInfo.isOnline).toBe(false)
    })
  })
})
