import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import LineUsers from '@/Pages/LineUsers.vue'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  Link: { template: '<a><slot /></a>', props: ['href', 'method'] },
  usePage: () => ({ props: { auth: { user: { name: '管理員' } } } }),
}))

// Mock axios
vi.mock('axios', () => ({
  default: {
    put: vi.fn().mockResolvedValue({ data: { role: 'editor' } }),
  },
}))

const makeUsers = (count = 2) =>
  Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    line_user_id: `U${i + 1}`,
    name: `使用者 ${i + 1}`,
    picture_url: null,
    role: 'viewer',
    created_at: '2026-01-01T00:00:00.000Z',
  }))

describe('LineUsers', () => {
  it('renders user list with display name and role', () => {
    const wrapper = mount(LineUsers, {
      props: {
        lineUsers: {
          data: makeUsers(3),
          current_page: 1,
          last_page: 1,
        },
      },
    })

    expect(wrapper.text()).toContain('使用者 1')
    expect(wrapper.text()).toContain('使用者 2')
    expect(wrapper.text()).toContain('使用者 3')
    expect(wrapper.findAll('select').length).toBe(3)
  })

  it('shows empty state when no users', () => {
    const wrapper = mount(LineUsers, {
      props: {
        lineUsers: {
          data: [],
          current_page: 1,
          last_page: 1,
        },
      },
    })

    expect(wrapper.text()).toContain('尚未有 LINE 使用者資料')
  })

  it('role dropdown triggers axios put on change', async () => {
    const axios = (await import('axios')).default
    const wrapper = mount(LineUsers, {
      props: {
        lineUsers: {
          data: makeUsers(1),
          current_page: 1,
          last_page: 1,
        },
      },
    })

    const select = wrapper.find('select')
    await select.setValue('editor')
    await select.trigger('change')

    expect(axios.put).toHaveBeenCalledWith('/line-users/1/role', { role: 'editor' })
  })

  it('does not show pagination when only one page', () => {
    const wrapper = mount(LineUsers, {
      props: {
        lineUsers: {
          data: makeUsers(2),
          current_page: 1,
          last_page: 1,
        },
      },
    })

    // pagination 區塊不存在
    const links = wrapper.findAll('a')
    const pageLinks = links.filter((l) => /page=/.test(l.attributes('href') || ''))
    expect(pageLinks.length).toBe(0)
  })
})
