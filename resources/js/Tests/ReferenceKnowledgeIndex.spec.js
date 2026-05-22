import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ReferenceKnowledgeIndex from '@/Pages/ReferenceKnowledge/Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  router: { delete: vi.fn() },
}))

vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText'],
  },
}))

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: {
    template: '<div :class="wrapperClass"><img v-if="src" :src="src" :alt="alt" :class="imgClass" /></div>',
    props: ['src', 'alt', 'wrapperClass', 'imgClass'],
  },
}))

const mountPage = (knowledge = []) =>
  mount(ReferenceKnowledgeIndex, {
    props: {
      fish: { id: 1, name: '飛魚' },
      knowledge: {
        data: knowledge,
      },
    },
  })

describe('ReferenceKnowledge Index', () => {
  it('會依文獻分組、依頁碼排序並顯示較大的封面', () => {
    const wrapper = mountPage([
      {
        id: 1,
        pages: '20',
        content: '乙書內容',
        note: null,
        tribe: null,
        reference: { id: 2, name: '乙書', image_url: 'https://example.com/b.jpg' },
      },
      {
        id: 2,
        pages: '16',
        content: '甲書第二段',
        note: null,
        tribe: 'iraraley',
        reference: { id: 1, name: '甲書', image_url: 'https://example.com/a.jpg' },
      },
      {
        id: 3,
        pages: '12-15',
        content: '甲書第一段',
        note: '補充',
        tribe: null,
        reference: { id: 1, name: '甲書', image_url: 'https://example.com/a.jpg' },
      },
    ])

    const groups = wrapper.findAll('[data-testid="reference-group"]')
    expect(groups).toHaveLength(2)
    expect(groups[0].text()).toContain('甲書')
    expect(groups[0].text().indexOf('12-15')).toBeLessThan(groups[0].text().indexOf('16'))
    expect(groups[0].text()).toContain('部落：iraraley')
    expect(groups[1].text()).toContain('乙書')
    expect(wrapper.find('[data-testid="reference-group-cover"]').classes()).toContain('w-32')
    expect(wrapper.find('img[alt="甲書"]').exists()).toBe(true)
  })
})
