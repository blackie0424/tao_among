import { describe, expect, it, vi } from "vitest"
import { mount } from "@vue/test-utils"
import Login from "@/Pages/Auth/Login.vue"

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
  })
})
