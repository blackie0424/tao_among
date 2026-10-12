import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FishTabBar from '@/Components/FishDetail/FishTabBar.vue'

const tabs = [
  { key: 'basic', label: '基本' },
  { key: 'local', label: '地方知識' },
  { key: 'advanced', label: '進階知識' },
  { key: 'reference', label: '文獻知識' },
  { key: 'capture', label: '捕獲紀錄' },
  { key: 'audio', label: '發音' },
]

const mountTabBar = (props = {}) =>
  mount(FishTabBar, {
    props: { tabs, activeTab: 'basic', ...props },
  })

describe('FishTabBar', () => {
  it.each([
    [3, 'col-span-2'],
    [4, 'col-span-3'],
    [5, 'col-span-2'],
    [6, 'col-span-2'],
  ])('手機顯示 %i 個分頁時使用無橫向捲動的格線排列', (count, firstRowSpan) => {
    const wrapper = mountTabBar({ tabs: tabs.slice(0, count) })
    const tabBar = wrapper.get('[data-testid="fish-tab-bar"]')
    const buttons = wrapper.findAll('button')

    expect(tabBar.classes()).toContain('grid')
    expect(tabBar.classes()).toContain('grid-cols-6')
    expect(tabBar.classes()).not.toContain('overflow-x-auto')
    expect(buttons).toHaveLength(count)
    expect(buttons.map((button) => button.text())).toEqual(
      tabs.slice(0, count).map((tab) => tab.label)
    )
    expect(buttons.slice(0, count === 5 ? 3 : count).every((button) => button.classes().includes(firstRowSpan))).toBe(true)
    if (count === 5) {
      expect(buttons.slice(3).every((button) => button.classes().includes('col-span-3'))).toBe(true)
    }
  })

  it('五個分頁時上排三格、下排兩格各佔半列', () => {
    const wrapper = mountTabBar({ tabs: tabs.slice(0, 5) })
    const buttons = wrapper.findAll('button')

    expect(buttons.slice(0, 3).every((button) => button.classes().includes('col-span-2'))).toBe(true)
    expect(buttons.slice(3).every((button) => button.classes().includes('col-span-3'))).toBe(true)
  })

  it('以淺灰圓角格顯示分頁，並標示目前選中項目', async () => {
    const wrapper = mountTabBar({ activeTab: 'local' })
    const [basic, local] = wrapper.findAll('button')

    expect(basic.classes()).toContain('bg-gray-100')
    expect(basic.classes()).toContain('text-elder-subtext')
    expect(basic.classes()).not.toContain('bg-blue-100')
    expect(basic.classes()).not.toContain('text-blue-700')
    expect(basic.classes()).toContain('rounded-lg')
    expect(local.classes()).toContain('bg-blue-100')
    expect(local.classes()).toContain('text-blue-700')
    expect(local.classes()).not.toContain('bg-gray-100')
    expect(local.classes()).not.toContain('text-elder-subtext')
    await basic.trigger('click')
    expect(wrapper.emitted('select')).toEqual([['basic']])
  })

  it('桌機改為單列且依文字寬度排列', () => {
    const wrapper = mountTabBar()
    const buttons = wrapper.findAll('button')

    expect(wrapper.get('[data-testid="fish-tab-bar"]').classes()).toContain('md:flex')
    expect(buttons.every((button) => button.classes().includes('md:w-auto'))).toBe(true)
  })
})
