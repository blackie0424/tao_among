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
const makeViewerUser = () => ({ name: '使用者', role: 'viewer' })
const makeGuestUser = () => ({ name: '訪客', role: 'guest' })

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
      // 檢查不顯示帶有首頁圖示的連結 (v-if="!breadcrumbPage" 那段)
      const homeIconLinks = mobileBreadcrumb.findAll('a').filter((l) => 
        l.find('svg').exists() && l.text().includes('首頁')
      )
      expect(homeIconLinks.length).toBe(0)
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
        (l) => l.text() === 'among no tao' && l.attributes('href') === '/'
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
    it.each([
      ['guest', makeGuestUser(), '訪客'],
      ['viewer', makeViewerUser(), '使用者'],
      ['editor', makeEditorUser(), '田調人員'],
      ['admin', makeAdminUser(), '管理者'],
    ])('%s 顯示正確的角色 badge', (_role, user, label) => {
      const wrapper = mountNavBar({}, user)
      expect(wrapper.get('[data-testid="desktop-role-label"]').text()).toBe(label)
    })

    it('未知角色不顯示 badge', () => {
      const wrapper = mountNavBar({}, { name: '未知使用者', role: 'unknown' })
      expect(wrapper.find('[data-testid="desktop-role-label"]').exists()).toBe(false)
    })

    it('editor 的下拉按鈕內顯示「田調人員」badge 與名字', () => {
      const wrapper = mountNavBar({}, makeEditorUser())
      const desktopButtons = wrapper.findAll('button').filter((b) => b.text().includes('田調人員'))
      expect(desktopButtons.length).toBeGreaterThan(0)
      expect(desktopButtons[0].text()).toContain('田調員')
    })

    it('editor 點擊 badge 按鈕後顯示 UserMenuDropdown', async () => {
      const wrapper = mountNavBar({}, makeEditorUser())
      expect(wrapper.find('[data-testid="user-menu-dropdown"]').exists()).toBe(false)
      const btn = wrapper.findAll('button').find((b) => b.text().includes('田調人員'))
      await btn?.trigger('click')
      expect(wrapper.find('[data-testid="user-menu-dropdown"]').exists()).toBe(true)
    })

    it('未登入時 desktop 顯示登入連結', () => {
      mockUsePage.mockReturnValue({ props: { auth: { user: null }, fish: null } })
      const wrapper = mount(AppNavBar, { props: {} })
      const loginLinks = wrapper.findAll('a').filter((a) => a.text().includes('登入'))
      expect(loginLinks.length).toBeGreaterThan(0)
    })
  })

  describe('Desktop 首頁分隔符號', () => {
    it('未覆寫 desktop-nav slot 時只顯示一個固定分隔符號', () => {
      const wrapper = mountNavBar({})
      expect(wrapper.findAll('[data-testid="desktop-home-separator"]')).toHaveLength(1)
    })

    it('覆寫 desktop-nav slot 時仍顯示一個固定分隔符號', () => {
      mountNavBar({}, makeAdminUser())
      const customWrapper = mount(AppNavBar, {
        props: {},
        slots: { 'desktop-nav': '<span data-testid="custom-nav">自訂導覽</span>' },
      })
      expect(customWrapper.findAll('[data-testid="desktop-home-separator"]')).toHaveLength(1)
      expect(customWrapper.find('[data-testid="custom-nav"]').exists()).toBe(true)
    })
  })

  describe('Slots', () => {
    it('mobile-actions slot 正常渲染', () => {
      const wrapper = mountNavBar({}, makeAdminUser())
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
