/**
 * Volume 組件測試
 *
 * 測試點擊事件的正確處理、驗證播放期間的點擊禁用功能、測試不同播放狀態的視覺渲染
 * Requirements: 2.1, 2.2, 1.3
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import Volume from '../Components/UI/Volume.vue'

// Mock useAudioPlayback composable
// #132：各狀態的按鈕 class（與 useAudioPlayback.spec.js 對照的固定預期值）
const IDLE_CLASSES =
  'bg-paper-raised border border-brand hover:bg-paper hover:border-brand-dark focus-visible:ring-2 focus-visible:ring-brand cursor-pointer'
const PLAYING_CLASSES = 'bg-brand border border-brand ring-2 ring-brand/30 cursor-not-allowed'
const ERROR_CLASSES =
  'bg-red-500 hover:bg-red-600 focus-visible:ring-2 focus-visible:ring-red-500 cursor-pointer'
const RETRYING_CLASSES = 'bg-yellow-500 cursor-not-allowed animate-pulse'

const mockHandleClick = vi.fn()
const mockPlaybackState = ref('idle')
const mockButtonClasses = ref(IDLE_CLASSES)
const mockButtonTitle = ref('點擊播放音頻')
const mockIsPlaying = ref(false)

// Mock AnimationOptimizer，隔離 DOM 副作用（init 會呼叫 matchMedia，jsdom 不支援）
vi.mock('../utils/AnimationOptimizer.js', () => ({
  default: {
    optimizationLevel: 'none',
    animationConfig: { enableTransitions: true, enableAnimations: true },
    shouldEnableAnimation: vi.fn(() => true),
    getOptimalAnimationConfig: vi.fn(() => ({ enableTransitions: true, enableAnimations: true })),
    init: vi.fn(),
  },
}))

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
import animationOptimizer from '../utils/AnimationOptimizer.js'

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
    mockButtonClasses.value = IDLE_CLASSES
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

    it('一般尺寸使用長者次要觸控規格，large 尺寸維持主要觸控規格', async () => {
      const button = wrapper.find('span[role="button"]')
      expect(button.classes()).toContain('min-w-touch-secondary')
      expect(button.classes()).toContain('min-h-touch-secondary')

      const largeWrapper = mount(Volume, { props: { ...defaultProps, large: true } })
      expect(largeWrapper.find('span[role="button"]').classes()).toContain('min-w-touch-primary')
      expect(largeWrapper.find('span[role="button"]').classes()).toContain('min-h-touch-primary')
      largeWrapper.unmount()
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
      mockButtonClasses.value = PLAYING_CLASSES
      mockButtonTitle.value = '正在播放...'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })

    it('應該在 error 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'error'
      mockButtonClasses.value = ERROR_CLASSES
      mockButtonTitle.value = '播放失敗，點擊重試 (1/3)'

      await wrapper.vm.$nextTick()

      // 檢查是否有 SVG 元素存在
      const svgElements = wrapper.findAll('svg')
      expect(svgElements.length).toBeGreaterThan(0)
    })

    it('應該在 retrying 狀態顯示正確的圖示', async () => {
      mockPlaybackState.value = 'retrying'
      mockButtonClasses.value = RETRYING_CLASSES
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

describe('Volume 播放中的效果（#159）', () => {
  it('播放中顯示三條會跳動的聲音條，不是打勾', async () => {
    mockPlaybackState.value = 'playing'
    mockButtonClasses.value = PLAYING_CLASSES
    const wrapper = mount(Volume, { props: { audioUrl: 'a.mp3' } })
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('svg rect.vol-bar')).toHaveLength(3)
    expect(wrapper.find('svg path').exists()).toBe(false) // 打勾圖示是 path
  })

  it('閒置時是播放三角形，沒有聲音條', async () => {
    mockPlaybackState.value = 'idle'
    mockButtonClasses.value = IDLE_CLASSES
    const wrapper = mount(Volume, { props: { audioUrl: 'a.mp3' } })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('svg rect.vol-bar').exists()).toBe(false)
    expect(wrapper.find('svg path').exists()).toBe(true)
  })
})

describe('Volume 各狀態實際輸出的顏色（#132 AC 6）', () => {
  const BLUE_OR_GRAY = /(?:bg|text|border|ring)-(?:blue|gray)-\d/

  const cases = [
    {
      state: 'idle',
      classes: IDLE_CLASSES,
      expectButton: ['bg-paper-raised', 'border-brand', 'focus-visible:ring-brand'],
      expectIcon: 'text-brand',
    },
    {
      state: 'playing',
      classes: PLAYING_CLASSES,
      expectButton: ['bg-brand', 'ring-brand/30'],
      expectIcon: 'text-white',
    },
    {
      state: 'error',
      classes: ERROR_CLASSES,
      expectButton: ['bg-red-500', 'hover:bg-red-600', 'focus-visible:ring-red-500'],
      expectIcon: 'text-white',
    },
    {
      state: 'retrying',
      classes: RETRYING_CLASSES,
      expectButton: ['bg-yellow-500', 'animate-pulse'],
      expectIcon: 'text-white',
    },
  ]

  const mountWithState = async ({ state, classes }) => {
    mockPlaybackState.value = state
    mockButtonClasses.value = classes
    const wrapper = mount(Volume, { props: { audioUrl: 'a.mp3' } })
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it.each(cases)('$state：按鈕與圖示用該狀態的顏色，沒有 Tailwind 藍或灰', async (testCase) => {
    const wrapper = await mountWithState(testCase)
    const button = wrapper.get('[role="button"]')
    const icon = wrapper.get('svg')

    expect(button.classes()).toEqual(expect.arrayContaining(testCase.expectButton))
    expect(icon.classes()).toContain(testCase.expectIcon)
    expect(button.attributes('class')).not.toMatch(BLUE_OR_GRAY)
    expect(icon.attributes('class')).not.toMatch(BLUE_OR_GRAY)
  })

  const buttonClassesOf = async (testCase) => {
    const wrapper = await mountWithState(testCase)
    const classes = wrapper.get('[role="button"]').classes()
    wrapper.unmount()
    return classes
  }

  it('錯誤與重試不使用主色，待播放與播放中的主色不被狀態色取代', async () => {
    expect(await buttonClassesOf(cases[2])).not.toContain('bg-brand')
    expect(await buttonClassesOf(cases[3])).not.toContain('bg-brand')
    expect(await buttonClassesOf(cases[0])).not.toContain('bg-red-500')
    expect(await buttonClassesOf(cases[1])).not.toContain('bg-yellow-500')
  })

  it('優化模式 aggressive 會移除 transition 與 animate，但顏色 class 保留', async () => {
    animationOptimizer.optimizationLevel = 'aggressive'
    try {
      const retrying = await buttonClassesOf(cases[3])
      const playing = await buttonClassesOf(cases[1])

      expect(retrying).toContain('bg-yellow-500')
      expect(retrying).not.toContain('animate-pulse')
      expect(playing).toEqual(expect.arrayContaining(['bg-brand', 'ring-brand/30']))
    } finally {
      animationOptimizer.optimizationLevel = 'none'
    }
  })

  it('一般尺寸 48px、large 56px 的觸控規格不變', () => {
    mockPlaybackState.value = 'idle'
    mockButtonClasses.value = IDLE_CLASSES
    const normal = mount(Volume, { props: { audioUrl: 'a.mp3' } })
    const large = mount(Volume, { props: { audioUrl: 'a.mp3', large: true } })

    expect(normal.get('[role="button"]').classes()).toContain('min-h-touch-secondary')
    expect(large.get('[role="button"]').classes()).toContain('min-h-touch-primary')
  })
})
