import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FabButton from '@/Components/FabButton.vue'

describe('FabButton 功能測試', () => {
  it('點擊時若有 to prop，應導向指定網址', async () => {
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
    const wrapper = mount(FabButton, { props: { to: '/fish/create' } })
    await wrapper.find('button').trigger('click')
    expect(setHref).toHaveBeenCalledWith('/fish/create')
    hrefSpy.mockRestore()
  })

  it('點擊時若無 to prop，應 emit click 事件', async () => {
    const wrapper = mount(FabButton)
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('click')).toBeTruthy()
  })

  it('根據 position prop 顯示正確的 class', () => {
    const wrapper = mount(FabButton, { props: { position: 'left-top' } })
    expect(wrapper.classes()).toContain('left-6')
    expect(wrapper.classes()).toContain('top-6')
  })
})
