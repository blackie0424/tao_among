import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import PlacePicker from '@/Components/CaptureSessions/PlacePicker.vue'

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('CaptureSessions/PlacePicker', () => {
  it('suggests an existing place and emits its fixed id when selected', async () => {
    vi.useFakeTimers()
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        json: async () => ({ places: [{ id: 7, name: '東清灣', tao_name: 'Iraraley' }] }),
      })
      .mockResolvedValueOnce({ json: async () => ({ places: [] }) })
    vi.stubGlobal('fetch', fetchMock)
    const wrapper = mount(PlacePicker)

    await wrapper.find('input[placeholder="輸入地名搜尋"]').setValue('東清')
    await wrapper.find('input[placeholder="輸入地名搜尋"]').trigger('input')
    await vi.advanceTimersByTimeAsync(200)
    await wrapper.findAll('button')[0].trigger('click')

    expect(fetchMock).toHaveBeenCalledWith('/places/suggest?q=%E6%9D%B1%E6%B8%85', expect.any(Object))
    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([7])
    expect(wrapper.text()).toContain('已選：東清灣')
    expect(wrapper.find('[data-testid="create-place-panel"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('新增並選取')

    await wrapper.find('input[placeholder="輸入地名搜尋"]').setValue('新地名')
    await wrapper.find('input[placeholder="輸入地名搜尋"]').trigger('input')
    await vi.advanceTimersByTimeAsync(200)

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.get('[data-testid="create-place-panel"]').text()).toContain('新增並選取')
  })

  it('offers the existing place after a duplicate create response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      status: 422,
      ok: false,
      json: async () => ({ message: '已有同名地名，要用既有的嗎？', existing_place: { id: 9, name: '朗島灣' } }),
    }))
    const wrapper = mount(PlacePicker)
    await wrapper.find('input[placeholder="輸入地名搜尋"]').setValue(' 朗島灣 ')
    await wrapper.find('button').trigger('click')
    await Promise.resolve()
    await wrapper.vm.$nextTick()

    expect(wrapper.get('[role="alert"]').text()).toBe('已有同名地名，要用既有的嗎？')
    expect(wrapper.text()).toContain('朗島灣')
  })

  it('keeps the selected place empty when text is entered without selecting or creating', async () => {
    vi.useFakeTimers()
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      json: async () => ({ places: [] }),
    }))
    const wrapper = mount(PlacePicker, { props: { modelValue: null } })

    await wrapper.find('input[placeholder="輸入地名搜尋"]').setValue('未建立地名')
    await wrapper.find('input[placeholder="輸入地名搜尋"]').trigger('input')
    await vi.advanceTimersByTimeAsync(200)

    expect(wrapper.emitted('update:modelValue').at(-1)).toEqual([null])
    expect(wrapper.get('[data-testid="create-place-panel"]').exists()).toBe(true)
  })
})
