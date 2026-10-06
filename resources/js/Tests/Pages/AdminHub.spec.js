import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminHub from '@/Pages/Admin/Hub.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
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

  it('在文獻管理後顯示主題導覽入口與項目總數', () => {
    const wrapper = mount(AdminHub, {
      props: {
        stats: { fishCount: 0, audioCoverage: 0, pendingAudio: 0, monthlyNew: 0, topicItemCount: 7 },
        pendingUsers: 0,
      },
    })

    const links = wrapper.findAll('a')
    const topicLink = links.find(link => link.attributes('href') === '/admin/topics')

    expect(topicLink).toBeTruthy()
    expect(topicLink.text()).toContain('主題導覽')
    expect(topicLink.text()).toContain('管理四個主題分類與項目內容')
    expect(topicLink.text()).toContain('7')
    expect(links.findIndex(link => link.attributes('href') === '/admin/topics'))
      .toBeLessThan(links.findIndex(link => link.attributes('href') === '/fish-report'))
  })
})
