import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FramedImage from '@/Components/UI/FramedImage.vue'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: {} }) }))

// 用真正的 LazyImage：照片載入失敗 → 預設圖載入的路徑
beforeEach(() => {
  vi.stubGlobal('ResizeObserver', class { observe() {} disconnect() {} })
  vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(320)
  vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(180)
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const loadImg = async (wrapper, width, height) => {
  const img = wrapper.get('img')
  Object.defineProperty(img.element, 'naturalWidth', { value: width, configurable: true })
  Object.defineProperty(img.element, 'naturalHeight', { value: height, configurable: true })
  await img.trigger('load')
  await nextTick()
}

describe('FramedImage + 真實 LazyImage', () => {
  it('原照片失敗、預設圖載入後，不用原照片的調整值裁切預設圖；換回有效照片後恢復', async () => {
    const wrapper = mount(FramedImage, {
      props: { src: '/images/fish.jpg', alt: '魚', framing: { x: 30, y: 60, zoom: 1.5, rotation: 90 } },
    })
    await loadImg(wrapper, 4000, 3000)
    expect(wrapper.get('img').element.style.transform).toBe('rotate(90deg)')

    await wrapper.get('img').trigger('error')
    await nextTick()
    expect(wrapper.get('img').attributes('src')).toBe('/images/default.png')

    await loadImg(wrapper, 100, 100)
    expect(wrapper.get('img').attributes('style') ?? '').not.toContain('rotate')
    expect(wrapper.get('img').attributes('style') ?? '').not.toContain('absolute')

    await wrapper.setProps({ src: '/images/ok.jpg' })
    await loadImg(wrapper, 4000, 3000)
    expect(wrapper.get('img').element.style.transform).toBe('rotate(90deg)')
  })
})
