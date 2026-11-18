import { readFileSync } from 'node:fs'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, reactive } from 'vue'
import Login from '@/Pages/Auth/Login.vue'

const postSpy = vi.hoisted(() => vi.fn())
const formHolder = vi.hoisted(() => ({ current: null }))

vi.mock("@inertiajs/vue3", () => ({
  Head: { template: "<div />" },
  useForm: () => formHolder.current,
}))

const LOGIN_SOURCE = 'resources/js/Pages/Auth/Login.vue'

const mountLogin = (errors = {}) => {
  formHolder.current = reactive({
    email: '',
    password: '',
    errors,
    processing: false,
    post: postSpy,
  })
  return mount(Login, { attachTo: document.body })
}

const tabs = (wrapper) => wrapper.findAll('[role="tab"]')
const lineTab = (wrapper) => tabs(wrapper)[0]
const adminTab = (wrapper) => tabs(wrapper)[1]

describe("登入頁", () => {
  beforeEach(() => {
    postSpy.mockClear()
    document.body.innerHTML = ''
    window.history.replaceState(null, "", "/login")
  })

  describe("預設顯示族人登入分頁", () => {
    it("看得到 LINE 登入按鈕與提示文字，看不到管理員欄位與送出鈕", () => {
      const wrapper = mountLogin()

      expect(wrapper.get('a[href="/auth/line"]').isVisible()).toBe(true)
      expect(wrapper.text()).toContain("請使用 LINE 登入")
      expect(wrapper.get('input[name="email"]').isVisible()).toBe(false)
      expect(wrapper.get('input[name="password"]').isVisible()).toBe(false)
      expect(wrapper.get('button[type="submit"]').isVisible()).toBe(false)
    })

    it("分頁列有兩個分頁：族人登入（選中）、管理員登入", () => {
      const wrapper = mountLogin()

      expect(tabs(wrapper)).toHaveLength(2)
      expect(lineTab(wrapper).text()).toBe("族人登入")
      expect(adminTab(wrapper).text()).toBe("管理員登入")
      expect(lineTab(wrapper).attributes('aria-selected')).toBe('true')
      expect(adminTab(wrapper).attributes('aria-selected')).toBe('false')
    })
  })

  describe("切換到管理員登入分頁", () => {
    it("顯示 Email、密碼與送出鈕，LINE 按鈕不可見；切回來恢復", async () => {
      const wrapper = mountLogin()

      await adminTab(wrapper).trigger('click')
      expect(wrapper.get('input[name="email"]').isVisible()).toBe(true)
      expect(wrapper.get('input[name="password"]').isVisible()).toBe(true)
      expect(wrapper.get('button[type="submit"]').isVisible()).toBe(true)
      expect(wrapper.get('button[type="submit"]').text()).toBe("管理員登入")
      expect(wrapper.get('a[href="/auth/line"]').isVisible()).toBe(false)

      await lineTab(wrapper).trigger('click')
      expect(wrapper.get('a[href="/auth/line"]').isVisible()).toBe(true)
      expect(wrapper.get('input[name="email"]').isVisible()).toBe(false)
    })

    it("切換分頁不清空已輸入的 Email 與密碼", async () => {
      const wrapper = mountLogin()

      await adminTab(wrapper).trigger('click')
      await wrapper.get('input[name="email"]').setValue('admin@example.com')
      await wrapper.get('input[name="password"]').setValue('secret-pass')

      await lineTab(wrapper).trigger('click')
      await adminTab(wrapper).trigger('click')

      expect(wrapper.get('input[name="email"]').element.value).toBe('admin@example.com')
      expect(wrapper.get('input[name="password"]').element.value).toBe('secret-pass')
    })

    it("tab 與 tabpanel 以 aria-controls／aria-labelledby 互相指向", () => {
      const wrapper = mountLogin()
      const panels = wrapper.findAll('[role="tabpanel"]')

      expect(wrapper.find('[role="tablist"]').exists()).toBe(true)
      expect(panels).toHaveLength(2)
      tabs(wrapper).forEach((tab, i) => {
        expect(tab.attributes('aria-controls')).toBe(panels[i].attributes('id'))
        expect(panels[i].attributes('aria-labelledby')).toBe(tab.attributes('id'))
      })
    })
  })

  describe("分頁的鍵盤操作", () => {
    it("只有選中的 tab 在 Tab 鍵順序內", async () => {
      const wrapper = mountLogin()

      expect(lineTab(wrapper).attributes('tabindex')).toBe('0')
      expect(adminTab(wrapper).attributes('tabindex')).toBe('-1')

      await adminTab(wrapper).trigger('click')
      expect(lineTab(wrapper).attributes('tabindex')).toBe('-1')
      expect(adminTab(wrapper).attributes('tabindex')).toBe('0')
    })

    it("右方向鍵選取下一個 tab 且焦點跟著移動；左方向鍵回來", async () => {
      const wrapper = mountLogin()
      lineTab(wrapper).element.focus()

      await lineTab(wrapper).trigger('keydown', { key: 'ArrowRight' })
      expect(adminTab(wrapper).attributes('aria-selected')).toBe('true')
      expect(document.activeElement).toBe(adminTab(wrapper).element)

      await adminTab(wrapper).trigger('keydown', { key: 'ArrowLeft' })
      expect(lineTab(wrapper).attributes('aria-selected')).toBe('true')
      expect(document.activeElement).toBe(lineTab(wrapper).element)
    })

    it("Home 選第一個、End 選最後一個", async () => {
      const wrapper = mountLogin()

      await lineTab(wrapper).trigger('keydown', { key: 'End' })
      expect(adminTab(wrapper).attributes('aria-selected')).toBe('true')

      await adminTab(wrapper).trigger('keydown', { key: 'Home' })
      expect(lineTab(wrapper).attributes('aria-selected')).toBe('true')
    })
  })

  describe("管理員登入失敗", () => {
    it("進頁面時就有錯誤：選中管理員分頁並顯示後端原文", () => {
      const wrapper = mountLogin({ email: ['這組帳號密碼無法登入。'] })

      expect(adminTab(wrapper).attributes('aria-selected')).toBe('true')
      expect(wrapper.get('input[name="email"]').isVisible()).toBe(true)
      expect(wrapper.text()).toContain('這組帳號密碼無法登入。')
    })

    it("後端實際回的訊息陣列只顯示第一則原文，不出現方括號與引號", () => {
      const wrapper = mountLogin({ email: ['這組帳號密碼無法登入。', '另一則'] })

      expect(wrapper.get('[data-testid="login-error"]').text()).toBe('這組帳號密碼無法登入。')
    })

    it("沒有錯誤時不顯示錯誤區塊", () => {
      const wrapper = mountLogin()

      expect(wrapper.find('[data-testid="login-error"]').exists()).toBe(false)
    })

    it("送出後才收到錯誤，使用者已切到族人分頁：切回管理員分頁並顯示原文", async () => {
      const wrapper = mountLogin()

      await adminTab(wrapper).trigger('click')
      await wrapper.get('form').trigger('submit.prevent')
      await lineTab(wrapper).trigger('click')
      expect(lineTab(wrapper).attributes('aria-selected')).toBe('true')

      formHolder.current.errors = { email: ['這組帳號密碼無法登入。'] }
      await nextTick()

      expect(adminTab(wrapper).attributes('aria-selected')).toBe('true')
      expect(wrapper.get('[data-testid="login-error"]').text()).toBe('這組帳號密碼無法登入。')
      expect(wrapper.get('input[name="email"]').isVisible()).toBe(true)
    })
  })

  describe("文字與頁尾", () => {
    it("品牌名為連到首頁的宋體連結，副標與提示文案正確", () => {
      const wrapper = mountLogin()
      const brand = wrapper.get('a[href="/"]')

      expect(brand.text()).toBe("among no tao")
      expect(brand.classes()).toContain('font-serif')
      expect(wrapper.text()).toContain("請先登入，讓我們認識你")
      expect(wrapper.text()).toContain("第一次登入，管理員確認後，就可以觀看詳細資料。")
    })

    it("舊文案不再出現", () => {
      const wrapper = mountLogin()

      expect(wrapper.text()).not.toContain("guest")
      expect(wrapper.text()).not.toContain("田野調查魚類資料系統")
      expect(wrapper.text()).not.toContain("回首頁")
    })

    it("頁面底部有頁尾", () => {
      const wrapper = mountLogin()

      expect(wrapper.get('footer').text()).toContain("網站資料皆為私人所有，不同意任何授權使用。")
    })
  })

  describe("樣式符合博物誌規範", () => {
    it("不使用預設灰／藍／陰影，也不使用小於長者標準的字級", () => {
      const source = readFileSync(LOGIN_SOURCE, 'utf8')

      expect(source).not.toMatch(/\b(?:bg|text|border|ring)-(?:gray|blue)-\d+/)
      expect(source).not.toMatch(/\bshadow(?:-\w+)?\b/)
      expect(source).not.toMatch(/\btext-(?:xs|sm)\b/)
    })

    it("使用紙底、主色與細線配色", () => {
      const wrapper = mountLogin()
      const html = wrapper.html()

      expect(html).toContain('bg-paper')
      expect(html).toContain('bg-paper-raised')
      expect(html).toContain('bg-brand')
      expect(html).toContain('border-rule-strong')
    })

    it("LINE 按鈕保留品牌綠 #06c755", () => {
      const wrapper = mountLogin()

      expect(wrapper.get('a[href="/auth/line"]').attributes('style')).toContain('rgb(6, 199, 85)')
    })

    it("觸控高度：分頁 48px、主要按鈕 56px；輸入框 18px 字級", () => {
      const wrapper = mountLogin()

      expect(tabs(wrapper)).toHaveLength(2)
      tabs(wrapper).forEach((tab) => expect(tab.classes()).toContain('min-h-touch-secondary'))
      expect(wrapper.get('a[href="/auth/line"]').classes()).toContain('min-h-touch-primary')
      expect(wrapper.get('button[type="submit"]').classes()).toContain('min-h-touch-primary')

      const inputs = wrapper.findAll('input')
      expect(inputs).toHaveLength(2)
      inputs.forEach((input) => expect(input.classes()).toContain('text-elder-body'))
    })
  })

  describe("redirect 行為不變", () => {
    it('網址帶 redirect 時，LINE 登入連結與表單都帶著它', async () => {
      window.history.replaceState(null, "", "/login?redirect=%2Ffish%2F5")
      const wrapper = mountLogin()

      expect(wrapper.get('a[href^="/auth/line"]').attributes('href')).toBe('/auth/line?redirect=%2Ffish%2F5')

      await wrapper.get('form').trigger('submit.prevent')
      expect(postSpy).toHaveBeenCalledWith('/login?redirect=%2Ffish%2F5')
    })

    it('網址沒有 redirect 時，LINE 登入連結與表單都不帶 redirect', async () => {
      const wrapper = mountLogin()

      expect(wrapper.get('a[href^="/auth/line"]').attributes('href')).toBe('/auth/line')

      await wrapper.get('form').trigger('submit.prevent')
      expect(postSpy).toHaveBeenCalledWith('/login')
    })
  })
})
