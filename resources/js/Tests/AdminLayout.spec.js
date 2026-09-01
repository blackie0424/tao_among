/**
 * AdminLayout Tests
 *
 * Tests the AdminLayout component including:
 * - Global error display from page.props.errors.error
 * - Error message visibility
 * - Error message content
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminLayout from '../Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    template: '<a><slot /></a>',
    props: ['href', 'method', 'as'],
  },
  usePage: vi.fn(() => ({
    props: {
      auth: { user: { name: 'Test Admin' } },
      errors: {},
    },
    url: '/admin',
  })),
}))

describe('AdminLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('不顯示錯誤訊息當 errors.error 不存在', async () => {
    const { usePage } = await import('@inertiajs/vue3')
    usePage.mockReturnValue({
      props: {
        auth: { user: { name: 'Test Admin' } },
        errors: {},
      },
      url: '/admin',
    })

    const wrapper = mount(AdminLayout, {
      props: {
        title: 'Test Page',
      },
      global: {
        components: { Link },
        stubs: {
          Link: true,
        },
      },
    })

    // 不應該顯示錯誤區塊
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('顯示全域錯誤訊息當 errors.error 存在', async () => {
    const { usePage } = await import('@inertiajs/vue3')
    usePage.mockReturnValue({
      props: {
        auth: { user: { name: 'Test Admin' } },
        errors: {
          error: '檔案上傳失敗,請稍後再試',
        },
      },
      url: '/admin',
    })

    const wrapper = mount(AdminLayout, {
      props: {
        title: 'Test Page',
      },
      global: {
        components: { Link },
        stubs: {
          Link: true,
        },
      },
    })

    // 應該顯示錯誤區塊
    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)

    // 應該包含錯誤訊息
    expect(alert.text()).toContain('發生錯誤')
    expect(alert.text()).toContain('檔案上傳失敗,請稍後再試')
  })

  it('錯誤區塊有正確的樣式', async () => {
    const { usePage } = await import('@inertiajs/vue3')
    usePage.mockReturnValue({
      props: {
        auth: { user: { name: 'Test Admin' } },
        errors: {
          error: '測試錯誤',
        },
      },
      url: '/admin',
    })

    const wrapper = mount(AdminLayout, {
      props: {
        title: 'Test Page',
      },
      global: {
        components: { Link },
        stubs: {
          Link: true,
        },
      },
    })

    const alert = wrapper.find('[role="alert"]')
    expect(alert.classes()).toContain('bg-red-50')
    expect(alert.classes()).toContain('border-red-200')
    expect(alert.classes()).toContain('text-red-800')
  })

  it('處理錯誤物件並提取訊息', async () => {
    const { usePage } = await import('@inertiajs/vue3')
    usePage.mockReturnValue({
      props: {
        auth: { user: { name: 'Test Admin' } },
        errors: {
          error: { message: '這是錯誤物件的訊息' },
        },
      },
      url: '/admin',
    })

    const wrapper = mount(AdminLayout, {
      props: {
        title: 'Test Page',
      },
      global: {
        components: { Link },
        stubs: {
          Link: true,
        },
      },
    })

    const alert = wrapper.find('[role="alert"]')
    expect(alert.exists()).toBe(true)
    expect(alert.text()).toContain('這是錯誤物件的訊息')
  })
})
