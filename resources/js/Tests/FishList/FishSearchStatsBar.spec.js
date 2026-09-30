import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FishSearchStatsBar from '@/Components/FishList/FishSearchStatsBar.vue'

const filters = [
  { key: 'tribe', label: '部落', value: '阿美族' },
  { key: 'name', label: '名稱', value: '飛魚' },
]

const mountBar = (props = {}) =>
  mount(FishSearchStatsBar, {
    props: { totalCount: 10, appliedFilters: filters, ...props },
  })

describe('FishSearchStatsBar', () => {
  it('chip 整顆可點並帶完整 aria-label，點擊傳出 key', async () => {
    const wrapper = mountBar()
    const chip = wrapper.get('button[aria-label="移除條件 部落：阿美族"]')
    expect(chip.classes()).toContain('min-h-touch-secondary')
    expect(chip.text()).toContain('部落：阿美族')
    expect(chip.text()).toContain('✕')
    await chip.trigger('click')
    expect(wrapper.emitted('remove-filter')).toEqual([['tribe']])
  })

  it('兩個以上條件顯示清除全部並 emit clear-all', async () => {
    const wrapper = mountBar()
    const clear = wrapper.findAll('button').find((button) => button.text() === '清除全部')
    expect(clear).toBeTruthy()
    await clear.trigger('click')
    expect(wrapper.emitted('clear-all')).toHaveLength(1)
  })

  it('只有一個條件時不顯示清除全部', () => {
    const wrapper = mountBar({ appliedFilters: [filters[0]] })
    expect(wrapper.text()).not.toContain('清除全部')
  })

  it('header variant 無條件時不渲染', () => {
    const wrapper = mountBar({ appliedFilters: [], variant: 'header' })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('showTotalCount 以長者字級顯示資料筆數', () => {
    const wrapper = mountBar({ totalCount: 42, appliedFilters: [], showTotalCount: true })
    expect(wrapper.text()).toContain('資料筆數')
    expect(wrapper.text()).toContain('42')
    expect(wrapper.get('.text-elder-body')).toBeTruthy()
  })
})
