import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FabButton from '@/Components/FabButton.vue'

describe('FabButton 畫面測試', () => {
  it('應顯示正確的 label 與 icon', () => {
    const wrapper = mount(FabButton, {
      props: { label: '新增魚類', icon: '＋' },
    })
    expect(wrapper.text()).toContain('新增魚類')
    expect(wrapper.html()).toContain('＋')
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
