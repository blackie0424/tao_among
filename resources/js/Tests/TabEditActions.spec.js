import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TabEditActions from '@/Components/Global/TabEditActions.vue'
vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))
describe('TabEditActions', () => {
  it('以固定文字與路徑顯示多個可換行入口', () => {
    const wrapper = mount(TabEditActions, {
      props: {
        items: [
          { label: '編輯名稱', href: '/fish/42/edit' },
          { label: '管理照片', href: '/fish/42/media-manager' },
        ],
      },
    })
    expect(wrapper.findAll('a').map((a) => [a.text(), a.attributes('href')])).toEqual([
      ['編輯名稱', '/fish/42/edit'],
      ['管理照片', '/fish/42/media-manager'],
    ])
    expect(wrapper.classes()).toContain('flex-wrap')
  })
  it('空項目不渲染容器', () => {
    expect(
      mount(TabEditActions, { props: { items: [] } })
        .find('div')
        .exists()
    ).toBe(false)
  })
  it('未傳項目不渲染容器', () => {
    expect(mount(TabEditActions).find('div').exists()).toBe(false)
  })
})
