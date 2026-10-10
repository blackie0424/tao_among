import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import UserMenuDropdown from '@/Components/Global/UserMenuDropdown.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: {
    template: '<a :href="href" :data-method="method" @click.prevent><slot /></a>',
    props: ['href', 'method', 'as'],
  },
}))

const makeAdmin = () => ({ name: '管理員A', role: 'admin' })
const makeEditor = () => ({ name: '田調員C', role: 'editor' })
const makeUser = () => ({ name: '使用者B', role: 'viewer' })

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
  it('管理員：應顯示系統管理後台連結', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    expect(wrapper.text()).toContain('系統管理後台')
  })

  it('管理員：後台連結應指向 /admin', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    const link = wrapper.find('[data-testid="link-admin-hub"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/admin')
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
  it('非管理員：不應顯示系統管理後台連結', () => {
    const wrapper = mountDropdown({ user: makeUser() })
    expect(wrapper.text()).not.toContain('系統管理後台')
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
    expect(wrapper.text()).toContain('使用者B')
  })

  it('showUserInfo=false：不應顯示使用者名稱', () => {
    const wrapper = mountDropdown({ user: makeAdmin(), showUserInfo: false })
    const header = wrapper.find('[data-testid="user-info-header"]')
    expect(header.exists()).toBe(false)
  })

  it.each([
    ['guest', '訪客'],
    ['viewer', '使用者'],
    ['editor', '田調人員'],
    ['admin', '管理者'],
  ])('%s 顯示正確的角色名稱', (role, label) => {
    const wrapper = mountDropdown({ user: { name: 'Test', role }, showUserInfo: true })
    expect(wrapper.get('[data-testid="user-role-label"]').text()).toBe(label)
  })

  it('未知角色不顯示角色文字', () => {
    const wrapper = mountDropdown({ user: { name: 'Test', role: 'unknown' }, showUserInfo: true })
    expect(wrapper.find('[data-testid="user-role-label"]').exists()).toBe(false)
  })
})

describe('共用可用性', () => {
  it('使用長者字級與足夠寬度，不含 text-xs/text-sm', () => {
    const wrapper = mountDropdown({ user: makeEditor(), showUserInfo: true })
    expect(wrapper.html()).not.toMatch(/text-(xs|sm)/)
    expect(wrapper.find('.w-64').exists()).toBe(true)
  })

  it.each(['guest', 'viewer', 'editor', 'admin'])('%s 都有至少 56px 的登出入口', (role) => {
    const wrapper = mountDropdown({ user: { name: role, role }, showUserInfo: true })
    const logout = wrapper.findAll('a').find((link) => link.text() === '登出')
    expect(logout).toBeTruthy()
    expect(logout.classes()).toContain('min-h-touch-primary')
  })
})

// ────────────────────────────────────────────────────
// 田調工作區 / 魚種連結（editor & admin）
// ────────────────────────────────────────────────────
describe('各角色選單項目', () => {
  it('admin：應顯示田調工作區連結，指向 /workspace', () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    const link = wrapper.find('[data-testid="link-workspace"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/workspace')
  })

  it('editor：應顯示田調工作區連結，指向 /workspace', () => {
    const wrapper = mountDropdown({ user: makeEditor() })
    const link = wrapper.find('[data-testid="link-workspace"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toBe('/workspace')
  })

  it.each([
    ['viewer', makeUser(), ['登出']],
    ['editor', makeEditor(), ['田調工作區', '登出']],
    ['admin', makeAdmin(), ['田調工作區', '系統管理後台', '登出']],
  ])('%s 只顯示角色可用項目且沒有新增魚種', (_role, user, expectedItems) => {
    const wrapper = mountDropdown({ user })
    const itemTexts = wrapper.findAll('a').map((link) => link.text().trim())
    expect(itemTexts).toEqual(expectedItems)
    expect(wrapper.find('[data-testid="link-batch-create"]').exists()).toBe(false)
  })

  it('一般使用者：不應顯示田調工作區連結', () => {
    const wrapper = mountDropdown({ user: makeUser() })
    expect(wrapper.find('[data-testid="link-workspace"]').exists()).toBe(false)
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

  it('點擊系統管理後台連結應觸發 close 事件', async () => {
    const wrapper = mountDropdown({ user: makeAdmin() })
    await wrapper.find('[data-testid="link-admin-hub"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
