import { describe, expect, it, vi } from "vitest"
import { mount } from "@vue/test-utils"
import ErrorPage from "@/Pages/Error.vue"

vi.mock("@/Layouts/FishAppLayout.vue", () => ({
  default: {
    name: "FishAppLayout",
    template: "<main><slot /></main>",
    props: ["pageTitle", "mobileBackUrl", "showHeader"],
  },
}))

const cases = [
  [403, "權限不足", "你目前的帳號權限不足，無法瀏覽此頁面。", "如需開通權限，請聯繫管理者。"],
  [404, "找不到頁面", "你要找的頁面不存在或已被移除。", "請確認網址是否正確，或回到首頁繼續瀏覽。"],
  [500, "系統發生錯誤", "系統暫時無法處理你的要求。", "請稍後再試，或回到首頁。"],
  [503, "服務暫時無法使用", "系統目前正在維護或暫時忙碌。", "請稍後再試，或回到首頁。"],
]

describe("Inertia 錯誤頁", () => {
  it.each(cases)("狀態碼 %i 顯示對應訊息", (status, title, description, guidance) => {
    const wrapper = mount(ErrorPage, { props: { status } })

    expect(wrapper.text()).toContain(title)
    expect(wrapper.text()).toContain(description)
    expect(wrapper.text()).toContain(guidance)
    expect(wrapper.get('a[href="/"]').text()).toBe("回首頁")
    expect(wrapper.getComponent({ name: "FishAppLayout" }).props()).toMatchObject({
      pageTitle: "無法瀏覽",
      mobileBackUrl: "/",
      showHeader: true,
    })
  })
})
