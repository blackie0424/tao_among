import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import EditorHome from '../Pages/EditorHome.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div><slot /></div>' },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: { get: vi.fn() },
}))

describe('EditorHome field survey technical validation entry', () => {
  it('在 PWA 工作區提供階段 0 驗證頁入口', () => {
    const wrapper = mount(EditorHome, {
      global: {
        stubs: {
          FishAppLayout: { template: '<main><slot /></main>' },
        },
      },
    })

    const link = wrapper.get('a[href="/workspace/field-survey/technical-validation"]')
    expect(link.text()).toContain('iPhone 錄音驗證')
  })
})
