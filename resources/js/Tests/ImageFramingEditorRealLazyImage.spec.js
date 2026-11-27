import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ImageFramingEditor from '@/Components/CaptureRecord/ImageFramingEditor.vue'

vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: {} }) }))

// 用真正的 FramedImage 與 LazyImage：原照片失敗 → 預設圖載入，編輯器不能產生可存的調整值
beforeEach(() => {
  vi.stubGlobal('ResizeObserver', class { observe() {} disconnect() {} })
  vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(320)
  vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(240)
})

afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

const mainImg = (wrapper) => wrapper.get('[data-testid="framing-frame"] img')

const loadMain = async (wrapper, width, height) => {
  const img = mainImg(wrapper)
  Object.defineProperty(img.element, 'naturalWidth', { value: width, configurable: true })
  Object.defineProperty(img.element, 'naturalHeight', { value: height, configurable: true })
  await img.trigger('load')
  await nextTick()
}

describe('ImageFramingEditor 照片載入失敗', () => {
  it('原照片失敗、預設圖載入後：顯示提示、不產生調整值；換成有效照片後恢復', async () => {
    const wrapper = mount(ImageFramingEditor, {
      props: { src: '/images/fish.jpg', modelValue: { x: 50, y: 50, zoom: 2, rotation: 0 } },
    })
    await loadMain(wrapper, 4000, 3000)

    await mainImg(wrapper).trigger('error')
    await nextTick()
    await loadMain(wrapper, 100, 100) // 預設圖載入

    expect(wrapper.find('[data-testid="framing-photo-failed"]').exists()).toBe(true)

    const frame = wrapper.get('[data-testid="framing-frame"]')
    await frame.trigger('pointerdown', { pointerId: 1, clientX: 100, clientY: 100 })
    await frame.trigger('pointermove', { pointerId: 1, clientX: 200, clientY: 100 })
    await wrapper.get('[data-testid="zoom-slider"]').setValue('2.5')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    expect(wrapper.get('[data-testid="rotate-right"]').attributes('disabled')).toBeDefined()

    await wrapper.setProps({ src: '/images/ok.jpg' })
    await loadMain(wrapper, 4000, 3000)
    expect(wrapper.find('[data-testid="framing-photo-failed"]').exists()).toBe(false)

    await wrapper.get('[data-testid="zoom-slider"]').setValue('2.5')
    expect(wrapper.emitted('update:modelValue').at(-1)[0].zoom).toBe(2.5)
  })
})
