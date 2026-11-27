import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FramedImage from '@/Components/UI/FramedImage.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    name: 'LazyImage',
    props: ['src', 'alt', 'wrapperClass', 'wrapperStyle', 'imgClass', 'imgStyle', 'imgIndex'],
    emits: ['failed', 'loaded'],
    template: '<div data-testid="frame" :style="wrapperStyle"><img :src="src" :class="imgClass" :style="imgStyle" /></div>',
  },
}))

// 框 320×180（jsdom 不排版，直接指定）
const FRAME = { width: 320, height: 180 }

beforeEach(() => {
  vi.stubGlobal('ResizeObserver', class { observe() {} disconnect() {} })
  vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(FRAME.width)
  vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(FRAME.height)
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const mountFramed = (props = {}) =>
  mount(FramedImage, { props: { src: '/p.jpg', alt: '魚', ...props } })

const loadPhoto = async (wrapper, width, height) => {
  wrapper.getComponent({ name: 'LazyImage' }).vm.$emit('loaded', { width, height })
  await nextTick()
}

describe('FramedImage', () => {
  it('沒有調整值：維持 cover 置中，不加任何行內樣式', async () => {
    const wrapper = mountFramed({ framing: null })
    await loadPhoto(wrapper, 4000, 3000)

    const img = wrapper.get('img')
    expect(img.classes()).toContain('object-cover')
    expect(img.attributes('style') ?? '').toBe('')
  })

  it('有調整值但照片尺寸還沒載入：先維持預設樣子', () => {
    const wrapper = mountFramed({ framing: { x: 30, y: 60, zoom: 1.5, rotation: 0 } })

    expect(wrapper.get('img').attributes('style') ?? '').toBe('')
  })

  it('有調整值且照片載入後：套用算出的位置與大小（4000×3000、框 320×180、30/60、1.5x）', async () => {
    const wrapper = mountFramed({ framing: { x: 30, y: 60, zoom: 1.5, rotation: 0 } })
    await loadPhoto(wrapper, 4000, 3000)

    const style = wrapper.get('img').element.style
    expect(style.position).toBe('absolute')
    expect(parseFloat(style.width)).toBeCloseTo(150)
    expect(parseFloat(style.height)).toBeCloseTo(200)
    expect(parseFloat(style.left)).toBeCloseTo(0)
    expect(parseFloat(style.top)).toBeCloseTo(-70)
  })

  it('旋轉 90 度：img 帶 rotate(90deg)', async () => {
    const wrapper = mountFramed({ framing: { x: 50, y: 50, zoom: 1, rotation: 90 } })
    await loadPhoto(wrapper, 2250, 4000)

    expect(wrapper.get('img').element.style.transform).toBe('rotate(90deg)')
  })

  it('ratio="photo"：框比例取旋轉後的照片比例', async () => {
    const wrapper = mountFramed({ framing: { x: 50, y: 50, zoom: 1, rotation: 90 }, ratio: 'photo' })
    await loadPhoto(wrapper, 2250, 4000)

    expect(wrapper.get('[data-testid="frame"]').element.style.aspectRatio.replace(/\s/g, '')).toBe('4000/2250')
  })

  it('ratio="photo" 且沒有調整值：不改框比例', async () => {
    const wrapper = mountFramed({ framing: null, ratio: 'photo' })
    await loadPhoto(wrapper, 2250, 4000)

    expect(wrapper.get('[data-testid="frame"]').attributes('style') ?? '').toBe('')
  })
})
