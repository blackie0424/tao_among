import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FishSearchStatsBar from '@/Components/Fish/FishList/FishSearchStatsBar.vue'

const filters = [
  { key: 'tribe', label: '部落', value: '阿美族' },
  { key: 'name', label: '名稱', value: '飛魚' },
]

describe('FishSearchStatsBar', () => {
  // ─── default variant ──────────────────────────────────────────────────────

  describe('variant="default"', () => {
    it('有 appliedFilters 時渲染 chip', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 10, appliedFilters: filters },
      })
      expect(wrapper.text()).toContain('部落：阿美族')
      expect(wrapper.text()).toContain('名稱：飛魚')
    })

    it('showTotalCount=true 時顯示筆數', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 42, appliedFilters: [], showTotalCount: true },
      })
      expect(wrapper.text()).toContain('42')
    })

    it('showTotalCount=false 時不顯示筆數', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 42, appliedFilters: [], showTotalCount: false },
      })
      expect(wrapper.text()).not.toContain('42')
    })

    it('點擊 × 按鈕 emit remove-filter 事件', async () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: filters },
      })
      await wrapper.find('button[aria-label="移除條件 部落"]').trigger('click')
      expect(wrapper.emitted('remove-filter')).toEqual([['tribe']])
    })
  })

  // ─── header variant ───────────────────────────────────────────────────────

  describe('variant="header"', () => {
    it('appliedFilters 為空時不渲染任何可見內容', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: [], variant: 'header' },
      })
      expect(wrapper.find('.border-t').exists()).toBe(false)
    })

    it('appliedFilters 有值時顯示外層 border-t 容器', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: filters, variant: 'header' },
      })
      expect(wrapper.find('.border-t').exists()).toBe(true)
    })

    it('appliedFilters 有值時顯示 container 內層包裝', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: filters, variant: 'header' },
      })
      expect(wrapper.find('.container').exists()).toBe(true)
    })

    it('appliedFilters 有值時渲染 chip', () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: filters, variant: 'header' },
      })
      expect(wrapper.text()).toContain('部落：阿美族')
    })

    it('點擊 × 按鈕 emit remove-filter 事件', async () => {
      const wrapper = mount(FishSearchStatsBar, {
        props: { totalCount: 0, appliedFilters: filters, variant: 'header' },
      })
      await wrapper.find('button[aria-label="移除條件 部落"]').trigger('click')
      expect(wrapper.emitted('remove-filter')).toEqual([['tribe']])
    })
  })
})
