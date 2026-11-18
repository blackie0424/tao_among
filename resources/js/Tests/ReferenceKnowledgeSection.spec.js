import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ReferenceKnowledgeSection from '@/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<div :class="wrapperClass"><img v-if="src" :src="src" :alt="alt" :class="imgClass" /></div>',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

const mountSection = (propsData = {}) =>
  mount(ReferenceKnowledgeSection, {
    props: {
      referenceKnowledge: [],
      isEditor: false,
      user: null,
      ...propsData,
    },
  })

describe('ReferenceKnowledgeSection', () => {
  it('viewer 不應渲染區塊', () => {
    const wrapper = mountSection({ isEditor: false, user: { id: 1, role: 'viewer' } })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('editor 有資料時應依文獻分組顯示封面、名稱與頁碼', () => {
    const wrapper = mountSection({
      isEditor: true,
      user: { id: 1, role: 'editor' },
      referenceKnowledge: [
        {
          id: 1,
          pages: '20',
          content: '乙書第一段',
          note: null,
          reference: {
            id: 2,
            name: '乙書',
            image_url: 'https://example.com/b-book.jpg',
          },
        },
        {
          id: 2,
          pages: '16',
          content: '甲書第二段',
          note: '補充備註',
          reference: {
            id: 1,
            name: '甲書',
            image_url: 'https://example.com/a-book.jpg',
          },
          tribe: 'iraraley',
        },
        {
          id: 3,
          pages: '12-15',
          content: '甲書第一段',
          note: null,
          reference: {
            id: 1,
            name: '甲書',
            image_url: 'https://example.com/a-book.jpg',
          },
        },
      ],
    })

    expect(wrapper.text()).toContain('文獻知識')
    expect(wrapper.text()).toContain('甲書')
    expect(wrapper.text()).toContain('乙書')
    expect(wrapper.text()).toContain('12-15')
    expect(wrapper.text()).toContain('甲書第一段')
    expect(wrapper.text()).toContain('甲書第二段')
    expect(wrapper.text()).toContain('部落：iraraley')
    expect(wrapper.find('img[alt="甲書"]').exists()).toBe(true)
    expect(wrapper.find('img[alt="乙書"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="reference-group-cover"]').classes()).toContain('w-32')

    const groups = wrapper.findAll('[data-testid="reference-group"]')
    expect(groups).toHaveLength(2)
    expect(groups[0].text()).toContain('甲書')
    expect(groups[0].text()).toContain('甲書第一段')
    expect(groups[0].text()).toContain('甲書第二段')
    expect(groups[0].text().indexOf('12-15')).toBeLessThan(groups[0].text().indexOf('16'))
    expect(groups[1].text()).toContain('乙書')
    expect(groups[1].text()).toContain('乙書第一段')
  })

  it('editor 無資料時應顯示空狀態', () => {
    const wrapper = mountSection({
      isEditor: true,
      user: { id: 1, role: 'editor' },
      referenceKnowledge: [],
    })

    expect(wrapper.text()).toContain('目前沒有文獻知識的紀錄')
  })
})

describe('ReferenceKnowledgeSection 博物誌樣式（#132 AC 8）', () => {
  const sample = {
    id: 1,
    pages: '20',
    content: '乙書第一段',
    note: '備註內容',
    tribe: '朗島',
    reference: { id: 2, name: '乙書', image_url: null },
  }
  const mountEditor = (referenceKnowledge) =>
    mountSection({ isEditor: true, user: { id: 1, role: 'editor' }, referenceKnowledge })

  it('沒有 Tailwind 預設色與卡片陰影，文獻名為宋體', () => {
    const wrapper = mountEditor([sample])

    expect(wrapper.html()).not.toMatch(
      /(?:bg|text|border)-(?:blue|gray|green|teal|slate|emerald)-\d|shadow|bg-white/
    )
    expect(wrapper.get('h3').classes()).toContain('font-serif')
  })

  it('部落標籤為細框膠囊；頁碼、備註、封面佔位字都是 18px', () => {
    const wrapper = mountEditor([sample])

    expect(wrapper.get('[data-testid="reference-tribe-tag"]').classes()).toEqual(
      expect.arrayContaining(['border', 'border-rule-strong', 'text-elder-body'])
    )
    expect(wrapper.get('[data-testid="reference-pages"]').classes()).toContain('text-elder-body')
    expect(wrapper.get('[data-testid="reference-note"]').classes()).toContain('text-elder-body')
    expect(wrapper.get('[data-testid="reference-group-cover"]').text()).toBe('暫無封面')
    expect(wrapper.get('[data-testid="reference-group-cover"] div').classes()).toContain('text-elder-body')
  })

  it('空狀態文字 18px 次要字色', () => {
    const empty = mountEditor([]).get('[data-testid="reference-empty"]')

    expect(empty.text()).toBe('目前沒有文獻知識的紀錄')
    expect(empty.classes()).toEqual(expect.arrayContaining(['text-elder-body', 'text-elder-subtext']))
  })
})
