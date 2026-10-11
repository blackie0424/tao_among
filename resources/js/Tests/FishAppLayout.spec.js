import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { user: { role: 'editor' } }, fish: { id: 42 } } }),
}))

vi.mock('@/Components/Global/AppNavBar.vue', () => ({
  default: {
    name: 'AppNavBar',
    template:
      '<header><slot /><slot name="mobile-actions" /><slot name="desktop-nav" /><slot name="header-extension" /></header>',
    props: [
      'pageTitle',
      'breadcrumbPage',
      'mobileBackUrl',
      'mobileBackText',
      'stickyMobile',
      'showMobileTitle',
    ],
  },
}))

vi.mock('@/Components/Global/FlashMessage.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/Global/AppFooter.vue', () => ({ default: { template: '<footer />' } }))

describe('FishAppLayout 導覽 props', () => {
  it('stickyMobile 與 showMobileTitle 預設為 true 並傳給 AppNavBar', () => {
    const wrapper = mount(FishAppLayout)
    const nav = wrapper.getComponent({ name: 'AppNavBar' })
    expect(nav.props('stickyMobile')).toBe(true)
    expect(nav.props('showMobileTitle')).toBe(true)
  })

  it('可覆寫並原樣傳給 AppNavBar', () => {
    const wrapper = mount(FishAppLayout, {
      props: { stickyMobile: false, showMobileTitle: false },
    })
    const nav = wrapper.getComponent({ name: 'AppNavBar' })
    expect(nav.props('stickyMobile')).toBe(false)
    expect(nav.props('showMobileTitle')).toBe(false)
  })
})

it('editor 且頁面有 fish 仍不注入編輯選單，保留返回導覽與固定留白', () => {
  const wrapper = mount(FishAppLayout, {
    props: { mobileBackUrl: '/fish/42', mobileBackText: '返回魚頁' },
    slots: { default: '<p>子頁內容</p>' },
  })
  expect(wrapper.props()).not.toHaveProperty('showEditMenu')
  expect(wrapper.element.children).toHaveLength(4)
  expect(wrapper.findAll('a, button, aside, nav')).toHaveLength(0)
  expect(wrapper.classes()).toContain('pb-6')
  expect(wrapper.get('main').text()).toBe('子頁內容')
  expect(wrapper.getComponent({ name: 'AppNavBar' }).props()).toMatchObject({
    mobileBackUrl: '/fish/42',
    mobileBackText: '返回魚頁',
  })
})
