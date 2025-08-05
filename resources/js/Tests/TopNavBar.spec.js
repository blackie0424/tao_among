import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopNavBar from '@/Components/Global/TopNavBar.vue'

describe('TopNavBar', () => {
  it('載入 TopNavBar 時，應該顯示關閉按鈕、送出按鈕與標題', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        title: '測試標題',
        showSubmit: true,
      },
    })
    // 檢查標題
    expect(wrapper.text()).toContain('測試標題')
    // 檢查關閉按鈕
    const closeBtn = wrapper.find('button[type="button"]')
    expect(closeBtn.exists()).toBe(true)
    expect(closeBtn.find('svg').exists()).toBe(true)
    // 檢查送出按鈕
    const submitBtn = wrapper.find('button[type="submit"]')
    expect(submitBtn.exists()).toBe(true)
    expect(submitBtn.text()).toContain('送出')
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

  it('當 showLoading 為 false 時，Loading畫面不應該顯示', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        showLoading: false,
      },
    })
    // 檢查 Loading 畫面不應該存在
    expect(wrapper.find('.animate-spin').exists()).toBe(false)
  })

  it('當 showLoading 為 true 時，Loading畫面應該顯示', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        showLoading: true,
      },
    })
    // 檢查 Loading 畫面應該存在
    expect(wrapper.find('.animate-spin').exists()).toBe(true)
  })

  it('當 submitting 為 false 時，送出按鈕應過被看到，也可以點擊', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        submitting: false,
      },
    })
    // 檢查送出按鈕應該存在
    const submitBtn = wrapper.find('button:last-of-type')
    expect(submitBtn.exists()).toBe(true)
    // 檢查送出按鈕應該是啟用狀態
    expect(submitBtn.element.disabled).toBe(false)
  })

  it('當 submitting 為 true 時，送出按鈕應該被禁用', () => {
    const wrapper = mount(TopNavBar, {
      props: {
        submitting: true,
      },
    })
    // 檢查送出按鈕應該存在
    const submitBtn = wrapper.find('button:last-of-type')
    expect(submitBtn.exists()).toBe(true)
    // 檢查送出按鈕應該是禁用狀態
    expect(submitBtn.element.disabled).toBe(true)
  })
})
