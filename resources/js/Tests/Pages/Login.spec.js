import { readFileSync } from 'node:fs'
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Login from '@/Pages/Auth/Login.vue'

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div />" },
  useForm: () => ({
    email: "",
    password: "",
    errors: {},
    processing: false,
    post: vi.fn(),
  }),
}))

describe("登入頁", () => {
  it("說明首次 LINE 登入會建立 guest 權限帳號", () => {
    const wrapper = mount(Login)

    expect(wrapper.text()).toContain("首次登入將自動建立帳號（guest 權限）")
    expect(wrapper.text()).not.toContain("首次登入將自動建立帳號（viewer 權限）")
    expect(wrapper.text()).toContain("請使用 LINE 登入")
    expect(wrapper.text()).not.toContain("田調人員請使用 LINE 登入")
  })

  it('兩個輸入框使用 16px 以上字級，避免 iOS Safari 聚焦時自動放大', () => {
    const wrapper = mount(Login)

    const inputs = wrapper.findAll('input')
    expect(inputs).toHaveLength(2)

    for (const input of inputs) {
      expect(input.classes()).toContain('text-elder-body')
    }
  })

  it('不再使用小於長者標準的字級 class', () => {
    const source = readFileSync('resources/js/Pages/Auth/Login.vue', 'utf8')

    expect(source).not.toMatch(/\btext-(?:xs|sm)\b/)
  })

  it('主要登入按鈕與回首頁連結具備足夠觸控高度', () => {
    const wrapper = mount(Login)

    expect(wrapper.get('a[href="/auth/line"]').classes()).toContain('min-h-touch-primary')
    expect(wrapper.get('button[type="submit"]').classes()).toContain('min-h-touch-primary')
    expect(wrapper.get('a[href="/"]').classes()).toContain('min-h-touch-secondary')
  })
})
