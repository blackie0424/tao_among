import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
  usePage: () => ({ props: { auth: { user: null }, fish: null } }),
}))

vi.mock('@/Components/Global/AppNavBar.vue', () => ({
  default: {
    name: 'AppNavBar',
    template: '<header><slot /><slot name="mobile-actions" /><slot name="desktop-nav" /><slot name="header-extension" /></header>',
    props: ['pageTitle', 'breadcrumbPage', 'mobileBackUrl', 'mobileBackText', 'stickyMobile', 'showMobileTitle'],
  },
}))

vi.mock('@/Components/Global/FlashMessage.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/Global/AppFooter.vue', () => ({ default: { template: '<footer />' } }))
vi.mock('@/Components/Global/AdminFloatingMenu.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/Global/BottomNavBar.vue', () => ({ default: { template: '<div />' } }))

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
