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
        .every((cell) => cell.classes().includes('text-elder-text'))
    ).toBe(true)
  })

  it('表頭、部落標籤、分類值與田調紀錄一律 18px 字級', () => {
    const wrapper = mountSummary({
      classifications: [
        { tribe: '朗島', food_category: '生食', processing_method: '曬乾', notes: '田調內容' },
      ],
      tribes: ['朗島', '無紀錄'],
    })
    expect(wrapper.findAll('th').every((cell) => cell.classes().includes('text-elder-body'))).toBe(
      true
    )
    expect(
      wrapper.findAll('[data-testid="tribe-name"]').every((tag) => tag.classes().includes('text-elder-body'))
    ).toBe(true)
    const valueCells = wrapper.findAll('tbody td:nth-child(2), tbody td:nth-child(3)')
    expect(valueCells).toHaveLength(4)
    for (const cell of valueCells) {
      expect(cell.classes()).toContain('text-elder-body')
      expect(cell.classes()).not.toEqual(expect.arrayContaining(['text-base']))
      expect(cell.classes()).not.toContain('md:text-lg')
      expect(cell.classes()).not.toContain('text-elder-aux')
    }
    expect(wrapper.get('p').classes()).toContain('text-elder-body')
    expect(wrapper.get('p').classes()).not.toContain('text-elder-aux')
    expect(wrapper.get('p').classes()).not.toContain('md:text-base')
  })

  it('手機卡片逐欄呈現空值色彩、字級與長字串，桌機維持三欄表格', () => {
    const wrapper = mountSummary({
      tribes: ['無紀錄', 'null 欄位', '空字串', '有值'],
      classifications: [
        {
          tribe: 'null 欄位',
          food_category: null,
          processing_method: '沒有空白的超長處理方式文字',
        },
        {
          tribe: '空字串',
          food_category: '沒有空白的超長飲食分類文字',
          processing_method: '',
        },
        {
          tribe: '有值',
          food_category: '生食',
          processing_method: '曬乾',
        },
      ],
    })

    const cards = wrapper.findAll('[data-testid="tribal-classification-card"]')
    expect(cards).toHaveLength(4)
    expect(cards.map((card) => card.find('span').text())).toEqual(['無紀錄', 'null 欄位', '空字串', '有值'])
    expect(cards.every((card) => card.find('span').classes().includes('text-elder-body'))).toBe(true)
    expect(cards.every((card) => card.findAll('dt').every((label) => label.classes().includes('text-elder-body')))).toBe(true)
    expect(cards.map((card) => card.findAll('dd').map((value) => value.text()))).toEqual([
      ['尚未紀錄', '尚未紀錄'],
      ['尚未紀錄', '沒有空白的超長處理方式文字'],
      ['沒有空白的超長飲食分類文字', '尚未紀錄'],
      ['生食', '曬乾'],
    ])
    const values = cards.flatMap((card) => card.findAll('dd'))
    expect(values.every((value) => value.classes().includes('text-elder-body'))).toBe(true)
    expect(values.filter((value) => value.text() === '尚未紀錄').every((value) => value.classes().includes('text-elder-subtext'))).toBe(true)
    expect(values.filter((value) => value.text() !== '尚未紀錄').every((value) => value.classes().includes('text-elder-text'))).toBe(true)
    expect(cards.every((card) => card.classes().includes('break-words'))).toBe(true)
    expect(wrapper.get('table').classes()).toContain('hidden')
    expect(wrapper.get('table').classes()).toContain('md:table')
  })

  it('田調紀錄的無空白長字串會在卡片內斷行', () => {
    const wrapper = mountSummary({
      classifications: [{ tribe: '朗島', notes: '沒有空白的超長田調紀錄文字' }],
    })

    expect(wrapper.get('p').classes()).toContain('break-words')
  })
})

describe('TribalClassificationSummary 博物誌樣式（#132 AC 7）', () => {
  const html = () =>
    mountSummary({
      classifications: [{ tribe: '朗島', food_category: '生食', processing_method: '曬乾', notes: '田調內容' }],
      tribes: ['朗島', '無紀錄'],
    }).html()

  it('沒有 Tailwind 預設色與卡片陰影', () => {
    expect(html()).not.toMatch(/(?:bg|text|border|divide)-(?:blue|gray|green|teal|slate|emerald)-\d|shadow|bg-white/)
  })

  it('部落名為宋體，列與列之間用細線分隔', () => {
    const wrapper = mountSummary({
      classifications: [{ tribe: '朗島', food_category: '生食', processing_method: '曬乾' }],
      tribes: ['朗島'],
    })
    const tribe = wrapper.get('[data-testid="tribe-name"]')

    expect(tribe.classes()).toEqual(expect.arrayContaining(['font-serif', 'text-elder-body']))
    expect(wrapper.get('[data-testid="tribal-classification-card"]').classes()).toContain('border-rule')
    expect(wrapper.get('tbody').classes()).toContain('divide-rule')
  })

  it('部落清單為空時，沿用回退：只列出已有的分類紀錄', () => {
    const wrapper = mountSummary({
      classifications: [{ tribe: '朗島', food_category: '生食', processing_method: null }],
      tribes: [],
    })

    expect(wrapper.findAll('[data-testid="tribal-classification-card"]')).toHaveLength(1)
    expect(wrapper.get('[data-testid="tribal-classification-card"]').text()).toContain('尚未紀錄')
  })
})
