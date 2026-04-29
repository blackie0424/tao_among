/**
 * AnimationOptimizer 單元測試
 *
 * 驗證以下規格：
 * 1. calculateOptimizationLevel() 是純函式，不依賴 DOM
 * 2. init() 方法存在，並負責應用 CSS 變數與設定監聽器
 * 3. shouldEnableAnimation() 根據 optimizationLevel 回傳正確值
 *
 * Requirements: SRP - AnimationOptimizer 只負責計算優化等級，
 * 不在 import 時自動觸發副作用（DOM 操作、事件綁定）
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import animationOptimizer from '../utils/AnimationOptimizer.js'

/**
 * 提供 window.matchMedia 的測試替身
 * jsdom 預設不實作此 API
 */
function mockMatchMedia() {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
}

describe('AnimationOptimizer', () => {
  beforeEach(() => {
    mockMatchMedia()
    // 每次測試前重置實例狀態到已知值
    animationOptimizer.prefersReducedMotion = false
    animationOptimizer.isLowPerformanceDevice = false
    animationOptimizer.isLowPowerMode = false
    animationOptimizer.optimizationLevel = 'none'
    animationOptimizer.animationConfig = animationOptimizer.getOptimalAnimationConfig()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ─────────────────────────────────────────────
  // calculateOptimizationLevel()
  // ─────────────────────────────────────────────
  describe('calculateOptimizationLevel()', () => {
    it('prefersReducedMotion 為 true 時回傳 aggressive', () => {
      animationOptimizer.prefersReducedMotion = true

      expect(animationOptimizer.calculateOptimizationLevel()).toBe('aggressive')
    })

    it('isLowPerformanceDevice 與 isLowPowerMode 皆為 true 時回傳 aggressive', () => {
      animationOptimizer.isLowPerformanceDevice = true
      animationOptimizer.isLowPowerMode = true

      expect(animationOptimizer.calculateOptimizationLevel()).toBe('aggressive')
    })

    it('只有 isLowPerformanceDevice 為 true 時回傳 moderate', () => {
      animationOptimizer.isLowPerformanceDevice = true

      expect(animationOptimizer.calculateOptimizationLevel()).toBe('moderate')
    })

    it('所有旗標皆為 false 時回傳 none', () => {
      expect(animationOptimizer.calculateOptimizationLevel()).toBe('none')
    })

    it('不呼叫 document.querySelectorAll（純函式，不依賴 DOM）', () => {
      // 此測試在修正前會失敗：目前程式碼使用 document.querySelectorAll('*').length
      const spy = vi.spyOn(document, 'querySelectorAll')

      animationOptimizer.calculateOptimizationLevel()

      expect(spy).not.toHaveBeenCalled()
    })
  })

  // ─────────────────────────────────────────────
  // init()
  // ─────────────────────────────────────────────
  describe('init()', () => {
    it('方法存在', () => {
      // 此測試在修正前會失敗：目前沒有 init() 方法
      expect(typeof animationOptimizer.init).toBe('function')
    })

    it('呼叫後會應用 CSS 變數至 document', () => {
      // 此測試在修正前會失敗：init() 不存在
      const spy = vi.spyOn(document.documentElement.style, 'setProperty')

      animationOptimizer.init()

      expect(spy).toHaveBeenCalled()
    })

    it('呼叫後會設定 matchMedia 監聽器', () => {
      animationOptimizer.init()

      expect(window.matchMedia).toHaveBeenCalled()
    })
  })

  // ─────────────────────────────────────────────
  // shouldEnableAnimation()
  // ─────────────────────────────────────────────
  describe('shouldEnableAnimation()', () => {
    it('optimizationLevel 為 none 時 transition 回傳 true', () => {
      animationOptimizer.optimizationLevel = 'none'
      animationOptimizer.animationConfig = animationOptimizer.getOptimalAnimationConfig()

      expect(animationOptimizer.shouldEnableAnimation('transition')).toBe(true)
    })

    it('optimizationLevel 為 aggressive 時 transition 回傳 false', () => {
      animationOptimizer.optimizationLevel = 'aggressive'
      animationOptimizer.animationConfig = animationOptimizer.getOptimalAnimationConfig()

      expect(animationOptimizer.shouldEnableAnimation('transition')).toBe(false)
    })

    it('optimizationLevel 為 moderate 時 transition 回傳 true', () => {
      animationOptimizer.optimizationLevel = 'moderate'
      animationOptimizer.animationConfig = animationOptimizer.getOptimalAnimationConfig()

      expect(animationOptimizer.shouldEnableAnimation('transition')).toBe(true)
    })

    it('未知的動畫類型回傳預設值（enableAnimations）', () => {
      animationOptimizer.optimizationLevel = 'none'
      animationOptimizer.animationConfig = animationOptimizer.getOptimalAnimationConfig()

      expect(animationOptimizer.shouldEnableAnimation('unknown-type')).toBe(true)
    })
  })

  // ─────────────────────────────────────────────
  // getOptimalAnimationConfig()
  // ─────────────────────────────────────────────
  describe('getOptimalAnimationConfig()', () => {
    it('none 等級回傳完整動畫設定', () => {
      animationOptimizer.optimizationLevel = 'none'
      const config = animationOptimizer.getOptimalAnimationConfig()

      expect(config.enableTransitions).toBe(true)
      expect(config.enableAnimations).toBe(true)
      expect(config.enableComplexAnimations).toBe(true)
    })

    it('aggressive 等級回傳禁用動畫設定', () => {
      animationOptimizer.optimizationLevel = 'aggressive'
      const config = animationOptimizer.getOptimalAnimationConfig()

      expect(config.enableTransitions).toBe(false)
      expect(config.enableAnimations).toBe(false)
    })
  })
})
