import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const pageMock = vi.hoisted(() => ({
  props: { auth: { user: null }, flash: {}, storageFolders: { image: 'images', webp: 'webp' } },
}))
const routerGet = vi.hoisted(() => vi.fn())

vi.mock('@inertiajs/vue3', () => ({
  router: { get: routerGet },
  usePage: () => pageMock,
  Head: { name: 'Head', render: () => null },
  Link: { name: 'Link', template: '<a><slot /></a>' },
}))

vi.mock('@/api/fishApi', () => ({
  getFishCompact: vi.fn(),
  getFishLatestAt: vi.fn().mockResolvedValue(null),
}))

vi.mock('../utils/AnimationOptimizer.js', () => ({
  default: {
    optimizationLevel: 'none',
    animationConfig: { enableTransitions: true, enableAnimations: true },
    shouldEnableAnimation: vi.fn(() => true),
    getOptimalAnimationConfig: vi.fn(() => ({ enableTransitions: true, enableAnimations: true })),
    init: vi.fn(),
  },
}))

import Fishs from '../Pages/Fishs.vue'

const VIEWER = { id: 5, role: 'viewer' }
const OTHER_VIEWER = { id: 6, role: 'viewer' }
const OPTIONS = [
  { key: 'night', label: '晚上釣魚會遇到的魚', hint: '有人記錄在晚上用釣的方式捕到', count: 12 },
  { key: 'same_name', label: '名字一樣、長得不一樣的魚', hint: '', count: 3 },
  { key: 'alpha', label: '依字母順序看', hint: '', count: 60 },
  { key: 'all', label: '全部都看（不分類）', hint: '', count: 60 },
]
const ALL_ITEMS = [{ id: 1, name: 'rahet', image_url: 'a' }]
const NIGHT_ITEMS = [{ id: 2, name: 'mamacik-fish', image_url: 'b' }]
const PAGE_INFO = { hasMore: false, nextCursor: null }
const ASKED_KEY = 'fish_view_asked:5'

const showPage = async (user, extraProps = {}) => {
  pageMock.props.auth = { user }
  const wrapper = mount(Fishs, {
    attachTo: document.body,
    props: {
      items: ALL_ITEMS,
      pageInfo: PAGE_INFO,
      view: 'all',
      viewOptions: OPTIONS,
      ...extraProps,
    },
  })
  await flushPromises()
  return wrapper
}

// 模擬伺服器回應：呼叫 router.get 的 onSuccess，帶入指定看法的 props
const respond = (callIndex, props) => {
  const options = routerGet.mock.calls[callIndex][2]
  options.onSuccess({ props })
}

const find = (id) => document.body.querySelector(`[data-testid="${id}"]`)

describe('清單頁看法：小視窗、橫條、右下角鈕', () => {
  beforeEach(() => {
    sessionStorage.clear()
    localStorage.clear()
    document.body.innerHTML = ''
    routerGet.mockReset()
    pageMock.props.auth = { user: null }
    // @ts-ignore
    global.IntersectionObserver = class {
      observe() {}
      disconnect() {}
      unobserve() {}
    }
    vi.stubGlobal('scrollTo', vi.fn())
  })

  describe('小視窗與記住（驗收 5）', () => {
    it('已開通帳號第一次進來自動出現，選項依序列出且按鈕高度至少 56px', async () => {
      await showPage(VIEWER)

      expect(find('view-picker')).not.toBeNull()
      const rows = [...document.body.querySelectorAll('[data-testid^="view-option-"]')].map(
        (button) => ({
          label: button.querySelector('.text-elder-name').textContent.trim(),
          count: button.lastElementChild.textContent.trim(),
        })
      )
      expect(rows).toEqual([
        { label: '晚上釣魚會遇到的魚', count: '12 種' },
        { label: '名字一樣、長得不一樣的魚', count: '3 組' },
        { label: '依字母順序看', count: '60 種' },
        { label: '全部都看（不分類）', count: '60 種' },
      ])
      expect(find('view-option-night').textContent).toContain('有人記錄在晚上用釣的方式捕到')
      expect(find('view-option-night').className).toContain('min-h-touch-primary')
    })

    it('選任一項後記下已問過；再次進來不自動出現，清單仍是全部', async () => {
      const first = await showPage(VIEWER)
      find('view-option-all').click()
      await flushPromises()
      expect(localStorage.getItem(ASKED_KEY)).toBe('1')
      first.unmount()
      document.body.innerHTML = ''

      await showPage(VIEWER)

      expect(find('view-picker')).toBeNull()
      expect(document.body.textContent).toContain('rahet')
      expect(find('view-bar')).toBeNull()
    })

    it('關閉視窗也算問過', async () => {
      await showPage(VIEWER)
      find('view-picker-close').click()
      await flushPromises()

      expect(find('view-picker')).toBeNull()
      expect(localStorage.getItem(ASKED_KEY)).toBe('1')
    })

    it('記錄以帳號區分：同一個瀏覽器換帳號會再問一次', async () => {
      const first = await showPage(VIEWER)
      find('view-picker-close').click()
      await flushPromises()
      first.unmount()
      document.body.innerHTML = ''

      await showPage(OTHER_VIEWER)

      expect(find('view-picker')).not.toBeNull()
    })

    it('瀏覽器不能存 localStorage 時每次都跳出，不報錯', async () => {
      const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('denied')
      })
      const first = await showPage(VIEWER)
      find('view-picker-close').click()
      await flushPromises()
      first.unmount()
      document.body.innerHTML = ''

      await showPage(VIEWER)

      expect(find('view-picker')).not.toBeNull()
      setItem.mockRestore()
    })

    it('訪客與沒有選項的回應：沒有小視窗、沒有右下角換看法鈕', async () => {
      await showPage(null, { viewOptions: null })
      expect(find('view-picker')).toBeNull()
      expect(find('floating-view')).toBeNull()
    })
  })

  describe('橫條與右下角鈕（驗收 6）', () => {
    it('選了晚上：送出只帶 view 的請求，橫條顯示標題與數字，並有換一個與看全部', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()

      const params = routerGet.mock.calls[0][1]
      expect(params.view).toBe('night')
      expect(params.name).toBeUndefined()
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await flushPromises()

      expect(find('view-bar').textContent).toContain('正在看')
      expect(find('view-bar').textContent).toContain('晚上釣魚會遇到的魚（12）')
      expect(find('view-bar-change')).not.toBeNull()
      expect(find('view-bar-all')).not.toBeNull()
      expect(document.body.textContent).toContain('mamacik-fish')
    })

    it('選全部都看：不用重取，不顯示橫條', async () => {
      await showPage(VIEWER)
      find('view-option-all').click()
      await flushPromises()

      expect(routerGet).not.toHaveBeenCalled()
      expect(find('view-bar')).toBeNull()
    })

    it('右下角換看法鈕是純圖示、有給螢幕閱讀器的名稱，點了重開小視窗', async () => {
      await showPage(VIEWER)
      find('view-picker-close').click()
      await flushPromises()

      const button = find('floating-view')
      expect(button.getAttribute('aria-label')).toBe('換看法')
      expect(button.textContent.trim()).toBe('')
      expect(button.querySelector('svg')).not.toBeNull()

      button.click()
      await flushPromises()
      expect(find('view-picker')).not.toBeNull()
    })

    it('橫條的看全部：回到全部並重新取得清單', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await flushPromises()

      find('view-bar-all').click()
      await flushPromises()

      expect(routerGet.mock.calls[1][1].view).toBeUndefined()
    })
  })

  describe('分段標題與搜尋互斥', () => {
    it('字母順序：段標題是字母與後端給的魚數；名字一樣：段標題是名字與條數', async () => {
      await showPage(VIEWER)
      find('view-option-alpha').click()
      await flushPromises()
      respond(0, {
        items: [
          { id: 3, name: 'ant', image_url: 'a', group_label: 'A' },
          { id: 4, name: 'Bee', image_url: 'b', group_label: 'B' },
          { id: 5, name: '9x', image_url: 'c', group_label: '其他' },
        ],
        pageInfo: PAGE_INFO,
        view: 'alpha',
        viewOptions: OPTIONS,
        viewGroupCounts: [
          { label: 'A', count: 7 },
          { label: 'B', count: 2 },
          { label: '其他', count: 1 },
        ],
      })
      await flushPromises()

      const headings = [
        ...document.body.querySelectorAll('[data-testid="fish-section-title"]'),
      ].map((h) => h.textContent.replace(/\s+/g, ''))
      expect(headings).toEqual(['A（7）', 'B（2）', '其他（1）'])

      find('view-bar-change').click()
      await flushPromises()
      find('view-option-same_name').click()
      await flushPromises()
      respond(1, {
        items: [
          { id: 6, name: 'rahet', image_url: 'a', group_label: 'rahet', group_count: 2 },
          { id: 7, name: 'Rahet ', image_url: 'b', group_label: 'rahet', group_count: 2 },
        ],
        pageInfo: PAGE_INFO,
        view: 'same_name',
        viewOptions: OPTIONS,
      })
      await flushPromises()

      const sameNameHeadings = [
        ...document.body.querySelectorAll('[data-testid="fish-section-title"]'),
      ].map((h) => h.textContent.replace(/\s+/g, ''))
      expect(sameNameHeadings).toEqual(['rahet（2）'])
    })

    it('選看法時清掉搜尋條件，請求只帶看法', async () => {
      await showPage(VIEWER, { filters: { name: 'bee' } })
      find('view-option-night').click()
      await flushPromises()

      expect(routerGet.mock.calls[0][1]).toEqual(expect.objectContaining({ view: 'night' }))
      expect(routerGet.mock.calls[0][1].name).toBeUndefined()
    })
  })

  describe('快取與切換（驗收 7）', () => {
    it('看法不是全部時離開清單頁，不留下快取', async () => {
      const page = await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await flushPromises()

      page.unmount()

      expect(sessionStorage.getItem('fishs_list_state')).toBeNull()
    })

    it('看法是全部時離開清單頁，維持現有快取行為', async () => {
      const page = await showPage(VIEWER)
      find('view-picker-close').click()
      await flushPromises()

      page.unmount()

      expect(JSON.parse(sessionStorage.getItem('fishs_list_state')).items[0].name).toBe('rahet')
    })

    it('載入中連續切換：以最後一次為準，較早的回應被丟棄', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      find('floating-view').click()
      await flushPromises()
      find('view-option-alpha').click()
      await flushPromises()

      expect(routerGet.mock.calls.map((call) => call[1].view)).toEqual(['night', 'alpha'])

      // 晚上的回應較晚才回來：不能蓋掉字母順序
      respond(1, {
        items: [{ id: 3, name: 'ant', image_url: 'a', group_label: 'A' }],
        pageInfo: PAGE_INFO,
        view: 'alpha',
        viewOptions: OPTIONS,
        viewGroupCounts: [{ label: 'A', count: 1 }],
      })
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await flushPromises()

      expect(document.body.textContent).toContain('ant')
      expect(document.body.textContent).not.toContain('mamacik-fish')
    })
  })

  describe('失敗與 0 筆（驗收 8）', () => {
    it('選了看法後清單是 0 條：顯示這一組目前沒有魚與看全部，不顯示找不到符合的魚', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      respond(0, {
        items: [],
        pageInfo: PAGE_INFO,
        view: 'night',
        viewOptions: OPTIONS.filter((option) => option.key !== 'night'),
      })
      await flushPromises()

      expect(find('view-empty').textContent).toContain('這一組目前沒有魚')
      expect(find('view-empty-all')).not.toBeNull()
      expect(find('fish-list-empty')).toBeNull()
    })

    it('選了看法後載入失敗：顯示錯誤與重試、看全部，重試會再送同一個看法', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      routerGet.mock.calls[0][2].onError({})
      await flushPromises()

      expect(find('view-error').textContent).toContain('載入失敗')
      expect(find('view-error-all')).not.toBeNull()

      find('view-error-retry').click()
      await flushPromises()
      expect(routerGet.mock.calls[1][1].view).toBe('night')
    })

    it('先成功、切換看法後第一頁選項計算失敗：不顯示舊數字，右下角換看法鈕消失', async () => {
      const wrapper = await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      // 真實的 Inertia 回應會同時更新頁面 props；第一頁選項計算失敗時 viewOptions 為 null
      const failedProps = {
        items: NIGHT_ITEMS,
        pageInfo: PAGE_INFO,
        view: 'night',
        viewOptions: null,
      }
      respond(0, failedProps)
      await wrapper.setProps(failedProps)
      await flushPromises()

      expect(find('view-bar').textContent).toContain('晚上釣魚會遇到的魚')
      expect(find('view-bar').textContent).not.toContain('（12）')
      expect(find('floating-view')).toBeNull()
      expect(find('view-bar-change')).toBeNull()
      expect(document.body.textContent).toContain('mamacik-fish')

      // 沒有小視窗可用時，仍能用「看全部」回去
      find('view-bar-all').click()
      await flushPromises()
      expect(routerGet.mock.calls[1][1].view).toBeUndefined()
    })

    it('往下載入的回應沒有選項欄位：保留原本的選項，不當作失敗', async () => {
      const wrapper = await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await wrapper.setProps({ view: 'night', items: NIGHT_ITEMS, viewOptions: OPTIONS })
      await flushPromises()

      // 往下載入的回應：沒有 viewOptions 欄位（Inertia 以新的 props 取代，欄位消失）
      await wrapper.setProps({ viewOptions: undefined })
      await flushPromises()

      expect(find('floating-view')).not.toBeNull()
      expect(find('view-bar').textContent).toContain('（12）')
    })

    it('網路中斷或伺服器錯誤（沒有成功也沒有 onError）：解除載入、顯示錯誤與重試、看全部', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()

      routerGet.mock.calls[0][2].onFinish({ cancelled: false, interrupted: false })
      await flushPromises()

      expect(find('view-error').textContent).toContain('載入失敗')
      expect(find('view-error-retry')).not.toBeNull()
      expect(find('view-error-all')).not.toBeNull()
    })

    it('請求被新的選擇取代（interrupted）或已成功處理：onFinish 不算失敗', async () => {
      await showPage(VIEWER)
      find('view-option-night').click()
      await flushPromises()
      routerGet.mock.calls[0][2].onFinish({ cancelled: false, interrupted: true })
      await flushPromises()
      expect(find('view-error')).toBeNull()

      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      routerGet.mock.calls[0][2].onFinish({ cancelled: false, interrupted: false })
      await flushPromises()
      expect(find('view-error')).toBeNull()
    })

    it('從詳細頁返回、歷史還原的是舊看法資料：重取第一頁，換成新的資料', async () => {
      await showPage(VIEWER, {
        view: 'night',
        items: [{ id: 99, name: 'stale-night-fish', image_url: 'x' }],
      })

      expect(routerGet).toHaveBeenCalledTimes(1)
      expect(routerGet.mock.calls[0][1].view).toBe('night')
      respond(0, { items: NIGHT_ITEMS, pageInfo: PAGE_INFO, view: 'night', viewOptions: OPTIONS })
      await flushPromises()

      expect(document.body.textContent).toContain('mamacik-fish')
      expect(document.body.textContent).not.toContain('stale-night-fish')
    })

    it('計算選項失敗（沒有選項）時清單照常顯示', async () => {
      await showPage(VIEWER, { viewOptions: null })

      expect(document.body.textContent).toContain('rahet')
      expect(find('view-picker')).toBeNull()
      expect(find('floating-view')).toBeNull()
    })
  })
})
