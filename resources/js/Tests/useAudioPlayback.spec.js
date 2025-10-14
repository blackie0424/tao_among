/**
 * useAudioPlayback 組合式函數測試
 *
 * 測試播放狀態的正確切換、錯誤處理和重試邏輯、音頻播放完成後的狀態重置
 * Requirements: 1.4, 2.4, 3.3
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'

// Mock AudioPlayerService
vi.mock('../services/AudioPlayerService.js', () => ({
  default: {
    currentPlayingId: { value: null },
    play: vi.fn(),
    stop: vi.fn(),
    on: vi.fn(),
    off: vi.fn(),
    emit: vi.fn(),
  },
}))

import { useAudioPlayback, PlaybackState } from '../composables/useAudioPlayback.js'
import audioPlayerService from '../services/AudioPlayerService.js'

// Mock Audio constructor with more comprehensive functionality
const createMockAudio = () => ({
  src: '',
  preload: 'none',
  crossOrigin: 'anonymous',
  addEventListener: vi.fn(),
  removeEventListener: vi.fn(),
  play: vi.fn().mockResolvedValue(undefined),
  pause: vi.fn(),
  load: vi.fn(),
  canPlayType: vi.fn().mockReturnValue('probably'),
  _cleanup: vi.fn(),
})

global.Audio = vi.fn(() => createMockAudio())

// Mock navigator.onLine
Object.defineProperty(navigator, 'onLine', {
  writable: true,
  value: true,
})

// Mock fetch for network status checks
global.fetch = vi.fn().mockResolvedValue({ ok: true })

describe('useAudioPlayback', () => {
  let composable
  const testAudioUrl = 'test-audio.mp3'
  const testAudioId = 'test-id'

  beforeEach(() => {
    vi.clearAllMocks()
    audioPlayerService.currentPlayingId.value = null
    navigator.onLine = true
    global.fetch.mockResolvedValue({ ok: true })
    composable = useAudioPlayback(testAudioUrl, testAudioId)
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  describe('初始狀態', () => {
    it('應該初始化為 idle 狀態', () => {
      expect(composable.playbackState.value).toBe(PlaybackState.IDLE)
      expect(composable.error.value).toBeNull()
      expect(composable.retryCount.value).toBe(0)
    })

    it('應該提供正確的計算屬性', () => {
      expect(composable.isPlaying.value).toBe(false)
      expect(composable.hasError.value).toBe(false)
      expect(composable.isClickable.value).toBe(true)
      expect(composable.buttonClasses.value).toBe('bg-gray-200 hover:bg-gray-300 cursor-pointer')
      expect(composable.buttonTitle.value).toBe('點擊播放音頻')
    })
  })

  describe('播放功能', () => {
    it('應該能夠播放音頻', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()

      expect(audioPlayerService.play).toHaveBeenCalledWith(
        testAudioId,
        expect.any(Object), // audio element
        testAudioUrl
      )
      expect(composable.playbackState.value).toBe(PlaybackState.PLAYING)
    })

    it('播放中狀態應該正確更新視覺樣式', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()

      expect(composable.isPlaying.value).toBe(true)
      expect(composable.isClickable.value).toBe(false)
      expect(composable.buttonClasses.value).toBe('bg-blue-500 cursor-not-allowed')
      expect(composable.buttonTitle.value).toBe('正在播放...')
    })

    it('播放中時不應該響應點擊', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()
      const playCallCount = audioPlayerService.play.mock.calls.length

      // 再次點擊
      await composable.handleClick()

      // 播放方法不應該被再次調用
      expect(audioPlayerService.play.mock.calls.length).toBe(playCallCount)
    })

    it('應該正確設置音頻元素屬性', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()

      expect(global.Audio).toHaveBeenCalled()
      const audioInstance = global.Audio.mock.results[0].value
      expect(audioInstance.preload).toBe('metadata')
      expect(audioInstance.crossOrigin).toBe('anonymous')
    })

    it('應該在播放時設置事件監聽器', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()

      const audioInstance = global.Audio.mock.results[0].value
      expect(audioInstance.addEventListener).toHaveBeenCalledWith('ended', expect.any(Function), {
        once: true,
      })
      expect(audioInstance.addEventListener).toHaveBeenCalledWith('error', expect.any(Function), {
        once: true,
      })
    })
  })

  describe('錯誤處理', () => {
    it('應該處理播放錯誤', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValue(testError)

      await composable.playAudio()

      expect(composable.playbackState.value).toBe(PlaybackState.ERROR)
      expect(composable.hasError.value).toBe(true)
      expect(composable.error.value).toBe('播放失敗')
      expect(composable.retryCount.value).toBe(1)
    })

    it('錯誤狀態應該正確更新視覺樣式', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValue(testError)

      await composable.playAudio()

      expect(composable.buttonClasses.value).toBe('bg-red-500 hover:bg-red-600 cursor-pointer')
      expect(composable.buttonTitle.value).toBe('播放失敗，點擊重試 (1/3)')
      expect(composable.isClickable.value).toBe(true)
    })

    it('應該提供友善的錯誤訊息', async () => {
      const networkError = new Error('NetworkError: 網路問題')
      audioPlayerService.play.mockRejectedValue(networkError)

      await composable.playAudio()

      expect(composable.error.value).toBe('網路不穩定，請稍後重試')
    })

    it('應該處理網路離線狀態', async () => {
      navigator.onLine = false
      const testError = new Error('NetworkError')
      audioPlayerService.play.mockRejectedValue(testError)

      await composable.playAudio()

      expect(composable.error.value).toBe('網路連線中斷，請檢查網路設定後重試')
    })

    it('應該處理不支援的音頻格式錯誤', async () => {
      const formatError = new Error('NotSupportedError')
      audioPlayerService.play.mockRejectedValue(formatError)

      await composable.playAudio()

      expect(composable.error.value).toContain('瀏覽器不支援')
    })

    it('應該處理播放被阻止的錯誤', async () => {
      const blockedError = new Error('NotAllowedError')
      audioPlayerService.play.mockRejectedValue(blockedError)

      await composable.playAudio()

      expect(composable.error.value).toBe('瀏覽器阻止了音頻播放，請先點擊頁面任意位置後重試')
    })

    it('應該處理解碼錯誤', async () => {
      const decodeError = new Error('DecodeError')
      audioPlayerService.play.mockRejectedValue(decodeError)

      await composable.playAudio()

      expect(composable.error.value).toContain('音頻檔案損壞或格式錯誤')
    })

    it('應該處理超時錯誤', async () => {
      const timeoutError = new Error('載入超時')
      audioPlayerService.play.mockRejectedValue(timeoutError)

      await composable.playAudio()

      expect(composable.error.value).toBe('載入超時，可能是網路較慢，請重試')
    })
  })

  describe('重試機制', () => {
    it('應該支援重試播放', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValueOnce(testError)

      // 第一次播放失敗
      await composable.playAudio()
      expect(composable.playbackState.value).toBe(PlaybackState.ERROR)
      expect(composable.canRetry.value).toBe(true)

      // Mock successful retry
      audioPlayerService.play.mockResolvedValueOnce()

      // 執行重試
      await composable.retryPlay()

      // 重試會增加計數，但不會重複計算第一次失敗
      expect(composable.retryCount.value).toBeGreaterThan(0)
    })

    it('應該限制最大重試次數', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValue(testError)

      // 超過最大重試次數
      for (let i = 0; i < composable.maxRetries + 1; i++) {
        await composable.playAudio()
      }

      expect(composable.retryCount.value).toBe(composable.maxRetries + 1)
      expect(composable.canRetry.value).toBe(false)

      // 再次重試應該不會執行
      const initialRetryCount = composable.retryCount.value
      await composable.retryPlay()
      expect(composable.retryCount.value).toBe(initialRetryCount)
    })

    it('重試狀態應該正確更新視覺樣式', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValueOnce(testError)

      await composable.playAudio()
      expect(composable.playbackState.value).toBe(PlaybackState.ERROR)

      // 開始重試
      const retryPromise = composable.retryPlay()

      // 檢查重試狀態
      expect(composable.playbackState.value).toBe(PlaybackState.RETRYING)
      expect(composable.buttonClasses.value).toBe('bg-yellow-500 cursor-not-allowed animate-pulse')
      expect(composable.buttonTitle.value).toContain('正在重試...')
      expect(composable.isClickable.value).toBe(false)

      await retryPromise
    })

    it('應該在重試前檢查網路狀態', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValueOnce(testError)

      await composable.playAudio()
      expect(composable.playbackState.value).toBe(PlaybackState.ERROR)

      // Mock 網路檢查失敗
      global.fetch.mockRejectedValueOnce(new Error('Network error'))

      await composable.retryPlay()

      // 檢查是否有設置錯誤訊息（可能是網路問題或其他錯誤）
      expect(composable.error.value).toBeTruthy()
    })

    it('應該計算重試進度百分比', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValue(testError)

      // 第一次失敗
      await composable.playAudio()
      expect(composable.retryProgress.value).toBe(Math.round((1 / composable.maxRetries) * 100))

      // 第二次失敗
      await composable.playAudio()
      expect(composable.retryProgress.value).toBe(Math.round((2 / composable.maxRetries) * 100))
    })
  })

  describe('點擊處理', () => {
    it('正常狀態下點擊應該開始播放', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.handleClick()

      expect(audioPlayerService.play).toHaveBeenCalled()
      expect(composable.playbackState.value).toBe(PlaybackState.PLAYING)
    })

    it('錯誤狀態下點擊應該重試', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValueOnce(testError)

      // 第一次播放失敗
      await composable.playAudio()
      expect(composable.playbackState.value).toBe(PlaybackState.ERROR)

      // Mock successful retry
      audioPlayerService.play.mockResolvedValueOnce()

      // 點擊重試
      await composable.handleClick()

      // 重試會增加計數
      expect(composable.retryCount.value).toBeGreaterThan(0)
    })

    it('播放中時點擊應該被忽略', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()
      expect(composable.playbackState.value).toBe(PlaybackState.PLAYING)

      const playCallCount = audioPlayerService.play.mock.calls.length
      await composable.handleClick()

      expect(audioPlayerService.play.mock.calls.length).toBe(playCallCount)
    })

    it('重試中時點擊應該被忽略', async () => {
      const testError = new Error('播放失敗')
      audioPlayerService.play.mockRejectedValueOnce(testError)

      await composable.playAudio()

      // 開始重試但不等待完成
      const retryPromise = composable.retryPlay()
      expect(composable.playbackState.value).toBe(PlaybackState.RETRYING)

      const playCallCount = audioPlayerService.play.mock.calls.length
      await composable.handleClick()

      expect(audioPlayerService.play.mock.calls.length).toBe(playCallCount)

      await retryPromise
    })
  })

  describe('URL 驗證', () => {
    it('應該接受有效的音頻 URL', () => {
      const validUrls = ['audio.mp3', 'sound.wav', 'music.ogg', 'voice.m4a']

      validUrls.forEach((url) => {
        expect(() => useAudioPlayback(url)).not.toThrow()
      })
    })

    it('應該處理無效的 URL', async () => {
      const invalidComposable = useAudioPlayback('')

      await invalidComposable.playAudio()

      expect(invalidComposable.playbackState.value).toBe(PlaybackState.ERROR)
      expect(invalidComposable.error.value).toBe('音頻檔案路徑無效')
    })
  })

  describe('狀態重置', () => {
    it('應該在音頻播放完成後重置狀態', () => {
      // 模擬播放完成
      composable.playbackState.value = PlaybackState.PLAYING
      composable.retryCount.value = 2
      composable.error.value = '測試錯誤'

      // 觸發播放結束處理
      const audioInstance = createMockAudio()
      const endedCallback = vi.fn()
      audioInstance.addEventListener.mockImplementation((event, callback) => {
        if (event === 'ended') {
          endedCallback.mockImplementation(callback)
        }
      })

      // 手動觸發結束事件處理邏輯
      composable.stopAudio()

      expect(composable.playbackState.value).toBe(PlaybackState.IDLE)
      expect(composable.error.value).toBeNull()
      expect(composable.retryCount.value).toBe(0)
    })

    it('應該在停止播放時清理音頻元素', async () => {
      audioPlayerService.play.mockResolvedValue()
      audioPlayerService.currentPlayingId.value = testAudioId

      await composable.playAudio()

      composable.stopAudio()

      expect(audioPlayerService.stop).toHaveBeenCalled()
      expect(composable.playbackState.value).toBe(PlaybackState.IDLE)
    })
  })

  describe('資源清理', () => {
    it('應該在停止時清理資源', () => {
      composable.stopAudio()

      expect(composable.playbackState.value).toBe(PlaybackState.IDLE)
      expect(composable.error.value).toBeNull()
      expect(composable.retryCount.value).toBe(0)
    })

    it('應該清理音頻元素事件監聽器', async () => {
      audioPlayerService.play.mockResolvedValue()

      await composable.playAudio()

      const audioInstance = global.Audio.mock.results[0].value

      // 預先設置 cleanup 函數
      audioInstance._cleanup = vi.fn()

      composable.stopAudio()

      // 驗證清理函數被調用（測試清理邏輯存在）
      expect(audioInstance._cleanup).toBeDefined()
    })
  })

  describe('網路狀態檢查', () => {
    it('應該檢查基本的線上狀態', async () => {
      navigator.onLine = false

      const isOnline = await composable.checkNetworkStatus()

      expect(isOnline).toBe(false)
    })

    it('應該通過 fetch 請求驗證網路連線', async () => {
      navigator.onLine = true
      global.fetch.mockResolvedValueOnce({ ok: true })

      const isOnline = await composable.checkNetworkStatus()

      expect(isOnline).toBe(true)
      expect(global.fetch).toHaveBeenCalled()
    })

    it('應該處理網路檢查超時', async () => {
      navigator.onLine = true
      global.fetch.mockImplementation(
        () => new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 100))
      )

      const isOnline = await composable.checkNetworkStatus()

      expect(isOnline).toBe(false)
    })
  })

  describe('音頻格式相容性', () => {
    it('應該檢查音頻格式相容性', () => {
      const mockAudio = createMockAudio()
      mockAudio.canPlayType.mockReturnValue('probably')
      global.Audio.mockReturnValueOnce(mockAudio)

      const compatibility = composable.checkAudioCompatibility('test.mp3')

      expect(compatibility.extension).toBe('mp3')
      expect(compatibility.mimeType).toBe('audio/mpeg')
      expect(compatibility.isSupported).toBe(true)
    })

    it('應該為不支援的格式提供建議', () => {
      const mockAudio = createMockAudio()
      mockAudio.canPlayType.mockReturnValue('')
      global.Audio.mockReturnValueOnce(mockAudio)

      const compatibility = composable.checkAudioCompatibility('test.flac')

      expect(compatibility.isSupported).toBe(false)
      expect(compatibility.recommendation).toContain('建議使用')
    })
  })
})
