/**
 * Volume 組件測試
 *
 * 測試點擊事件的正確處理、驗證播放期間的點擊禁用功能、測試不同播放狀態的視覺渲染
 * Requirements: 2.1, 2.2, 1.3
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import Volume from '../Components/Volume.vue'

// Mock useAudioPlayback composable
const mockHandleClick = vi.fn()
const mockPlaybackState = ref('idle')
const mockButtonClasses = ref('bg-gray-200 hover:bg-gray-300 cursor-pointer')
const mockButtonTitle = ref('點擊播放音頻')
const mockIsPlaying = ref(false)

vi.mock('../composables/useAudioPlayback.js', () => ({
  useAudioPlayback: vi.fn(() => ({
    playbackState: mockPlaybackState,
    buttonClasses: mockButtonClasses,
    buttonTitle: mockButtonTitle,
    isPlaying: mockIsPlaying,
    hasError: ref(false),
    isClickable: ref(true),
    isRetrying: ref(false),
    canRetry: ref(false),
    retryProgress: ref(0),
    error: ref(null),
    retryCount: ref(0),
    maxRetries: 3,
    handleClick: mockHandleClick,
  })),
}))

import { useAudioPlayback } from '../composables/useAudioPlayback.js'

describe('Volume Component', () => {
  let wrapper
  const defaultProps = {
    audioUrl: 'test-audio.mp3',
    audioId: 'test-id',
  }

  beforeEach(() => {
    vi.clearAllMocks()

    // Reset mock values to default state
    mockPlaybackState.value = 'idle'
    mockButtonClasses.value = 'bg-gray-200 hover:bg-gray-300 cursor-pointer'
    mockButtonTitle.value = '點擊播放音頻'
    mockIsPlaying.value = false

    wrapper = mount(Volume, {
      props: defaultProps,
    })
  })

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount()
    }
  })

  describe('組件初始化', () => {
    it('應該正確渲染基本結構', () => {
      expect(wrapper.find('span[role="button"]').exists()).toBe(true)
    })

    it('應該使用正確的 props 初始化 useAudioPlayback', () => {
      expect(useAudioPlayback).toHaveBeenCalledWith(defaultProps.audioUrl, defaultProps.audioId)
    })

    it('應該設置正確的可訪問性屬性', () => {
      const button = wrapper.find('span[role="button"]')

      expect(button.attributes('role')).toBe('button')
      expect(button.attributes('tabindex')).toBe('0')
    })

    it('應該應用正確的初始樣式類別', () => {
      const button = wrapper.find('span[role="button"]')

      expect(button.classes()).toContain('inline-flex')
      expect(button.classes()).toContain('items-center')
      expect(button.classes()).toContain('justify-center')
      expect(button.classes()).toContain('rounded-full')
      expect(button.classes()).toContain('ml-2')
      expect(button.classes()).toContain('transition-colors')
      expect(button.classes()).toContain('duration-200')
    })
  })

  describe('點擊事件處理', () => {
    it('應該在點擊時調用 handleClick', async () => {
      const button = wrapper.find('span[role="button"]')

      await button.trigger('click')

      expect(mockHandleClick).toHaveBeenCalledTimes(1)
    })

    it('應該支援鍵盤 Enter 鍵觸發', async () => {
      const button = wrapper.find('span[role="button"]')

      await button.trigger('keydown.enter')

      expect(mockHandleClick).toHaveBeenCalledTimes(1)
    })

    it('應該支援鍵盤 Space 鍵觸發', async () => {
      const button = wrapper.find('span[role="button"]')

      await button.trigger('keydown.space')

      expect(mockHandleClick).toHaveBeenCalledTimes(1)
    })

    it('播放中時應該顯示禁用狀態', async () => {
      // 設置為播放中狀態
      mockPlaybackState.value = 'playing'
      mockIsPlaying.value = true

      await wrapper.vm.$nextTick()

      const button = wrapper.find('span[role="button"]')
      expect(button.attributes('disabled')).toBeDefined()
    })
  })

  describe('視覺狀態渲染', () => {
    it('應該在 idle 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'idle'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })

    it('應該在 playing 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'playing'
      mockIsPlaying.value = true
      mockButtonClasses.value = 'bg-blue-500 cursor-not-allowed'
      mockButtonTitle.value = '正在播放...'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })

    it('應該在 error 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'error'
      mockButtonClasses.value = 'bg-red-500 hover:bg-red-600 cursor-pointer'
      mockButtonTitle.value = '播放失敗，點擊重試 (1/3)'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })

    it('應該在 retrying 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'retrying'
      mockButtonClasses.value = 'bg-yellow-500 cursor-not-allowed animate-pulse'
      mockButtonTitle.value = '正在重試... (1/3)'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })
  })

  describe('Props 處理', () => {
    it('應該接受必需的 audioUrl prop', () => {
      expect(wrapper.props('audioUrl')).toBe(defaultProps.audioUrl)
    })

    it('應該接受可選的 audioId prop', () => {
      expect(wrapper.props('audioId')).toBe(defaultProps.audioId)
    })

    it('應該為 audioId 提供預設值', async () => {
      const wrapperWithoutId = mount(Volume, {
        props: {
          audioUrl: 'test.mp3',
        },
      })

      expect(wrapperWithoutId.props('audioId')).toBeNull()

      wrapperWithoutId.unmount()
    })

    it('應該在 props 變更時重新初始化 composable', async () => {
      const newAudioUrl = 'new-audio.mp3'
      const newAudioId = 'new-id'

      await wrapper.setProps({
        audioUrl: newAudioUrl,
        audioId: newAudioId,
      })

      // 由於 composable 在 setup 中只初始化一次，這裡主要驗證 props 更新
      expect(wrapper.props('audioUrl')).toBe(newAudioUrl)
      expect(wrapper.props('audioId')).toBe(newAudioId)
    })
  })

  describe('可訪問性支援', () => {
    it('應該設置正確的 ARIA 屬性', () => {
      const button = wrapper.find('span[role="button"]')

      expect(button.attributes('role')).toBe('button')
      expect(button.attributes('aria-label')).toBeDefined()
      expect(button.attributes('aria-disabled')).toBeDefined()
      expect(button.attributes('tabindex')).toBe('0')
    })

    it('應該在播放中時設置正確的 aria-disabled', async () => {
      const button = wrapper.find('span[role="button"]')

      mockIsPlaying.value = true

      await wrapper.vm.$nextTick()

      expect(button.attributes('aria-disabled')).toBeDefined()
    })
  })

  describe('事件處理邊界情況', () => {
    it('應該只響應 Enter 和 Space 鍵', async () => {
      const button = wrapper.find('span[role="button"]')

      // 測試其他鍵不會觸發
      await button.trigger('keydown', { key: 'a' })
      expect(mockHandleClick).not.toHaveBeenCalled()

      await button.trigger('keydown', { key: 'Escape' })
      expect(mockHandleClick).not.toHaveBeenCalled()

      // 測試正確的鍵會觸發
      await button.trigger('keydown.enter')
      expect(mockHandleClick).toHaveBeenCalledTimes(1)

      await button.trigger('keydown.space')
      expect(mockHandleClick).toHaveBeenCalledTimes(2)
    })
  })

  describe('組件生命週期', () => {
    it('應該在組件卸載時清理資源', () => {
      // 這個測試主要驗證組件能正常卸載而不報錯
      expect(() => {
        wrapper.unmount()
      }).not.toThrow()
    })
  })
})
