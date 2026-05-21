import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ReferenceKnowledgeSection from '@/Components/ReferenceKnowledge/ReferenceKnowledgeSection.vue'

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

  it('editor 有資料時應顯示文獻名稱與頁碼', () => {
    const wrapper = mountSection({
      isEditor: true,
      user: { id: 1, role: 'editor' },
      referenceKnowledge: [
        {
          id: 1,
          pages: '12-15',
          content: '這是文獻內容',
          note: '補充備註',
          reference: { name: '蘭嶼魚類誌' },
        },
      ],
    })

    expect(wrapper.text()).toContain('文獻知識')
    expect(wrapper.text()).toContain('蘭嶼魚類誌')
    expect(wrapper.text()).toContain('12-15')
    expect(wrapper.text()).toContain('這是文獻內容')
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

