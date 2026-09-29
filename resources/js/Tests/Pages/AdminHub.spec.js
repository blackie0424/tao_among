import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminHub from '@/Pages/Admin/Hub.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  Link: { template: '<a><slot /></a>', props: ['href'] },
}))

vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: { template: '<main><slot /></main>', props: ['title'] },
}))

describe('後台控制台', () => {
  it('使用者管理說明涵蓋所有角色', () => {
    const wrapper = mount(AdminHub, {
      props: {
        stats: { fishCount: 0, captureRecordCount: 0, referenceCount: 0 },
        pendingUsers: 0,
      },
    })

    expect(wrapper.text()).toContain('管理訪客、使用者、田調人員與管理者角色')
  })
})
