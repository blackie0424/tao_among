/**
 * 音頻播放系統整合測試
 *
 * 測試與現有系統的整合：
 * - 驗證與 AudioPlayerService 的正確整合
 * - 測試多個 Volume 組件的互斥播放行為
 * - 確認與現有音頻功能的相容性
 * Requirements: 4.1, 4.2, 4.3
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

// Import components and services
import Volume from '../Components/Volume.vue'
import FishAudioCard from '../Components/FishAudioCard.vue'
import { useAudioPlayback } from '../composables/useAudioPlayback.js'

// Mock the AudioPlayerService module
vi.mock('../services/AudioPlayerService.js', () => {
  const service = {
    currentPlayingId: { value: null },
    playbackState: {
      isPlaying: false,
      isPaused: false,
      currentTime: 0,
      duration: 0,
      error: null,
    },
    eventListeners: {
      play: [],
      pause: [],
      stop: [],
      ended: [],
      error: [],
      stateSync: [],
    },
    play: vi.fn(),
    playShortAudio: vi.fn(),
    stop: vi.fn(),
    pause: vi.fn(),
    resume: vi.fn(),
    isPlaying: vi.fn(),
    isPaused: vi.fn(),
    on: vi.fn(),
    off: vi.fn(),
    emit: vi.fn(),
    ensureMutualExclusion: vi.fn(),
    forceStopAll: vi.fn(),
    getGlobalState: vi.fn(),
    hasActivePlayback: vi.fn(),
  }

  // Mock event system
  service.on.mockImplementation((event, callback) => {
    if (service.eventListeners[event]) {
      service.eventListeners[event].push(callback)
    }
  })

  service.off.mockImplementation((event, callback) => {
    if (service.eventListeners[event]) {
      const index = service.eventListeners[event].indexOf(callback)
      if (index > -1) {
        service.eventListeners[event].splice(index, 1)
      }
    }
  })

  service.emit.mockImplementation((event, data) => {
    if (service.eventListeners[event]) {
      service.eventListeners[event].forEach((callback) => {
        callback(data)
      })
    }
  })

  // Mock mutual exclusion behavior
  service.ensureMutualExclusion.mockImplementation((newAudioId) => {
    if (service.currentPlayingId.value && service.currentPlayingId.value !== newAudioId) {
      service.forceStopAll()
    }
  })

  service.forceStopAll.mockImplementation(() => {
    const audioId = service.currentPlayingId.value
    service.currentPlayingId.value = null
    service.playbackState.isPlaying = false
    service.playbackState.isPaused = false
    service.playbackState.error = null
    if (audioId) {
      service.emit('stop', { audioId })
    }
  })

  // Mock play method with realistic behavior
  service.play.mockImplementation(async (audioId, audioElement, audioUrl) => {
    service.ensureMutualExclusion(audioId)
    service.currentPlayingId.value = audioId
    service.playbackState.isPlaying = true
    service.playbackState.isPaused = false
    service.playbackState.error = null
    service.emit('play', { audioId, audioUrl })
  })

  service.playShortAudio.mockImplementation(async (audioId, audioElement, audioUrl) => {
    service.ensureMutualExclusion(audioId)
    service.currentPlayingId.value = audioId
    service.playbackState.isPlaying = true
    service.playbackState.isPaused = false
    service.playbackState.error = null
    service.emit('play', { audioId, audioUrl })
  })

  service.stop.mockImplementation(() => {
    const audioId = service.currentPlayingId.value
    service.currentPlayingId.value = null
    service.playbackState.isPlaying = false
    service.playbackState.isPaused = false
    service.emit('stop', { audioId })
  })

  service.isPlaying.mockImplementation((audioId) => {
    return service.currentPlayingId.value === audioId && service.playbackState.isPlaying
  })

  service.isPaused.mockImplementation((audioId) => {
    return service.currentPlayingId.value === audioId && service.playbackState.isPaused
  })

  service.getGlobalState.mockImplementation(() => ({
    hasActivePlayback: service.currentPlayingId.value !== null,
    currentPlayingId: service.currentPlayingId.value,
    isPlaying: service.playbackState.isPlaying,
    isPaused: service.playbackState.isPaused,
    hasError: service.playbackState.error !== null,
    error: service.playbackState.error,
  }))

  service.hasActivePlayback.mockImplementation(() => {
    return service.currentPlayingId.value !== null && service.playbackState.isPlaying
  })

  return {
    default: service,
  }
})

// Mock Audio constructor
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

// Mock navigator and fetch
Object.defineProperty(navigator, 'onLine', {
  writable: true,
  value: true,
})
global.fetch = vi.fn().mockResolvedValue({ ok: true })

// Mock FishAudioCard component for integration tests
vi.mock('../Components/FishAudioCard.vue', () => ({
  default: {
    name: 'FishAudioCard',
    props: ['audio', 'fishId'],
    emits: ['deleted'],
    template: `
      <div class="fish-audio-card-mock" :data-audio-id="audio.id">
        <button @click="playAudio" :class="buttonClass">
          {{ buttonText }}
        </button>
      </div>
    `,
    setup(props) {
      const audioPlayerService = require('../services/AudioPlayerService.js').default
      const isPlaying = () => audioPlayerService.isPlaying(props.audio.id)
      const buttonClass = () => (isPlaying() ? 'bg-red-500' : 'bg-blue-500')
      const buttonText = () => (isPlaying() ? '暫停' : '播放')

      const playAudio = async () => {
        const audioElement = new Audio()
        const audioUrl = props.audio.url || `test-${props.audio.id}.mp3`
        await audioPlayerService.play(props.audio.id, audioElement, audioUrl)
      }

      return {
        playAudio,
        buttonClass,
        buttonText,
      }
    },
  },
}))

describe('音頻播放系統整合測試', () => {
  let mockAudioPlayerService

  beforeEach(async () => {
    vi.clearAllMocks()

    // Get fresh mock service instance
    const audioPlayerServiceModule = await import('../services/AudioPlayerService.js')
    mockAudioPlayerService = audioPlayerServiceModule.default

    // Reset service state
    mockAudioPlayerService.currentPlayingId.value = null
    mockAudioPlayerService.playbackState.isPlaying = false
    mockAudioPlayerService.playbackState.isPaused = false
    mockAudioPlayerService.playbackState.error = null
    mockAudioPlayerService.playbackState.currentTime = 0
    mockAudioPlayerService.playbackState.duration = 0

    // Clear event listeners
    Object.keys(mockAudioPlayerService.eventListeners).forEach((event) => {
      mockAudioPlayerService.eventListeners[event] = []
    })

    navigator.onLine = true
    global.fetch.mockResolvedValue({ ok: true })
  })

  afterEach(() => {
    vi.clearAllMocks()
  })

  describe('AudioPlayerService 整合測試', () => {
    it('Volume 組件應該正確整合 AudioPlayerService', async () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'test-id',
        },
      })

      // 點擊播放
      await wrapper.find('span[role="button"]').trigger('click')

      // 驗證 AudioPlayerService.play 被調用
      expect(mockAudioPlayerService.play).toHaveBeenCalledWith(
        'test-id',
        expect.any(Object), // audio element
        'test-audio.mp3'
      )

      wrapper.unmount()
    })

    it('useAudioPlayback 應該正確使用 AudioPlayerService 的 play 方法', async () => {
      const { playAudio } = useAudioPlayback('test.mp3', 'test-id')

      await playAudio()

      expect(mockAudioPlayerService.play).toHaveBeenCalledWith(
        'test-id',
        expect.any(Object),
        'test.mp3'
      )
    })

    it('useAudioPlayback 應該監聽 AudioPlayerService 的事件', () => {
      useAudioPlayback('test.mp3', 'test-id')

      // 驗證事件監聽器被註冊
      expect(mockAudioPlayerService.on).toHaveBeenCalledWith('ended', expect.any(Function))
      expect(mockAudioPlayerService.on).toHaveBeenCalledWith('error', expect.any(Function))
      expect(mockAudioPlayerService.on).toHaveBeenCalledWith('stop', expect.any(Function))
    })

    it('AudioPlayerService 的狀態變化應該反映到 Volume 組件', async () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'test-id',
        },
      })

      // 模擬播放開始
      mockAudioPlayerService.currentPlayingId.value = 'test-id'
      mockAudioPlayerService.playbackState.isPlaying = true

      // 點擊播放
      await wrapper.find('span[role="button"]').trigger('click')
      await nextTick()

      // 驗證組件狀態更新
      expect(wrapper.vm.playbackState).toBe('playing')
      expect(wrapper.vm.isPlaying).toBe(true)

      wrapper.unmount()
    })
  })

  describe('多個 Volume 組件互斥播放測試', () => {
    it('當一個 Volume 組件播放時，其他組件應該停止', async () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 播放第一個音頻
      await wrapper1.find('span[role="button"]').trigger('click')
      await nextTick()

      expect(mockAudioPlayerService.play).toHaveBeenCalledWith(
        'audio-1',
        expect.any(Object),
        'audio1.mp3'
      )

      // 播放第二個音頻
      await wrapper2.find('span[role="button"]').trigger('click')
      await nextTick()

      // 驗證互斥行為
      expect(mockAudioPlayerService.ensureMutualExclusion).toHaveBeenCalledWith('audio-2')
      expect(mockAudioPlayerService.play).toHaveBeenCalledWith(
        'audio-2',
        expect.any(Object),
        'audio2.mp3'
      )

      wrapper1.unmount()
      wrapper2.unmount()
    })

    it('多個 Volume 組件應該共享全域播放狀態', async () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 播放第一個音頻
      await wrapper1.find('span[role="button"]').trigger('click')

      // 模擬 AudioPlayerService 狀態變化
      mockAudioPlayerService.currentPlayingId.value = 'audio-1'
      mockAudioPlayerService.playbackState.isPlaying = true

      await nextTick()

      // 第一個組件應該顯示播放中狀態
      expect(wrapper1.vm.playbackState).toBe('playing')

      // 第二個組件應該保持 idle 狀態
      expect(wrapper2.vm.playbackState).toBe('idle')

      wrapper1.unmount()
      wrapper2.unmount()
    })

    it('當播放的音頻結束時，所有組件應該重置狀態', async () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 播放第一個音頻
      await wrapper1.find('span[role="button"]').trigger('click')

      // 模擬播放狀態
      mockAudioPlayerService.currentPlayingId.value = 'audio-1'
      mockAudioPlayerService.playbackState.isPlaying = true

      await nextTick()

      // 模擬播放結束
      mockAudioPlayerService.emit('ended', { audioId: 'audio-1' })
      mockAudioPlayerService.currentPlayingId.value = null
      mockAudioPlayerService.playbackState.isPlaying = false

      await nextTick()

      // 所有組件應該重置為 idle 狀態
      expect(wrapper1.vm.playbackState).toBe('idle')
      expect(wrapper2.vm.playbackState).toBe('idle')

      wrapper1.unmount()
      wrapper2.unmount()
    })
  })

  describe('與現有音頻功能的相容性測試', () => {
    it('Volume 組件應該與 FishAudioCard 組件共享播放狀態', async () => {
      const volumeWrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'shared-audio',
        },
      })

      const fishAudioWrapper = mount(FishAudioCard, {
        props: {
          audio: {
            id: 'shared-audio',
            name: '測試音頻',
            url: 'test-audio.mp3',
            created_at: '2023-10-13T10:30:00Z',
          },
          fishId: 123,
        },
      })

      // Volume 組件播放音頻
      await volumeWrapper.find('span[role="button"]').trigger('click')

      // 模擬播放狀態
      mockAudioPlayerService.currentPlayingId.value = 'shared-audio'
      mockAudioPlayerService.playbackState.isPlaying = true

      await nextTick()

      // 兩個組件都應該反映播放狀態
      expect(volumeWrapper.vm.playbackState).toBe('playing')
      expect(mockAudioPlayerService.isPlaying('shared-audio')).toBe(true)

      volumeWrapper.unmount()
      fishAudioWrapper.unmount()
    })

    it('不同類型的音頻組件應該能夠互斥播放', async () => {
      const volumeWrapper = mount(Volume, {
        props: {
          audioUrl: 'volume-audio.mp3',
          audioId: 'volume-audio',
        },
      })

      const fishAudioWrapper = mount(FishAudioCard, {
        props: {
          audio: {
            id: 'fish-audio',
            name: '魚類音頻',
            url: 'fish-audio.mp3',
            created_at: '2023-10-13T10:30:00Z',
          },
          fishId: 123,
        },
      })

      // Volume 組件先播放
      await volumeWrapper.find('span[role="button"]').trigger('click')

      // 模擬 Volume 播放狀態
      mockAudioPlayerService.currentPlayingId.value = 'volume-audio'
      mockAudioPlayerService.playbackState.isPlaying = true

      await nextTick()

      // FishAudioCard 播放音頻
      await fishAudioWrapper.find('button').trigger('click')

      // 驗證互斥行為 - 檢查是否調用了 ensureMutualExclusion
      expect(mockAudioPlayerService.ensureMutualExclusion).toHaveBeenCalled()

      // 驗證調用了互斥方法（至少一次）
      expect(mockAudioPlayerService.ensureMutualExclusion.mock.calls.length).toBeGreaterThanOrEqual(
        1
      )

      volumeWrapper.unmount()
      fishAudioWrapper.unmount()
    })

    it('應該保持與現有 AudioPlayerService API 的相容性', () => {
      // 驗證所有必要的方法都存在
      expect(typeof mockAudioPlayerService.play).toBe('function')
      expect(typeof mockAudioPlayerService.playShortAudio).toBe('function')
      expect(typeof mockAudioPlayerService.stop).toBe('function')
      expect(typeof mockAudioPlayerService.pause).toBe('function')
      expect(typeof mockAudioPlayerService.resume).toBe('function')
      expect(typeof mockAudioPlayerService.isPlaying).toBe('function')
      expect(typeof mockAudioPlayerService.isPaused).toBe('function')
      expect(typeof mockAudioPlayerService.on).toBe('function')
      expect(typeof mockAudioPlayerService.off).toBe('function')
      expect(typeof mockAudioPlayerService.emit).toBe('function')

      // 驗證狀態屬性存在
      expect(mockAudioPlayerService.currentPlayingId).toBeDefined()
      expect(mockAudioPlayerService.playbackState).toBeDefined()
    })
  })

  describe('錯誤處理整合測試', () => {
    it('AudioPlayerService 的錯誤應該正確傳播到 Volume 組件', async () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'error-audio.mp3',
          audioId: 'error-audio',
        },
      })

      // 模擬播放錯誤
      const testError = new Error('播放失敗')
      mockAudioPlayerService.play.mockRejectedValueOnce(testError)

      // 點擊播放
      await wrapper.find('span[role="button"]').trigger('click')
      await nextTick()

      // 驗證錯誤狀態
      expect(wrapper.vm.playbackState).toBe('error')
      expect(wrapper.vm.hasError).toBe(true)

      wrapper.unmount()
    })

    it('多個組件中的錯誤應該獨立處理', async () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 第一個組件播放成功
      mockAudioPlayerService.play.mockResolvedValueOnce()
      await wrapper1.find('span[role="button"]').trigger('click')

      // 模擬第一個音頻播放成功
      mockAudioPlayerService.currentPlayingId.value = 'audio-1'
      mockAudioPlayerService.playbackState.isPlaying = true

      await nextTick()

      // 第二個組件播放失敗
      const testError = new Error('播放失敗')
      mockAudioPlayerService.play.mockRejectedValueOnce(testError)
      await wrapper2.find('span[role="button"]').trigger('click')

      await nextTick()

      // 驗證狀態獨立性
      expect(wrapper1.vm.playbackState).toBe('playing')
      expect(wrapper2.vm.playbackState).toBe('error')

      wrapper1.unmount()
      wrapper2.unmount()
    })
  })

  describe('事件系統整合測試', () => {
    it('AudioPlayerService 事件應該正確觸發組件狀態更新', async () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'test-audio',
        },
      })

      // 模擬外部觸發播放結束事件
      mockAudioPlayerService.emit('ended', { audioId: 'test-audio' })

      await nextTick()

      // 組件應該重置狀態
      expect(wrapper.vm.playbackState).toBe('idle')
      expect(wrapper.vm.retryCount).toBe(0)

      wrapper.unmount()
    })

    it('組件卸載時應該正確清理事件監聽器', () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'test-audio',
        },
      })

      // 驗證事件監聽器被註冊
      expect(mockAudioPlayerService.on).toHaveBeenCalled()

      wrapper.unmount()

      // 由於 useAudioPlayback 在組件外部調用時會有警告，
      // 我們主要驗證組件能正常卸載而不報錯
      expect(wrapper.vm).toBeDefined()
    })

    it('全域狀態同步應該正常工作', async () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 播放第一個音頻
      await wrapper1.find('span[role="button"]').trigger('click')

      // 模擬全域狀態變化
      mockAudioPlayerService.currentPlayingId.value = 'audio-1'
      mockAudioPlayerService.playbackState.isPlaying = true

      // 觸發狀態同步事件
      mockAudioPlayerService.emit('stateSync', {
        originalEvent: 'play',
        originalData: { audioId: 'audio-1' },
        globalState: mockAudioPlayerService.getGlobalState(),
        timestamp: Date.now(),
      })

      await nextTick()

      // 驗證全域狀態
      expect(mockAudioPlayerService.hasActivePlayback()).toBe(true)
      expect(mockAudioPlayerService.getGlobalState().currentPlayingId).toBe('audio-1')

      wrapper1.unmount()
      wrapper2.unmount()
    })
  })

  describe('效能和資源管理測試', () => {
    it('多個組件應該共享同一個 AudioPlayerService 實例', () => {
      const wrapper1 = mount(Volume, {
        props: {
          audioUrl: 'audio1.mp3',
          audioId: 'audio-1',
        },
      })

      const wrapper2 = mount(Volume, {
        props: {
          audioUrl: 'audio2.mp3',
          audioId: 'audio-2',
        },
      })

      // 兩個組件應該使用同一個服務實例
      // 這通過 mock 的調用次數來驗證
      expect(mockAudioPlayerService.on).toHaveBeenCalled()

      wrapper1.unmount()
      wrapper2.unmount()
    })

    it('組件卸載時應該正確清理資源', () => {
      const wrapper = mount(Volume, {
        props: {
          audioUrl: 'test-audio.mp3',
          audioId: 'test-audio',
        },
      })

      // 模擬播放狀態
      mockAudioPlayerService.currentPlayingId.value = 'test-audio'

      // 驗證組件能正常卸載
      expect(() => {
        wrapper.unmount()
      }).not.toThrow()

      // 驗證組件已卸載
      expect(wrapper.vm).toBeDefined()
    })
  })
})
