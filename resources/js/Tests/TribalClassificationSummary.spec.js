import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TribalClassificationSummary from '@/Components/TribalClassification/TribalClassificationSummary.vue'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { user: null } } }),
  Link: { template: '<a><slot /></a>' },
}))

const mountSummary = (props = {}) =>
  mount(TribalClassificationSummary, {
    props: { fishId: 1, classifications: [], tribes: [], ...props },
  })

describe('TribalClassificationSummary', () => {
  it('田調紀錄標題以對讀屏隱藏的線條圖示取代表情符號', () => {
    const wrapper = mountSummary({
      classifications: [{ tribe: '朗島', notes: '田調內容' }],
    })

    const heading = wrapper.findAll('h3').at(-1)
    const icon = heading.get('svg')
    expect(icon.attributes('aria-hidden')).toBe('true')
    expect(icon.attributes('fill')).toBe('none')
    expect(heading.text()).toBe('地方知識田調紀錄')
    expect(wrapper.text()).not.toContain('🏝️')
  })

  it('以逐欄空值顯示尚未紀錄與輔助文字色', () => {
    const wrapper = mountSummary({
      tribes: ['無紀錄', 'null 欄位', '空字串', '有值'],
      classifications: [
        { tribe: 'null 欄位', food_category: null, processing_method: '曬乾' },
        { tribe: '空字串', food_category: '', processing_method: '' },
        { tribe: '有值', food_category: '生食', processing_method: '煮湯' },
      ],
    })
    const cells = wrapper.findAll('tbody td')
    expect(cells.map((cell) => cell.text())).toEqual([
      '無紀錄',
      '尚未紀錄',
      '尚未紀錄',
      'null 欄位',
      '尚未紀錄',
      '曬乾',
      '空字串',
      '尚未紀錄',
      '尚未紀錄',
      '有值',
      '生食',
      '煮湯',
    ])
    expect(
      cells
        .filter((cell) => cell.text() === '尚未紀錄')
        .every((cell) => cell.classes().includes('text-elder-subtext'))
    ).toBe(true)
    expect(
      cells
        .filter((cell) => ['曬乾', '生食', '煮湯'].includes(cell.text()))
        .every((cell) => cell.classes().includes('text-gray-900'))
    ).toBe(true)
  })

  it('表頭、部落標籤、分類值與田調紀錄都至少使用 15px 字級', () => {
    const wrapper = mountSummary({
      classifications: [
        { tribe: '朗島', food_category: '生食', processing_method: '曬乾', notes: '田調內容' },
      ],
      tribes: ['朗島', '無紀錄'],
    })
    expect(wrapper.findAll('th').every((cell) => cell.classes().includes('text-elder-aux'))).toBe(
      true
    )
    expect(
      wrapper.findAll('.bg-blue-100').every((tag) => tag.classes().includes('text-elder-aux'))
    ).toBe(true)
    const valueCells = wrapper.findAll('tbody td:nth-child(2), tbody td:nth-child(3)')
    expect(valueCells).toHaveLength(4)
    for (const cell of valueCells) {
      expect(cell.classes()).toContain('text-base')
      expect(cell.classes()).toContain('md:text-lg')
      expect(cell.classes()).not.toContain('text-elder-aux')
    }
    expect(wrapper.get('p').classes()).toContain('text-elder-aux')
    expect(wrapper.get('p').classes()).toContain('md:text-base')
  })
})
