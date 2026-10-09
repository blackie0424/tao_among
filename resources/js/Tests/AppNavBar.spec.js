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
    template: '<div data-testid="user-menu-dropdown">{{ user.role }}</div>',
    props: ['user', 'showUserInfo'],
    emits: ['close'],
  },
}))

const makeAdminUser = () => ({ name: '管理員', role: 'admin' })
const makeEditorUser = () => ({ name: '田調員', role: 'editor' })

const mountNavBar = (props = {}, user = makeAdminUser(), url = '/') => {
  mockUsePage.mockReturnValue({
    url,
    props: { auth: { user }, fish: { id: 1, name: '飛魚' } },
  })
  return mount(AppNavBar, { props })
}

describe('AppNavBar', () => {
  beforeEach(() => {
    mockUsePage.mockReturnValue({ url: '/', props: { auth: { user: makeAdminUser() } } })
  })

  describe('手機返回列', () => {
    it('有上層頁面時顯示正確返回按鈕與標題', () => {
      const wrapper = mountNavBar({
        pageTitle: '飛魚',
        mobileBackUrl: '/fishs',
        mobileBackText: '魚類列表',
      })
      const back = wrapper.get('[data-testid="nav-back-button"]')
      expect(back.attributes('href')).toBe('/fishs')
      expect(back.text()).toContain('魚類列表')
      expect(wrapper.get('[data-testid="nav-mobile-title"]').text()).toBe('飛魚')
      expect(wrapper.find('[data-testid="mobile-breadcrumb"]').exists()).toBe(false)
    })

    it('返回首頁時顯示品牌、不顯示返回按鈕', () => {
      const wrapper = mountNavBar({ mobileBackUrl: '/' })
      expect(wrapper.get('[data-testid="nav-brand"]').attributes('href')).toBe('/')
      expect(wrapper.get('[data-testid="nav-brand"]').text()).toContain('among no tao')
      expect(wrapper.find('[data-testid="nav-back-button"]').exists()).toBe(false)
    })

    it('showMobileTitle=false 時不顯示 pageTitle', () => {
      const wrapper = mountNavBar({
        pageTitle: '不應顯示的魚名',
        mobileBackUrl: '/fishs',
        mobileBackText: '魚類列表',
        showMobileTitle: false,
      })
      expect(wrapper.find('[data-testid="nav-mobile-title"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('不應顯示的魚名')
    })

    it('stickyMobile=false 時手機為 relative、桌機仍 sticky', () => {
      const wrapper = mountNavBar({ stickyMobile: false })
      expect(wrapper.get('header').classes()).toEqual(
        expect.arrayContaining(['relative', 'lg:sticky', 'lg:top-4'])
      )
      expect(wrapper.get('header').classes()).not.toContain('top-4')
    })
  })

  describe('使用者選單', () => {
    it('手機頭像按鈕會開啟含使用者資訊的選單', async () => {
      const wrapper = mountNavBar({}, makeAdminUser())
      await wrapper.get('[data-testid="nav-user-button-mobile"]').trigger('click')
      const dropdown = wrapper.getComponent({ name: 'UserMenuDropdown' })
      expect(dropdown.props('showUserInfo')).toBe(true)
    })

    it.each([
      ['admin', makeAdminUser()],
      ['editor', makeEditorUser()],
    ])('桌機 %s 頭像會開啟選單且不顯示舊 badge/姓名', async (_role, user) => {
      const wrapper = mountNavBar({}, user)
      expect(wrapper.find('[data-testid="desktop-role-label"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain(user.name)
      await wrapper.get('[data-testid="nav-user-button-desktop"]').trigger('click')
      expect(wrapper.find('[data-testid="user-menu-dropdown"]').exists()).toBe(true)
    })

    it('未登入時手機與桌機都有登入入口', () => {
      const wrapper = mountNavBar({}, null)
      expect(wrapper.get('[data-testid="nav-login-mobile"]')).toBeTruthy()
      expect(wrapper.get('[data-testid="nav-login-desktop"]')).toBeTruthy()
    })
  })

  describe('桌機主導覽', () => {
    it('只有首頁與 among no tao 兩個主要連結，且文字不截斷', () => {
      const wrapper = mountNavBar()
      const nav = wrapper.get('nav[aria-label="主要導覽"]')
      const links = nav.findAll('a')

      expect(links.map((link) => [link.text(), link.attributes('href')])).toEqual([
        ['首頁', '/'],
        ['among no tao', '/fishs'],
      ])
      expect(links.every((link) => !link.classes().includes('truncate'))).toBe(true)
    })

    it.each([
      ['/', 'desktop-nav-home'],
      ['/fishs', 'desktop-nav-fishs'],
      ['/fish/1', 'desktop-nav-fishs'],
      ['/search', 'desktop-nav-fishs'],
      ['/workspace', null],
    ])('%s 的高亮符合所屬導覽', (url, activeTestId) => {
      const wrapper = mountNavBar({}, makeAdminUser(), url)
      const home = wrapper.get('[data-testid="desktop-nav-home"]')
      const fishs = wrapper.get('[data-testid="desktop-nav-fishs"]')

      expect(home.attributes('aria-current')).toBe(
        activeTestId === 'desktop-nav-home' ? 'page' : undefined
      )
      expect(fishs.attributes('aria-current')).toBe(
        activeTestId === 'desktop-nav-fishs' ? 'page' : undefined
      )
    })

    it.each(['/', '/fishs'])('返回目標為 %s 時不顯示桌機返回按鈕', (mobileBackUrl) => {
      const wrapper = mountNavBar({ mobileBackUrl })
      expect(wrapper.find('[data-testid="nav-back-button-desktop"]').exists()).toBe(false)
    })

    it('返回目標為魚頁時保留桌機返回按鈕', () => {
      const wrapper = mountNavBar({ mobileBackUrl: '/fish/1', mobileBackText: '飛魚' })
      expect(wrapper.get('[data-testid="nav-back-button-desktop"]').attributes('href')).toBe(
        '/fish/1'
      )
    })
  })

  describe('Slots', () => {
    it('mobile-actions slot 在單列導覽正常渲染', () => {
      const wrapper = mountNavBar({}, makeAdminUser())
      const withSlot = mount(AppNavBar, {
        slots: { 'mobile-actions': '<button data-testid="mobile-action">搜尋</button>' },
      })
      expect(wrapper.exists()).toBe(true)
      expect(withSlot.find('[data-testid="mobile-action"]').exists()).toBe(true)
    })

    it('header-extension slot 正常渲染', () => {
      const wrapper = mount(AppNavBar, {
        slots: { 'header-extension': '<div data-testid="ext">延伸內容</div>' },
      })
      expect(wrapper.find('[data-testid="ext"]').exists()).toBe(true)
    })

    it('desktop-nav slot 可覆蓋預設返回按鈕', () => {
      const wrapper = mount(AppNavBar, {
        props: { mobileBackUrl: '/fishs' },
        slots: { 'desktop-nav': '<span data-testid="custom-nav">自訂導覽</span>' },
      })
      expect(wrapper.find('[data-testid="custom-nav"]').exists()).toBe(true)
      expect(wrapper.find('[data-testid="nav-back-button-desktop"]').exists()).toBe(false)
    })
  })
})
