import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import UserMenuDropdown from '@/Components/Global/UserMenuDropdown.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    template: '<a :href="href" :data-method="method"><slot /></a>',
    props: ['href', 'method', 'as'],
  },
}))

const makeAdmin = () => ({ name: '管理員A', role: 'admin' })
const makeUser = () => ({ name: '田調員B', role: 'user' })

const mountDropdown = (propsData = {}) =>
  mount(UserMenuDropdown, {
    props: {
      user: makeAdmin(),
      showUserInfo: false,
      ...propsData,
    },
  })

// ────────────────────────────────────────────────────
// 管理員選單項目
// ────────────────────────────────────────────────────
describe('管理員選單項目', () => {
  it('管理員：應顯示統計面板連結', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    expect(wrapper.text()).toContain('統計面板')
  })

  it('管理員：應顯示使用者管理連結', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    expect(wrapper.text()).toContain('使用者管理')
  })

  it('管理員：應顯示登出按鈕', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    expect(wrapper.text()).toContain('登出')
  })
})

// ────────────────────────────────────────────────────
// 非管理員選單項目
// ────────────────────────────────────────────────────
describe('非管理員選單項目', () => {
  it('非管理員：不應顯示統計面板連結', () => {
    const wrapper = mountDropdown({ user: makeUser() })
    expect(wrapper.text()).not.toContain('統計面板')
  })

  it('非管理員：不應顯示使用者管理連結', () => {
    const wrapper = mountDropdown({ user: makeUser() })
    expect(wrapper.text()).not.toContain('使用者管理')
  })

  it('非管理員：應顯示登出按鈕', () => {
    const wrapper = mountDropdown({ user: makeUser() })
    expect(wrapper.text()).toContain('登出')
  })
})

// ────────────────────────────────────────────────────
// 使用者資訊標頭
// ────────────────────────────────────────────────────
describe('使用者資訊標頭', () => {
  it('showUserInfo=true：應顯示使用者名稱', () => {
    const wrapper = mountDropdown({ user: makeUser(), showUserInfo: true })
    expect(wrapper.text()).toContain('田調員B')
  })

  it('showUserInfo=false：不應顯示使用者名稱', () => {
    const wrapper = mountDropdown({ user: makeAdmin(), showUserInfo: false })
    const header = wrapper.find('[data-testid="user-info-header"]')
    expect(header.exists()).toBe(false)
  })
})

// ────────────────────────────────────────────────────
// 關閉行為
// ────────────────────────────────────────────────────
describe('關閉行為', () => {
  it('點擊 backdrop 應觸發 close 事件', async () => {
    const wrapper = mountDropdown()
    await wrapper.find('[data-testid="dropdown-backdrop"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('點擊統計面板連結應觸發 close 事件', async () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    await wrapper.find('[data-testid="link-dashboard"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('點擊使用者管理連結應觸發 close 事件', async () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    await wrapper.find('[data-testid="link-line-users"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
