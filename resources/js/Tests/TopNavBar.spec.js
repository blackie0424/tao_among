import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopNavBar from '@/Components/Global/TopNavBar.vue'

describe('TopNavBar', () => {
  it('載入 TopNavBar 時，應該顯示取消、送出按鈕與標題', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        title: '測試標題',
        goBack: vi.fn(),
        submitNote: vi.fn(),
        showSubmit: true,
      },
    })
    // 檢查標題
    expect(wrapper.text()).toContain('測試標題')
    // 檢查取消按鈕
    const buttons = wrapper.findAll('button')
    expect(buttons[0].text()).toContain('取消')
    // 檢查送出按鈕
    expect(buttons[1].text()).toContain('送出')
  })

  it('載入 TopNavBar 時，可以自訂送出按鈕顯示的文字', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        title: '測試標題',
        goBack: vi.fn(),
        submitNote: vi.fn(),
        showSubmit: true,
        submitLabel: '自訂送出',
      },
    })
    // 檢查送出按鈕
    const buttons = wrapper.findAll('button')
    expect(buttons[1].text()).toContain('自訂送出')
  })

  it('載入 TopNavBar 時，沒有給標題時，應該要顯示請新增標題文字', async () => {
    const wrapper = mount(TopNavBar, {
      props: {
        goBack: vi.fn(),
        submitNote: vi.fn(),
        showSubmit: true,
      },
    })
    // 檢查標題
    expect(wrapper.text()).toContain('請新增標題')
  })

  it('點擊取消按鈕時，應該呼叫 goBack 方法', async () => {
    const goBack = vi.fn()
    const wrapper = mount(TopNavBar, {
      props: {
        goBack,
        submitNote: vi.fn(),
        showSubmit: true,
      },
    })
    // 模擬點擊取消按鈕
    await wrapper.find('button:first-of-type').trigger('click')
    // 檢查 goBack 是否被呼叫
    expect(goBack).toHaveBeenCalled()
  })

  it('點擊送出按鈕時，應該呼叫 submitNote 方法', async () => {
    const submitNote = vi.fn()
    const wrapper = mount(TopNavBar, {
      props: {
        goBack: vi.fn(),
        submitNote,
        showSubmit: true,
      },
    })
    // 模擬點擊送出按鈕
    await wrapper.find('button:last-of-type').trigger('click')
    // 檢查 submitNote 是否被呼叫
    expect(submitNote).toHaveBeenCalled()
  })
})
