import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SearchToggleButton from '@/Components/UI/SearchToggleButton.vue'

describe('SearchToggleButton', () => {
  it('預設顯示「搜尋」', () => {
    expect(mount(SearchToggleButton).text()).toContain('搜尋')
  })

  it('可用 label 自訂文字', () => {
    expect(mount(SearchToggleButton, { props: { label: '搜尋魚類' } }).text()).toContain('搜尋魚類')
  })

  it('點擊時 emit toggle', async () => {
    const wrapper = mount(SearchToggleButton)
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('toggle')).toHaveLength(1)
  })

  it('具備 48px 觸控尺寸、長者字級與隱藏裝飾 icon', () => {
    const wrapper = mount(SearchToggleButton)
    expect(wrapper.get('button').classes()).toEqual(
      expect.arrayContaining(['min-h-touch-secondary', 'text-elder-body'])
    )
    expect(wrapper.get('svg').attributes('aria-hidden')).toBe('true')
  })
})
