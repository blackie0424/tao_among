/**
 * AudioNetworkService 單元測試
 *
 * AudioNetworkService 負責音頻佇列管理、快取管理、網路事件監聽。
 * 副作用集中於 init()，由呼叫端明確觸發。
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

// 提供 window.matchMedia 替身（jsdom 不支援）
function mockBrowserAPIs() {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query) => ({
      matches: false,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  })
  Object.defineProperty(navigator, 'onLine', { writable: true, value: true })
  Object.defineProperty(navigator, 'connection', { writable: true, value: null })
  global.fetch = vi.fn().mockResolvedValue({ ok: true })
  global.Audio = vi.fn(() => ({
    src: '',
    preload: 'metadata',
    crossOrigin: '',
    load: vi.fn(),
    addEventListener: vi.fn((event, handler, opts) => {
      if (event === 'canplaythrough' && !opts?.once === false) {
        // 立刻觸發，方便測試
      }
    }),
    removeEventListener: vi.fn(),
    _triggerCanPlay: null,
  }))
}

describe('AudioNetworkService', () => {
  let audioNetworkService

  beforeEach(async () => {
    vi.resetModules()
    mockBrowserAPIs()
    const mod = await import('../services/AudioNetworkService.js')
    audioNetworkService = mod.default
    audioNetworkService.loadingQueue.clear()
    audioNetworkService.preloadCache.clear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ─────────────────────────────────────────────
  // 基本結構
  // ─────────────────────────────────────────────
  describe('基本結構', () => {
    it('檔案存在且可 import（GREEN：新建後通過）', () => {
      expect(audioNetworkService).toBeDefined()
    })

    it('有 loadingQueue（Map）', () => {
      expect(audioNetworkService.loadingQueue).toBeInstanceOf(Map)
    })

    it('有 preloadCache（Map）', () => {
      expect(audioNetworkService.preloadCache).toBeInstanceOf(Map)
    })

    it('有 init() 方法', () => {
      expect(typeof audioNetworkService.init).toBe('function')
    })

    it('有 optimizedAudioLoad() 方法', () => {
      expect(typeof audioNetworkService.optimizedAudioLoad).toBe('function')
    })

    it('有 shouldCacheAudio() 方法', () => {
      expect(typeof audioNetworkService.shouldCacheAudio).toBe('function')
    })

    it('有 cleanupCache() 方法', () => {
      expect(typeof audioNetworkService.cleanupCache).toBe('function')
    })

    it('有 reset() 方法', () => {
      expect(typeof audioNetworkService.reset).toBe('function')
    })
  })

  // ─────────────────────────────────────────────
  // init()
  // ─────────────────────────────────────────────
  describe('init()', () => {
    it('呼叫後會設定 window.addEventListener（online/offline）', () => {
      const spy = vi.spyOn(window, 'addEventListener')
      audioNetworkService.init()
      const calls = spy.mock.calls.map((c) => c[0])
      expect(calls).toContain('online')
      expect(calls).toContain('offline')
    })

    it('呼叫後會啟動定期監控（setInterval）', () => {
      const spy = vi.spyOn(globalThis, 'setInterval')
      audioNetworkService.init()
      expect(spy).toHaveBeenCalled()
    })
  })

  // ─────────────────────────────────────────────
  // shouldCacheAudio()
  // ─────────────────────────────────────────────
  describe('shouldCacheAudio()', () => {
    it('cacheStrategy = aggressive 時回傳 true', async () => {
      // 模擬 networkOptimizer 的 adaptiveSettings
      const { default: networkOptimizer } = await import('../utils/NetworkOptimizer.js')
      networkOptimizer.adaptiveSettings.cacheStrategy = 'aggressive'
      expect(audioNetworkService.shouldCacheAudio('test.mp3')).toBe(true)
    })

    it('cacheStrategy = conservative 且快取未滿時回傳 true', async () => {
      const { default: networkOptimizer } = await import('../utils/NetworkOptimizer.js')
      networkOptimizer.adaptiveSettings.cacheStrategy = 'conservative'
      audioNetworkService.preloadCache.clear()
      expect(audioNetworkService.shouldCacheAudio('test.mp3')).toBe(true)
    })

    it('cacheStrategy = conservative 且快取已滿（≥5）時回傳 false', async () => {
      const { default: networkOptimizer } = await import('../utils/NetworkOptimizer.js')
      networkOptimizer.adaptiveSettings.cacheStrategy = 'conservative'
      for (let i = 0; i < 5; i++) {
        audioNetworkService.preloadCache.set(`url${i}`, {})
      }
      expect(audioNetworkService.shouldCacheAudio('test.mp3')).toBe(false)
    })
  })

  // ─────────────────────────────────────────────
  // cleanupCache()
  // ─────────────────────────────────────────────
  describe('cleanupCache()', () => {
    it('移除超過 maxAge 的快取項目', () => {
      const oldAudio = { _cacheTime: Date.now() - 400000 }
      const freshAudio = { _cacheTime: Date.now() }
      audioNetworkService.preloadCache.set('old.mp3', oldAudio)
      audioNetworkService.preloadCache.set('fresh.mp3', freshAudio)

      audioNetworkService.cleanupCache(300000)

      expect(audioNetworkService.preloadCache.has('old.mp3')).toBe(false)
      expect(audioNetworkService.preloadCache.has('fresh.mp3')).toBe(true)
    })
  })

  // ─────────────────────────────────────────────
  // reset()
  // ─────────────────────────────────────────────
  describe('reset()', () => {
    it('清空佇列與快取', () => {
      audioNetworkService.loadingQueue.set('a', {})
      audioNetworkService.preloadCache.set('b', {})

      audioNetworkService.reset()

      expect(audioNetworkService.loadingQueue.size).toBe(0)
      expect(audioNetworkService.preloadCache.size).toBe(0)
    })
  })

  // ─────────────────────────────────────────────
  // getOptimizationReport()
  // ─────────────────────────────────────────────
  describe('getOptimizationReport()', () => {
    it('回傳包含 connectionInfo 和 adaptiveSettings 的報告', () => {
      const report = audioNetworkService.getOptimizationReport()
      expect(report).toHaveProperty('connectionInfo')
      expect(report).toHaveProperty('adaptiveSettings')
      expect(report).toHaveProperty('cacheStats')
      expect(report).toHaveProperty('loadingStats')
      expect(report).toHaveProperty('recommendations')
    })
  })
})
