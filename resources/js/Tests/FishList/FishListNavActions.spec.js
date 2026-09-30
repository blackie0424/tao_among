import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FishListNavActions from '@/Components/FishList/FishListNavActions.vue'

const SearchToggleButtonStub = {
  name: 'SearchToggleButton',
  template: '<button class="search-toggle-btn" @click="$emit(\'toggle\')">{{ label }}</button>',
  props: { label: { type: String, default: '搜尋' } },
  emits: ['toggle'],
}

const mountActions = (variant) =>
  mount(FishListNavActions, {
    props: { variant },
    global: { stubs: { SearchToggleButton: SearchToggleButtonStub } },
  })

describe('FishListNavActions', () => {
  it.each(['desktop', 'mobile'])('%s 不再渲染新增魚種連結', (variant) => {
    const wrapper = mountActions(variant)
    expect(wrapper.find('a[href="/fish/batch-create"]').exists()).toBe(false)
  })

  it('desktop 搜尋按鈕顯示完整標籤', () => {
    const wrapper = mountActions('desktop')
    expect(wrapper.getComponent(SearchToggleButtonStub).props('label')).toBe('搜尋魚類')
    expect(wrapper.get('div').classes()).not.toContain('h-10')
  })

  it('mobile 搜尋按鈕使用預設標籤', () => {
    const wrapper = mountActions('mobile')
    expect(wrapper.getComponent(SearchToggleButtonStub).props('label')).toBe('搜尋')
  })

  it.each(['desktop', 'mobile'])('%s 轉送 toggle 事件', async (variant) => {
    const wrapper = mountActions(variant)
    await wrapper.get('.search-toggle-btn').trigger('click')
    expect(wrapper.emitted('toggle')).toBeTruthy()
  })
})
