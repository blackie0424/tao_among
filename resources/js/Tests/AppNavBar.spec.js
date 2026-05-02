import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AppNavBar from '@/Components/Global/AppNavBar.vue'

const mockUsePage = vi.fn()

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    template: '<a :href="href" :data-method="method"><slot /></a>',
    props: ['href', 'method', 'as'],
  },
  usePage: () => mockUsePage(),
}))

vi.mock('@/Components/Global/UserMenuDropdown.vue', () => ({
  default: {
    template: '<div data-testid="user-menu-dropdown" />',
    props: ['user', 'showUserInfo'],
    emits: ['close'],
  },
}))

const makeAdminUser = () => ({ name: '管理員', role: 'admin' })
const makeEditorUser = () => ({ name: '田調員', role: 'editor' })

const mountNavBar = (props = {}, user = makeAdminUser()) => {
  mockUsePage.mockReturnValue({
    props: {
      auth: { user },
      fish: { id: 1, name: '飛魚' },
    },
  })
  return mount(AppNavBar, { props })
}

describe('AppNavBar', () => {
  beforeEach(() => {
    mockUsePage.mockReturnValue({
      props: { auth: { user: makeAdminUser() }, fish: null },
    })
  })

  describe('Mobile 麵包屑', () => {
    it('顯示 pageTitle', () => {
      const wrapper = mountNavBar({ pageTitle: '捕獲紀錄' })
      expect(wrapper.text()).toContain('捕獲紀錄')
    })

    it('breadcrumbPage 為空時，顯示首頁連結', () => {
      const wrapper = mountNavBar({ breadcrumbPage: '' })
      const links = wrapper.findAll('a')
      const homeLink = links.find((l) => l.text().includes('首頁'))
      expect(homeLink).toBeTruthy()
    })

    it('breadcrumbPage 有值時，mobile 不顯示首頁連結', () => {
      const wrapper = mountNavBar({ breadcrumbPage: '捕獲紀錄' })
      const mobileBreadcrumb = wrapper.find('[data-testid="mobile-breadcrumb"]')
      const homeLink = mobileBreadcrumb.findAll('a').find((l) => l.text().includes('首頁'))
      expect(homeLink).toBeFalsy()
    })

    it('mobileBackUrl 非 "/" 時，顯示上層連結', () => {
      const wrapper = mountNavBar({ mobileBackUrl: '/fishs', mobileBackText: 'among no tao' })
      expect(wrapper.text()).toContain('among no tao')
    })

    it('mobileBackUrl 為 "/" 時，不顯示上層連結', () => {
      const wrapper = mountNavBar({ mobileBackUrl: '/', mobileBackText: 'among no tao' })
      // 只有 desktop 的 among no tao 連結，mobile 不顯示
      const allLinks = wrapper.findAll('a')
      const backLinks = allLinks.filter(
        (l) => l.text() === 'among no tao' && l.attributes('href') === '/',
      )
      expect(backLinks.length).toBe(0)
    })
  })

  describe('手機版使用者選單', () => {
    it('已登入時顯示 avatar 按鈕', () => {
      const wrapper = mountNavBar({}, makeAdminUser())
      // avatar 按鈕為圓形藍色 button
      const btn = wrapper.find('button.rounded-full')
      expect(btn.exists()).toBe(true)
    })

    it('未登入時顯示登入連結', () => {
      mockUsePage.mockReturnValue({ props: { auth: { user: null }, fish: null } })
      const wrapper = mount(AppNavBar, { props: {} })
      expect(wrapper.text()).toContain('登入')
    })

    it('點擊 avatar 後顯示 UserMenuDropdown', async () => {
      const wrapper = mountNavBar({})
      expect(wrapper.find('[data-testid="user-menu-dropdown"]').exists()).toBe(false)
      await wrapper.find('button.rounded-full').trigger('click')
      expect(wrapper.find('[data-testid="user-menu-dropdown"]').exists()).toBe(true)
    })
  })

  describe('Desktop 使用者區域', () => {
    it('管理員顯示含名字的下拉按鈕', () => {
      const wrapper = mountNavBar({}, makeAdminUser())
      expect(wrapper.text()).toContain('管理員')
    })

    it('非管理員顯示田調人員徽章', () => {
      const wrapper = mountNavBar({}, makeEditorUser())
      expect(wrapper.text()).toContain('田調人員')
    })

    it('非管理員顯示登出按鈕', () => {
      const wrapper = mountNavBar({}, makeEditorUser())
      const logoutBtn = wrapper.findAll('a').find((a) => a.text().includes('登出'))
      expect(logoutBtn).toBeTruthy()
    })

    it('未登入時 desktop 顯示登入連結', () => {
      mockUsePage.mockReturnValue({ props: { auth: { user: null }, fish: null } })
      const wrapper = mount(AppNavBar, { props: {} })
      const loginLinks = wrapper.findAll('a').filter((a) => a.text().includes('登入'))
      expect(loginLinks.length).toBeGreaterThan(0)
    })
  })

  describe('Slots', () => {
    it('mobile-actions slot 正常渲染', () => {
      const wrapper = mountNavBar(
        {},
        makeAdminUser(),
      )
      // 重新掛載並帶入 slot
      mockUsePage.mockReturnValue({
        props: { auth: { user: makeAdminUser() }, fish: null },
      })
      const w = mount(AppNavBar, {
        props: {},
        slots: { 'mobile-actions': '<div data-testid="mobile-action">搜尋</div>' },
      })
      expect(w.find('[data-testid="mobile-action"]').exists()).toBe(true)
    })

    it('header-extension slot 正常渲染', () => {
      mockUsePage.mockReturnValue({
        props: { auth: { user: makeAdminUser() }, fish: null },
      })
      const wrapper = mount(AppNavBar, {
        props: {},
        slots: { 'header-extension': '<div data-testid="ext">延伸內容</div>' },
      })
      expect(wrapper.find('[data-testid="ext"]').exists()).toBe(true)
    })

    it('desktop-nav slot 可覆蓋預設麵包屑', () => {
      mockUsePage.mockReturnValue({
        props: { auth: { user: makeAdminUser() }, fish: { id: 1, name: '飛魚' } },
      })
      const wrapper = mount(AppNavBar, {
        props: {},
        slots: { 'desktop-nav': '<span data-testid="custom-nav">自訂導覽</span>' },
      })
      expect(wrapper.find('[data-testid="custom-nav"]').exists()).toBe(true)
    })
  })
})
