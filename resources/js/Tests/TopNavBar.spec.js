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
})
