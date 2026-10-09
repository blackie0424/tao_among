import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PlacePicker from '@/Components/CaptureSessions/PlacePicker.vue'

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

function mountPicker(props = {}) {
  return mount(PlacePicker, { props: { tribe: 'ivalino', modelValue: null, placeName: '', ...props } })
}

describe('CaptureSessions/PlacePicker', () => {
  it('requires a tribe before searching', () => {
    const wrapper = mountPicker({ tribe: '' })
    expect(wrapper.get('input').attributes('disabled')).toBeDefined()
    expect(wrapper.get('input').attributes('placeholder')).toBe('請先選擇部落')
  })

  it('queries the selected tribe and emits an existing place id', async () => {
    vi.useFakeTimers()
    const fetchMock = vi.fn().mockResolvedValue({ json: async () => ({ places: [{ id: 7, tribe: 'ivalino', name: '東清灣', tao_name: 'Iraraley' }] }) })
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mountPicker()

    await wrapper.get('input').setValue('東清')
    await vi.advanceTimersByTimeAsync(200)
    await wrapper.findAll('button')[0].trigger('click')

    expect(fetchMock.mock.calls[0][0]).toContain('q=%E6%9D%B1%E6%B8%85')
    expect(fetchMock.mock.calls[0][0]).toContain('tribe=ivalino')
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([7])
    expect(wrapper.emitted('update:placeName').at(-1)).toEqual([''])
    expect(wrapper.get('[data-testid="selected-place"]').text()).toContain('已選：東清灣')
    expect(wrapper.text()).not.toContain('新增並選取')
  })

  it('sends typed text as a provisional place name when no suggestion is selected', async () => {
    vi.useFakeTimers()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ places: [] }) }))
    const wrapper = mountPicker()

    await wrapper.get('input').setValue(' 未確認地名 ')
    await vi.advanceTimersByTimeAsync(200)

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.emitted('update:placeName').at(-1)).toEqual(['未確認地名'])
    expect(wrapper.get('[data-testid="provisional-place-hint"]').text()).toContain('將建立待確認地名：未確認地名')
    expect(wrapper.text()).not.toContain('新增並選取')
  })

  it('clears a selected place and refreshes suggestions when tribe changes', async () => {
    vi.useFakeTimers()
    const fetchMock = vi.fn().mockResolvedValue({ json: async () => ({ places: [] }) })
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mountPicker({ modelValue: 7, initialName: '東清灣' })

    await wrapper.setProps({ tribe: 'yayo' })
    await vi.advanceTimersByTimeAsync(200)

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(fetchMock.mock.calls.at(-1)[0]).toContain('tribe=yayo')
  })
})
