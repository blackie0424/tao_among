import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ImageFramingEditor from '@/Components/CaptureRecord/ImageFramingEditor.vue'

// FramedImage 換成會回報照片尺寸（4000×3000）的替身，並把收到的 framing 顯示出來
vi.mock('@/Components/UI/FramedImage.vue', () => ({
  default: {
    name: 'FramedImage',
    props: ['src', 'alt', 'framing', 'wrapperClass'],
    emits: ['loaded'],
    mounted() { this.$emit('loaded', { width: 4000, height: 3000 }) },
    template: '<div data-testid="framed" :data-framing="JSON.stringify(framing)" />',
  },
}))

beforeEach(() => {
  // 調整框 320×240（4:3）
  vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(320)
  vi.spyOn(HTMLElement.prototype, 'clientHeight', 'get').mockReturnValue(240)
})

afterEach(() => vi.restoreAllMocks())

const mountEditor = (modelValue = null) =>
  mount(ImageFramingEditor, { props: { src: '/p.jpg', modelValue } })

const lastEmitted = (wrapper) => wrapper.emitted('update:modelValue').at(-1)[0]

const pointer = (wrapper, type, init) =>
  wrapper.get('[data-testid="framing-frame"]').trigger(type, { pointerId: 1, ...init })

describe('ImageFramingEditor', () => {
  it('調整框與兩個預覽（4:3、16:9）都用同一組調整值', () => {
    const framing = { x: 30, y: 60, zoom: 1.5, rotation: 90 }
    const wrapper = mountEditor(framing)

    const shown = wrapper.findAllComponents({ name: 'FramedImage' }).map((image) => image.props('framing'))
    expect(shown).toHaveLength(3)
    shown.forEach((value) => expect(value).toEqual(framing))
    expect(wrapper.text()).toContain('魚頁（16:9）')
  })

  it('放大倍率標示目前倍率；沒調整時是 1.0x', () => {
    expect(mountEditor(null).get('[data-testid="zoom-label"]').text()).toBe('1.0x')
    expect(mountEditor({ x: 50, y: 50, zoom: 2.5, rotation: 0 }).get('[data-testid="zoom-label"]').text()).toBe('2.5x')
  })

  it('滑桿放大：保持中心，倍率換成滑桿的值', async () => {
    const wrapper = mountEditor({ x: 50, y: 50, zoom: 1, rotation: 0 })
    await wrapper.get('[data-testid="zoom-slider"]').setValue('2')

    expect(lastEmitted(wrapper)).toEqual({ x: 50, y: 50, zoom: 2, rotation: 0 })
  })

  it('拖曳：2.0x 時往右拖 100px，魚身中心往左移到約 34.38%', async () => {
    const wrapper = mountEditor({ x: 50, y: 50, zoom: 2, rotation: 0 })
    await pointer(wrapper, 'pointerdown', { clientX: 100, clientY: 100 })
    await pointer(wrapper, 'pointermove', { clientX: 200, clientY: 100 })

    const next = lastEmitted(wrapper)
    expect(next.x).toBeCloseTo(34.38, 1)
    expect(next.y).toBe(50)
    expect(next.zoom).toBe(2)
  })

  it('放開後再拖，從目前位置接著算；放開後移動不再改變', async () => {
    const wrapper = mountEditor({ x: 50, y: 50, zoom: 2, rotation: 0 })
    await pointer(wrapper, 'pointerdown', { clientX: 100, clientY: 100 })
    await pointer(wrapper, 'pointerup', { clientX: 100, clientY: 100 })
    const before = wrapper.emitted('update:modelValue')?.length ?? 0

    await pointer(wrapper, 'pointermove', { clientX: 300, clientY: 100 })
    expect(wrapper.emitted('update:modelValue')?.length ?? 0).toBe(before)
  })

  it('雙指張開：以兩指距離的比例放大', async () => {
    const wrapper = mountEditor({ x: 50, y: 50, zoom: 1, rotation: 0 })
    await pointer(wrapper, 'pointerdown', { pointerId: 1, clientX: 100, clientY: 100 })
    await pointer(wrapper, 'pointerdown', { pointerId: 2, clientX: 200, clientY: 100 })
    await pointer(wrapper, 'pointermove', { pointerId: 2, clientX: 300, clientY: 100 })

    expect(lastEmitted(wrapper).zoom).toBe(2)
  })

  it('旋轉：向右轉 90 度，中心與放大重設；向左轉回來', async () => {
    const wrapper = mountEditor({ x: 20, y: 30, zoom: 2, rotation: 0 })
    await wrapper.get('[data-testid="rotate-right"]').trigger('click')
    expect(lastEmitted(wrapper)).toEqual({ x: 50, y: 50, zoom: 1, rotation: 90 })

    const rotated = mountEditor({ x: 50, y: 50, zoom: 1, rotation: 90 })
    await rotated.get('[data-testid="rotate-left"]').trigger('click')
    expect(lastEmitted(rotated)).toEqual({ x: 50, y: 50, zoom: 1, rotation: 0 })
  })

  it('還原：送出 null（沒調整）', async () => {
    const wrapper = mountEditor({ x: 20, y: 30, zoom: 2, rotation: 90 })
    await wrapper.get('[data-testid="framing-reset"]').trigger('click')
    await nextTick()

    expect(lastEmitted(wrapper)).toBeNull()
  })
})
