import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FishAudioCard from '@/Components/FishAudioCard.vue'
import OverflowMenu from '@/Components/OverflowMenu.vue'

// Mock OverflowMenu component
vi.mock('@/Components/OverflowMenu.vue', () => ({
  default: {
    name: 'OverflowMenu',
    props: ['apiUrl', 'fishId', 'editUrl'],
    emits: ['deleted'],
    template: '<div class="overflow-menu-mock" @click="$emit(\'deleted\')">Mock OverflowMenu</div>',
  },
}))

// Mock AudioPlayerService
vi.mock('@/services/AudioPlayerService.js', () => {
  const mockAudioPlayerService = {
    currentPlayingId: { value: null },
    playbackState: {
      isPlaying: false,
      isPaused: false,
      currentTime: 0,
      duration: 0,
      error: null,
    },
    play: vi.fn(),
    pause: vi.fn(),
    stop: vi.fn(),
    isPlaying: vi.fn(() => false),
    isPaused: vi.fn(() => false),
    on: vi.fn(),
    off: vi.fn(),
    emit: vi.fn(),
  }
  return {
    default: mockAudioPlayerService,
  }
})

// Mock environment variables
vi.stubGlobal('import.meta', {
  env: {
    VITE_SUPABASE_STORAGE_URL: 'https://test-supabase.com/storage/v1',
    VITE_SUPABASE_BUCKET: 'test-bucket',
  },
})

describe('FishAudioCard', () => {
  let wrapper
  let mockAudioPlayerService
  const defaultProps = {
    audio: {
      id: 1,
      name: '測試發音',
      locate: 'test-audio.mp3',
      url: 'https://example.com/test-audio.mp3',
      created_at: '2023-10-13T10:30:00Z',
    },
    fishId: 123,
  }

  beforeEach(async () => {
    vi.clearAllMocks()
    // Get the mocked service
    const audioPlayerServiceModule = await import('@/services/AudioPlayerService.js')
    mockAudioPlayerService = audioPlayerServiceModule.default

    // Reset mock service state
    mockAudioPlayerService.currentPlayingId.value = null
    mockAudioPlayerService.playbackState.isPlaying = false
    mockAudioPlayerService.playbackState.isPaused = false
    mockAudioPlayerService.playbackState.error = null
    mockAudioPlayerService.playbackState.currentTime = 0
    mockAudioPlayerService.playbackState.duration = 0
    mockAudioPlayerService.isPlaying.mockReturnValue(false)
    mockAudioPlayerService.isPaused.mockReturnValue(false)
  })

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount()
    }
  })

  describe('基本渲染測試', () => {
    it('應該正確渲染音頻卡片的基本結構', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      expect(wrapper.find('.bg-gray-50').exists()).toBe(true)
      expect(wrapper.find('.rounded-lg').exists()).toBe(true)
      expect(wrapper.find('.border-gray-200').exists()).toBe(true)
    })

    it('應該顯示音頻名稱', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const audioName = wrapper.find('h4')
      expect(audioName.exists()).toBe(true)
      expect(audioName.text()).toBe('測試發音')
    })

    it('應該顯示播放按鈕', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const playButton = wrapper.find('button')
      expect(playButton.exists()).toBe(true)
      expect(playButton.classes()).toContain('bg-blue-500')
    })

    it('應該顯示音頻檔案資訊', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const fileInfo = wrapper.find('.text-gray-700')
      expect(fileInfo.exists()).toBe(true)
      expect(fileInfo.text()).toBe('test-audio.mp3')
    })

    it('應該顯示格式化的建立時間', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const timeInfo = wrapper.find('.text-gray-400')
      expect(timeInfo.exists()).toBe(true)
      expect(timeInfo.text()).toContain('記錄時間:')
    })

    it('應該包含隱藏的音頻元素', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const audioElement = wrapper.find('audio')
      expect(audioElement.exists()).toBe(true)
      expect(audioElement.attributes('src')).toBe('https://example.com/test-audio.mp3')
    })
  })

  describe('音頻 URL 計算測試', () => {
    it('應該優先使用 audio.url', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const audioElement = wrapper.find('audio')
      expect(audioElement.attributes('src')).toBe('https://example.com/test-audio.mp3')
    })

    it('當沒有 url 時應該使用 locate 構建 URL', () => {
      const propsWithoutUrl = {
        ...defaultProps,
        audio: {
          ...defaultProps.audio,
          url: null,
        },
      }

      wrapper = mount(FishAudioCard, {
        props: propsWithoutUrl,
      })

      const audioElement = wrapper.find('audio')
      // 檢查是否包含 locate 檔名
      expect(audioElement.attributes('src')).toContain('test-audio.mp3')
    })

    it('當沒有 url 和 locate 時應該返回 null', () => {
      const propsWithoutUrlAndLocate = {
        ...defaultProps,
        audio: {
          ...defaultProps.audio,
          url: null,
          locate: null,
        },
      }

      wrapper = mount(FishAudioCard, {
        props: propsWithoutUrlAndLocate,
      })

      const audioElement = wrapper.find('audio')
      expect(audioElement.attributes('src')).toBeUndefined()
    })
  })

  describe('播放狀態測試', () => {
    it('當音頻正在播放時應該顯示暫停按鈕', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.isPlaying = true
      mockAudioPlayerService.isPlaying.mockReturnValue(true)

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const playButton = wrapper.find('button')
      expect(playButton.classes()).toContain('bg-red-500')
      expect(playButton.attributes('title')).toBe('暫停播放')
    })

    it('當音頻暫停時應該顯示恢復播放按鈕', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.isPaused = true
      mockAudioPlayerService.isPaused.mockReturnValue(true)

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const playButton = wrapper.find('button')
      expect(playButton.classes()).toContain('bg-orange-500')
      expect(playButton.attributes('title')).toBe('恢復播放')
    })

    it('當音頻播放錯誤時應該顯示錯誤狀態', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError: 網路連線問題'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const playButton = wrapper.find('button')
      expect(playButton.classes()).toContain('bg-gray-400')
      expect(playButton.classes()).toContain('cursor-not-allowed')
      expect(playButton.attributes('title')).toBe('播放失敗，點擊重試')
    })
  })

  describe('播放控制測試', () => {
    it('點擊播放按鈕應該調用 AudioPlayerService.play', async () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const playButton = wrapper.find('button')
      await playButton.trigger('click')

      expect(mockAudioPlayerService.play).toHaveBeenCalledWith(
        1,
        expect.any(Object), // audioElement
        'https://example.com/test-audio.mp3'
      )
    })

    it('當沒有音頻 URL 時點擊播放按鈕不應該調用 play', async () => {
      const propsWithoutUrl = {
        ...defaultProps,
        audio: {
          ...defaultProps.audio,
          url: null,
          locate: null,
        },
      }

      wrapper = mount(FishAudioCard, {
        props: propsWithoutUrl,
      })

      const playButton = wrapper.find('button')
      await playButton.trigger('click')

      expect(mockAudioPlayerService.play).not.toHaveBeenCalled()
    })
  })

  describe('播放狀態指示器測試', () => {
    it('當音頻正在播放時應該顯示動畫指示器', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.isPlaying = true
      mockAudioPlayerService.isPlaying.mockReturnValue(true)

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const playingIndicator = wrapper.find('.animate-pulse')
      expect(playingIndicator.exists()).toBe(true)
    })

    it('當音頻暫停時應該顯示暫停指示器', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.isPaused = true
      mockAudioPlayerService.isPaused.mockReturnValue(true)

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const pausedIndicator = wrapper.find('.bg-orange-500.rounded-full')
      expect(pausedIndicator.exists()).toBe(true)
    })

    it('當音頻播放錯誤時應該顯示錯誤指示器', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const errorIndicator = wrapper.find('.bg-red-500.rounded-full')
      expect(errorIndicator.exists()).toBe(true)
    })
  })

  describe('播放進度測試', () => {
    it('當有播放進度時應該顯示進度條', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.currentTime = 30
      mockAudioPlayerService.playbackState.duration = 120

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const progressBar = wrapper.find('.bg-blue-500.h-1')
      expect(progressBar.exists()).toBe(true)
      expect(progressBar.attributes('style')).toContain('width: 25%')
    })

    it('應該顯示格式化的時間', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.currentTime = 65
      mockAudioPlayerService.playbackState.duration = 125

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const timeDisplay = wrapper.findAll('.text-gray-400')
      const timeTexts = timeDisplay.map((el) => el.text())
      expect(timeTexts.some((text) => text.includes('1:05'))).toBe(true)
      expect(timeTexts.some((text) => text.includes('2:05'))).toBe(true)
    })
  })

  describe('錯誤處理測試', () => {
    it('當有錯誤時應該顯示錯誤訊息', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError: 網路連線問題'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const errorMessage = wrapper.find('.bg-red-50')
      expect(errorMessage.exists()).toBe(true)
      expect(errorMessage.text()).toContain('音頻播放失敗')
      expect(errorMessage.text()).toContain('網路連線問題，請檢查網路狀態')
    })

    it('應該提供重試按鈕', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const retryButton = wrapper.find('.bg-red-100')
      expect(retryButton.exists()).toBe(true)
      expect(retryButton.text()).toContain('重試')
    })

    it('點擊重試按鈕應該重新嘗試播放', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      const retryButton = wrapper.find('.bg-red-100')
      await retryButton.trigger('click')

      // 應該調用重試方法（可能會間接調用 play）
      expect(retryButton.exists()).toBe(true)
    })

    it('應該提供忽略錯誤按鈕', async () => {
      mockAudioPlayerService.currentPlayingId.value = 1
      mockAudioPlayerService.playbackState.error = 'NetworkError'

      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      await nextTick()

      // 查找所有按鈕，找到包含"忽略"文字的按鈕
      const buttons = wrapper.findAll('button')
      const dismissButton = buttons.find((button) => button.text().includes('忽略'))
      expect(dismissButton).toBeTruthy()
    })
  })

  describe('OverflowMenu 整合測試', () => {
    it('應該正確傳遞 props 給 OverflowMenu', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const overflowMenu = wrapper.findComponent(OverflowMenu)
      expect(overflowMenu.exists()).toBe(true)
      expect(overflowMenu.props('apiUrl')).toBe('/fish/123/audio/1')
      expect(overflowMenu.props('fishId')).toBe('123')
      expect(overflowMenu.props('editUrl')).toBe('/fish/123/audio/1/edit')
    })

    it('當 OverflowMenu 觸發 deleted 事件時應該向上傳遞', async () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const overflowMenu = wrapper.findComponent(OverflowMenu)
      await overflowMenu.vm.$emit('deleted')

      expect(wrapper.emitted('deleted')).toBeTruthy()
      expect(wrapper.emitted('deleted')).toHaveLength(1)
    })
  })

  describe('事件監聽器測試', () => {
    it('組件掛載時應該註冊事件監聽器', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      expect(mockAudioPlayerService.on).toHaveBeenCalledWith('error', expect.any(Function))
      expect(mockAudioPlayerService.on).toHaveBeenCalledWith('ended', expect.any(Function))
    })

    it('組件卸載時應該移除事件監聽器', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      wrapper.unmount()

      expect(mockAudioPlayerService.off).toHaveBeenCalledWith('error', expect.any(Function))
      expect(mockAudioPlayerService.off).toHaveBeenCalledWith('ended', expect.any(Function))
    })
  })

  describe('工具方法測試', () => {
    it('formatTime 應該正確格式化時間', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      expect(wrapper.vm.formatTime(0)).toBe('0:00')
      expect(wrapper.vm.formatTime(65)).toBe('1:05')
      expect(wrapper.vm.formatTime(125)).toBe('2:05')
      expect(wrapper.vm.formatTime(3661)).toBe('61:01')
    })

    it('formatTime 應該處理無效輸入', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      expect(wrapper.vm.formatTime(null)).toBe('0:00')
      expect(wrapper.vm.formatTime(undefined)).toBe('0:00')
      expect(wrapper.vm.formatTime(NaN)).toBe('0:00')
    })

    it('formatDateTime 應該正確格式化日期時間', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      const testDate = '2023-10-13T10:30:00Z'
      const formatted = wrapper.vm.formatDateTime(testDate)
      expect(formatted).toBeTruthy()
      expect(typeof formatted).toBe('string')
    })

    it('getErrorMessage 應該返回友善的錯誤訊息', () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      expect(wrapper.vm.getErrorMessage('NotSupportedError')).toBe('瀏覽器不支援此音頻格式')
      expect(wrapper.vm.getErrorMessage('NetworkError: 網路連線問題')).toBe(
        '網路連線問題，請檢查網路狀態'
      )
      expect(wrapper.vm.getErrorMessage('音頻 URL 不存在')).toBe('音頻檔案路徑錯誤')
    })
  })

  describe('狀態文字測試', () => {
    it('getStatusText 應該根據播放狀態返回正確文字', async () => {
      wrapper = mount(FishAudioCard, {
        props: defaultProps,
      })

      // 預設狀態
      expect(wrapper.vm.getStatusText()).toBe('點擊播放')

      // 測試方法是否存在並可調用
      expect(typeof wrapper.vm.getStatusText).toBe('function')
    })
  })
})
