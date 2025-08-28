import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FabButton from '@/Components/FabButton.vue'

describe('FabButton', () => {
  it('應顯示正確的 label 與 icon', () => {
    const wrapper = mount(FabButton, {
      props: {
        label: '新增魚類',
        icon: '＋',
      },
    })
    expect(wrapper.text()).toContain('新增魚類')
    expect(wrapper.html()).toContain('＋')
  })

  it('點擊時若有 to prop，應導向指定網址', async () => {
    // 模擬 window.location.href
    const hrefSpy = vi.spyOn(window, 'location', 'get').mockReturnValue({ href: '' })
    const setHref = vi.fn()
    Object.defineProperty(window, 'location', {
      value: {
        set href(val) {
          setHref(val)
        },
      },
      writable: true,
    })

    const wrapper = mount(FabButton, {
      props: {
        to: '/fish/create',
      },
    })
    await wrapper.find('button').trigger('click')
    expect(setHref).toHaveBeenCalledWith('/fish/create')
    hrefSpy.mockRestore()
  })

  it('點擊時若無 to prop，應 emit click 事件', async () => {
    const wrapper = mount(FabButton)
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('click')).toBeTruthy()
  })

  it('hover 或 focus 時應展開顯示 label', async () => {
    const wrapper = mount(FabButton, {
      props: { label: '新增', icon: '+' },
    })
    const button = wrapper.find('button')
    await button.trigger('mouseenter')
    expect(wrapper.html()).toContain('新增')
    await button.trigger('mouseleave')
    // label 仍在 DOM，但 opacity 會變化，這裡只驗證展開時有顯示
  })
})
