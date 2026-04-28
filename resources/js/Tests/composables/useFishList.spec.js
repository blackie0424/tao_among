import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref } from 'vue'

// ─── Mock @inertiajs/vue3 router ──────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
  router: {
    get: vi.fn(),
  },
}))

import { router } from '@inertiajs/vue3'
import { useFishList } from '@/composables/useFishList'

// ─── Mock window.history & URL ────────────────────────────────────────────────
beforeEach(() => {
  vi.stubGlobal('location', { href: 'http://localhost/fishs' })
  vi.spyOn(window.history, 'replaceState').mockImplementation(() => {})
  // 每次測試前重置 router.get 為乾淨的 vi.fn()
  router.get.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

// ─── 工具：模擬 router.get 成功回應 ─────────────────────────────────────────
const mockRouterSuccess = (items = [], pageInfo = { hasMore: false, nextCursor: null }) => {
  router.get.mockImplementationOnce((_url, _params, callbacks) => {
    callbacks.onSuccess({ props: { items, pageInfo } })
  })
}

const mockRouterError = () => {
  router.get.mockImplementationOnce((_url, _params, callbacks) => {
    callbacks.onError({ message: 'INVALID_CURSOR' })
  })
}

// ─── 建立預設 refs ────────────────────────────────────────────────────────────
const makeRefs = (filterOverrides = {}) => ({
  currentFilters: ref({
    name: '',
    tribe: '',
    food_category: '',
    processing_method: '',
    capture_location: '',
    without_audio: '',
    ...filterOverrides,
  }),
  nameQuery: ref(''),
})

describe('useFishList', () => {
  // ─── fetchPage：首次請求（無 last_id）──────────────────────────────────────

  describe('fetchPage 首次請求', () => {
    it('成功後以新資料取代 items', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { items, fetchPage } = useFishList(currentFilters, nameQuery)

      const mockItems = [
        { id: 1, name: '飛魚' },
        { id: 2, name: '鯖魚' },
      ]
      mockRouterSuccess(mockItems)
      fetchPage({})

      expect(items.value).toEqual(mockItems)
    })

    it('成功後更新 pageInfo', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { pageInfo, fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterSuccess([], { hasMore: true, nextCursor: 42 })
      fetchPage({})

      expect(pageInfo.value).toEqual({ hasMore: true, nextCursor: 42 })
    })

    it('成功後 isLoading 恢復為 false', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { isLoading, fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterSuccess()
      fetchPage({})

      expect(isLoading.value).toBe(false)
    })
  })

  // ─── fetchPage：分頁追加（含 last_id）────────────────────────────────────

  describe('fetchPage 分頁追加', () => {
    it('有 last_id 時追加 items 而非取代', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { items, fetchPage } = useFishList(currentFilters, nameQuery)

      // 第一頁
      mockRouterSuccess([{ id: 1, name: '飛魚' }], { hasMore: true, nextCursor: 1 })
      fetchPage({})

      // 第二頁（模擬 hasMore + nextCursor）
      mockRouterSuccess([{ id: 2, name: '鯖魚' }], { hasMore: false, nextCursor: null })
      fetchPage({ last_id: 1 })

      expect(items.value).toHaveLength(2)
      expect(items.value[0].id).toBe(1)
      expect(items.value[1].id).toBe(2)
    })
  })

  // ─── fetchPage：錯誤處理 ──────────────────────────────────────────────────

  describe('fetchPage 錯誤處理', () => {
    it('API 錯誤時設定 showCursorError 為 true', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { showCursorError, fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterError()
      fetchPage({})

      expect(showCursorError.value).toBe(true)
    })

    it('showCursorError 為 true 時不發送新請求', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterError()
      fetchPage({}) // 觸發錯誤
      router.get.mockClear()

      fetchPage({}) // 應被跳過

      expect(router.get).not.toHaveBeenCalled()
    })
  })

  // ─── performSearch ────────────────────────────────────────────────────────

  describe('performSearch', () => {
    it('重置 pageInfo 並發送首頁請求', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { pageInfo, performSearch } = useFishList(currentFilters, nameQuery)

      // 假設已有分頁狀態
      pageInfo.value = { hasMore: true, nextCursor: 99 }

      mockRouterSuccess([], { hasMore: false, nextCursor: null })
      performSearch()

      // 驗證 router.get 被呼叫，且不含 last_id
      const [, params] = router.get.mock.calls[0]
      expect(params.last_id).toBeUndefined()
    })

    it('若傳入 onBeforeSearch callback 則呼叫它', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { performSearch } = useFishList(currentFilters, nameQuery)

      const onBeforeSearch = vi.fn()
      mockRouterSuccess()
      performSearch(onBeforeSearch)

      expect(onBeforeSearch).toHaveBeenCalledOnce()
    })

    it('重置 showCursorError 為 false', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { showCursorError, performSearch } = useFishList(currentFilters, nameQuery)

      showCursorError.value = true
      mockRouterSuccess()
      performSearch()

      expect(showCursorError.value).toBe(false)
    })
  })

  // ─── retryFromStart ───────────────────────────────────────────────────────

  describe('retryFromStart', () => {
    it('清除游標錯誤後重新從第一頁抓取', () => {
      const { currentFilters, nameQuery } = makeRefs()
      const { showCursorError, retryFromStart } = useFishList(currentFilters, nameQuery)

      // 直接設定 showCursorError=true 模擬游標錯誤狀態
      showCursorError.value = true

      mockRouterSuccess()
      retryFromStart()

      expect(showCursorError.value).toBe(false)
      expect(router.get).toHaveBeenCalled()
    })
  })

  // ─── buildQueryParams（間接測試） ─────────────────────────────────────────

  describe('query 參數建構', () => {
    it('空白值不會出現在 query 參數中', () => {
      const { currentFilters, nameQuery } = makeRefs({ tribe: '', food_category: '' })
      const { fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterSuccess()
      fetchPage({})

      const [, params] = router.get.mock.calls[0]
      expect(params.tribe).toBeUndefined()
      expect(params.food_category).toBeUndefined()
    })

    it('nameQuery 優先合併進 name 參數', () => {
      const { currentFilters, nameQuery } = makeRefs()
      nameQuery.value = '飛魚'
      const { fetchPage } = useFishList(currentFilters, nameQuery)

      mockRouterSuccess()
      fetchPage({})

      const [, params] = router.get.mock.calls[0]
      expect(params.name).toBe('飛魚')
    })
  })
})
