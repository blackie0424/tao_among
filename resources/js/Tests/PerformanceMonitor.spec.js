/**
 * PerformanceMonitor 單元測試（TDD）
 *
 * 驗證 PerformanceMonitor 的職責：
 * - isEnabled 不受環境變數影響（呼叫端負責 mock，工具不加測試守衛）
 * - startMonitoring 回傳監控會話
 * - 各 record 方法正確記錄時間
 * - reset 清除監控記錄
 */

import { describe, it, expect, beforeEach } from 'vitest'
import performanceMonitor from '../utils/PerformanceMonitor.js'

describe('PerformanceMonitor', () => {
  beforeEach(() => {
    performanceMonitor.reset()
  })

  describe('初始狀態', () => {
    it('isEnabled 應該預設為 true（不受 NODE_ENV 影響）', () => {
      expect(performanceMonitor.isEnabled).toBe(true)
    })

    it('metrics 應該初始為空', () => {
      expect(performanceMonitor.metrics.size).toBe(0)
    })

    it('thresholds 應包含預設閾值', () => {
      expect(performanceMonitor.thresholds.loadTime).toBeTypeOf('number')
      expect(performanceMonitor.thresholds.playDelay).toBeTypeOf('number')
      expect(performanceMonitor.thresholds.errorRate).toBeTypeOf('number')
    })
  })

  describe('startMonitoring', () => {
    it('應該回傳監控會話物件（不回傳 null）', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session).not.toBeNull()
      expect(session).toBeTypeOf('object')
    })

    it('會話應包含正確的 audioId 與 audioUrl', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session.audioId).toBe('audio-1')
      expect(session.audioUrl).toBe('test.mp3')
    })

    it('會話應包含 startTime（數字型別）', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session.startTime).toBeTypeOf('number')
      expect(session.startTime).toBeGreaterThan(0)
    })

    it('應將會話存入 metrics', () => {
      performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(performanceMonitor.metrics.has('audio-1')).toBe(true)
    })

    it('初始載入與播放時間應為 null', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session.loadStartTime).toBeNull()
      expect(session.loadEndTime).toBeNull()
      expect(session.playStartTime).toBeNull()
      expect(session.playEndTime).toBeNull()
    })
  })

  describe('recordLoadStart / recordLoadEnd', () => {
    it('應記錄載入開始時間', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      performanceMonitor.recordLoadStart('audio-1')

      expect(session.loadStartTime).toBeTypeOf('number')
    })

    it('應記錄載入完成時間', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      performanceMonitor.recordLoadStart('audio-1')

      performanceMonitor.recordLoadEnd('audio-1')

      expect(session.loadEndTime).toBeTypeOf('number')
      expect(session.loadEndTime).toBeGreaterThanOrEqual(session.loadStartTime)
    })

    it('未知 audioId 不應拋出錯誤', () => {
      expect(() => performanceMonitor.recordLoadStart('unknown-id')).not.toThrow()
    })
  })

  describe('recordPlayStart / recordPlayEnd', () => {
    it('應記錄播放開始時間', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      performanceMonitor.recordPlayStart('audio-1')

      expect(session.playStartTime).toBeTypeOf('number')
    })

    it('應記錄播放結束時間，並標記 completed', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      performanceMonitor.recordPlayStart('audio-1')

      performanceMonitor.recordPlayEnd('audio-1')

      expect(session.playEndTime).toBeTypeOf('number')
      expect(session.completed).toBe(true)
    })
  })

  describe('recordError', () => {
    it('應記錄錯誤訊息、名稱與堆疊', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      const error = new Error('播放失敗')

      performanceMonitor.recordError('audio-1', error)

      expect(session.error.message).toBe('播放失敗')
      expect(session.error.name).toBe('Error')
    })

    it('應記錄錯誤時間', () => {
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      performanceMonitor.recordError('audio-1', new Error('err'))

      expect(session.errorTime).toBeTypeOf('number')
    })
  })

  describe('getPerformanceStats', () => {
    it('無會話時應回傳零值統計', () => {
      const stats = performanceMonitor.getPerformanceStats()

      expect(stats.totalSessions).toBe(0)
      expect(stats.errorRate).toBe(0)
    })

    it('有完成會話時應計算 totalSessions', () => {
      performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      performanceMonitor.recordPlayEnd('audio-1')

      const stats = performanceMonitor.getPerformanceStats()

      expect(stats.totalSessions).toBe(1)
    })

    it('有錯誤會話時應計算 errorRate', () => {
      performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      performanceMonitor.recordError('audio-1', new Error('err'))

      const stats = performanceMonitor.getPerformanceStats()

      expect(stats.errorRate).toBe(1)
    })
  })

  describe('reset', () => {
    it('應清除所有監控記錄', () => {
      performanceMonitor.startMonitoring('audio-1', 'test.mp3')
      performanceMonitor.startMonitoring('audio-2', 'test2.mp3')

      performanceMonitor.reset()

      expect(performanceMonitor.metrics.size).toBe(0)
    })
  })

  describe('setEnabled', () => {
    it('應能停用監控（setEnabled(false)）', () => {
      performanceMonitor.setEnabled(false)
      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session).toBeNull()

      // 還原
      performanceMonitor.setEnabled(true)
    })

    it('應能重新啟用監控（setEnabled(true)）', () => {
      performanceMonitor.setEnabled(false)
      performanceMonitor.setEnabled(true)

      const session = performanceMonitor.startMonitoring('audio-1', 'test.mp3')

      expect(session).not.toBeNull()
    })
  })
})
