import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { ref } from 'vue'

// ─── Mock 相依模組 ─────────────────────────────────────────────────────────────
vi.mock('@/api/fishApi', () => ({
  getFishCompact: vi.fn(),
  getFishLatestAt: vi.fn(),
}))

// 目前登入身分：測試中可改 pageMock.props.auth.user
const pageMock = vi.hoisted(() => ({ props: { auth: { user: null } } }))
vi.mock('@inertiajs/vue3', () => ({ usePage: () => pageMock }))

vi.mock('@/utils/fishListCache', () => ({
  getStaleIds: vi.fn(() => []),
  clearStaleIds: vi.fn(),
  getDeletedIds: vi.fn(() => []),
  clearDeletedIds: vi.fn(),
  getCreatedIds: vi.fn(() => []),
  clearCreatedIds: vi.fn(),
}))

import { getFishCompact, getFishLatestAt } from '@/api/fishApi'
import {
  getStaleIds,
  clearStaleIds,
  getDeletedIds,
  clearDeletedIds,
  getCreatedIds,
  clearCreatedIds,
} from '@/utils/fishListCache'
import { useFishListCache } from '@/composables/useFishListCache'

// ─── sessionStorage mock ────────────────────────────────────────────────────
const storage = {}
const sessionStorageMock = {
  getItem: vi.fn((key) => storage[key] ?? null),
  setItem: vi.fn((key, val) => {
    storage[key] = val
  }),
  removeItem: vi.fn((key) => {
    delete storage[key]
  }),
}
Object.defineProperty(window, 'sessionStorage', { value: sessionStorageMock, writable: true })

// ─── window.scrollY / scrollTo ─────────────────────────────────────────────
vi.stubGlobal('scrollTo', vi.fn())
Object.defineProperty(window, 'scrollY', { value: 0, writable: true })

// ─── 工具函式 ──────────────────────────────────────────────────────────────────
const STORAGE_KEY = 'fishs_list_state'
const CACHE_TTL = 30 * 60 * 1000

const loginAs = (user) => {
  pageMock.props.auth = { user }
}
const EDITOR = { id: 7, role: 'editor' }
const OTHER_EDITOR = { id: 8, role: 'editor' }

const makeRefs = () => ({
  items: ref([{ id: 1, name: '飛魚' }]),
  pageInfo: ref({ hasMore: true, nextCursor: 1 }),
  currentFilters: ref({
    name: '',
    tribe: '',
    food_category: '',
    processing_method: '',
    capture_location: '',
    without_audio: '',
  }),
  nameQuery: ref(''),
})

const setCachedState = (overrides = {}) => {
  const state = {
    items: [{ id: 1, name: '飛魚' }],
    pageInfo: { hasMore: false, nextCursor: null },
    scrollY: 100,
    filters: {
      name: '',
      tribe: '',
      food_category: '',
      processing_method: '',
      capture_location: '',
      without_audio: '',
    },
    nameQuery: '',
    identity: 'guest',
    timestamp: Date.now(),
    ...overrides,
  }
  storage[STORAGE_KEY] = JSON.stringify(state)
}

beforeEach(() => {
  vi.clearAllMocks()
  Object.keys(storage).forEach((k) => delete storage[k])
  sessionStorageMock.getItem.mockImplementation((key) => storage[key] ?? null)
  sessionStorageMock.setItem.mockImplementation((key, val) => {
    storage[key] = val
  })
  sessionStorageMock.removeItem.mockImplementation((key) => {
    delete storage[key]
  })
  getStaleIds.mockReturnValue([])
  getDeletedIds.mockReturnValue([])
  getCreatedIds.mockReturnValue([])
  getFishLatestAt.mockResolvedValue(null)
  loginAs(null)
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('useFishListCache', () => {
  // ─── saveStateToStorage ───────────────────────────────────────────────────

  describe('saveStateToStorage', () => {
    it('將狀態序列化儲存至 sessionStorage', () => {
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { saveStateToStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      saveStateToStorage()

      expect(sessionStorageMock.setItem).toHaveBeenCalledWith(
        STORAGE_KEY,
        expect.stringContaining('"name":"飛魚"')
      )
    })

    it('儲存時包含 timestamp', () => {
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { saveStateToStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const before = Date.now()
      saveStateToStorage()
      const after = Date.now()

      const saved = JSON.parse(storage[STORAGE_KEY])
      expect(saved.timestamp).toBeGreaterThanOrEqual(before)
      expect(saved.timestamp).toBeLessThanOrEqual(after)
    })
  })

  // ─── clearStateStorage ────────────────────────────────────────────────────

  describe('clearStateStorage', () => {
    it('從 sessionStorage 移除快取', () => {
      setCachedState()
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { clearStateStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      clearStateStorage()

      expect(sessionStorageMock.removeItem).toHaveBeenCalledWith(STORAGE_KEY)
    })
  })

  // ─── restoreStateFromStorage ──────────────────────────────────────────────

  describe('restoreStateFromStorage', () => {
    it('無快取時回傳 false', async () => {
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(false)
    })

    it('快取已過期時移除並回傳 false', async () => {
      setCachedState({ timestamp: Date.now() - CACHE_TTL - 1000 })
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(false)
      expect(sessionStorageMock.removeItem).toHaveBeenCalledWith(STORAGE_KEY)
    })

    it('篩選條件不一致時移除並回傳 false', async () => {
      setCachedState({
        filters: {
          tribe: '阿美族',
          name: '',
          food_category: '',
          processing_method: '',
          capture_location: '',
          without_audio: '',
        },
      })
      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({ tribe: '泰雅族' }) // props 與快取不同
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(false)
      expect(sessionStorageMock.removeItem).toHaveBeenCalledWith(STORAGE_KEY)
    })

    it('成功還原時回傳 true 並設定 items/pageInfo', async () => {
      const cachedItems = [{ id: 2, name: '鯖魚' }]
      const cachedPageInfo = { hasMore: true, nextCursor: 2 }
      setCachedState({ items: cachedItems, pageInfo: cachedPageInfo })

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(true)
      expect(items.value).toEqual(cachedItems)
      expect(pageInfo.value).toEqual(cachedPageInfo)
    })

    it('快取含已刪除 ID 時從 items 移除', async () => {
      setCachedState({
        items: [
          { id: 1, name: '飛魚' },
          { id: 2, name: '鯖魚' },
        ],
      })
      getDeletedIds.mockReturnValue([2])

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      await restoreStateFromStorage()

      expect(items.value.some((i) => i.id === 2)).toBe(false)
      expect(clearDeletedIds).toHaveBeenCalled()
    })

    it('快取含 stale ID 時呼叫 getFishCompact 更新', async () => {
      setCachedState({ items: [{ id: 1, name: '飛魚（舊）' }] })
      getStaleIds.mockReturnValue([1])
      getFishCompact.mockResolvedValue({ id: 1, name: '飛魚（新）' })

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      await restoreStateFromStorage()

      expect(getFishCompact).toHaveBeenCalledWith(1)
      expect(items.value[0].name).toBe('飛魚（新）')
      expect(clearStaleIds).toHaveBeenCalled()
    })

    it('快取含 created ID 時插入到 items 開頭', async () => {
      setCachedState({ items: [{ id: 1, name: '飛魚' }] })
      getCreatedIds.mockReturnValue([99])
      getFishCompact.mockResolvedValue({ id: 99, name: '新增魚類' })

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      await restoreStateFromStorage()

      expect(items.value[0].id).toBe(99)
      expect(clearCreatedIds).toHaveBeenCalled()
    })

    it('後端 latest_at 比快取新時清除快取並回傳 false', async () => {
      const cacheTimestamp = Date.now() - 5000
      setCachedState({ timestamp: cacheTimestamp })
      getFishLatestAt.mockResolvedValue(cacheTimestamp + 1000) // 後端較新

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(false)
      expect(sessionStorageMock.removeItem).toHaveBeenCalledWith(STORAGE_KEY)
    })

    it('後端 latest_at 與快取相同時正常還原', async () => {
      const cacheTimestamp = Date.now()
      setCachedState({ timestamp: cacheTimestamp })
      getFishLatestAt.mockResolvedValue(cacheTimestamp)

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(true)
    })

    it('getFishLatestAt 失敗時仍正常還原快取（降級處理）', async () => {
      setCachedState()
      getFishLatestAt.mockResolvedValue(null)

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      const result = await restoreStateFromStorage()

      expect(result).toBe(true)
    })

    it('created ID 已存在於 items 時不重複插入', async () => {
      setCachedState({ items: [{ id: 1, name: '飛魚' }] })
      getCreatedIds.mockReturnValue([1]) // 已存在
      getFishCompact.mockResolvedValue({ id: 1, name: '飛魚' })

      const { items, pageInfo, currentFilters, nameQuery } = makeRefs()
      const { restoreStateFromStorage } = useFishListCache(
        items,
        pageInfo,
        currentFilters,
        nameQuery,
        () => ({})
      )

      await restoreStateFromStorage()

      expect(items.value.filter((i) => i.id === 1)).toHaveLength(1)
    })
  })

  // ─── 身分標記：不同身分不互相還原 ─────────────────────────────────────────

  describe('身分標記', () => {
    const mount = (items = ref([{ id: 1, name: '飛魚' }])) => {
      const refs = { ...makeRefs(), items }
      const api = useFishListCache(
        refs.items,
        refs.pageInfo,
        refs.currentFilters,
        refs.nameQuery,
        () => ({})
      )
      return { ...api, items: refs.items }
    }

    it('儲存時記下身分：未登入為 guest、登入者為 id 加角色', () => {
      mount().saveStateToStorage()
      expect(JSON.parse(storage[STORAGE_KEY]).identity).toBe('guest')

      loginAs(EDITOR)
      mount().saveStateToStorage()
      expect(JSON.parse(storage[STORAGE_KEY]).identity).toBe('7:editor')
    })

    it('記的是清單建立時的身分：離開頁面時 page 已換成新身分，也不會把舊清單標成新身分', () => {
      const guestList = mount() // 以訪客身分建立（清單內容是訪客的）
      loginAs(EDITOR) // 返回時 Inertia 先換了 page props，舊頁面才卸載、儲存

      guestList.saveStateToStorage()

      expect(JSON.parse(storage[STORAGE_KEY]).identity).toBe('guest')
    })

    it('同一個身分可還原', async () => {
      loginAs(EDITOR)
      mount().saveStateToStorage()

      const { restoreStateFromStorage, items } = mount(ref([]))
      expect(await restoreStateFromStorage()).toBe(true)
      expect(items.value).toHaveLength(1)
    })

    it('訪客存的暫存，editor 不還原且清掉（畫面清單不被覆寫）', async () => {
      mount().saveStateToStorage() // guest

      loginAs(EDITOR)
      const { restoreStateFromStorage, items } = mount(ref([{ id: 99, name: '伺服器給的魚' }]))

      expect(await restoreStateFromStorage()).toBe(false)
      expect(items.value).toEqual([{ id: 99, name: '伺服器給的魚' }])
      expect(storage[STORAGE_KEY]).toBeUndefined()
    })

    it('訪客→editor→訪客→同一個 editor：每次換身分都不還原別人的暫存', async () => {
      mount().saveStateToStorage() // 訪客存

      loginAs(EDITOR)
      expect(await mount().restoreStateFromStorage()).toBe(false) // editor 取不到訪客的
      mount().saveStateToStorage() // editor 存

      loginAs(null)
      expect(await mount().restoreStateFromStorage()).toBe(false) // 訪客取不到 editor 的
      mount().saveStateToStorage() // 訪客存

      loginAs(EDITOR)
      expect(await mount().restoreStateFromStorage()).toBe(false) // 同一個 editor 也取不到訪客的
    })

    it('同帳號角色改變時不還原', async () => {
      loginAs({ id: 7, role: 'viewer' })
      mount().saveStateToStorage()

      loginAs({ id: 7, role: 'editor' })
      expect(await mount().restoreStateFromStorage()).toBe(false)
      expect(storage[STORAGE_KEY]).toBeUndefined()
    })

    it('不同帳號同角色時不還原', async () => {
      loginAs(EDITOR)
      mount().saveStateToStorage()

      loginAs(OTHER_EDITOR)
      expect(await mount().restoreStateFromStorage()).toBe(false)
    })

    it('舊版沒有身分標記的暫存一律丟棄', async () => {
      setCachedState({ identity: undefined })

      expect(await mount().restoreStateFromStorage()).toBe(false)
      expect(storage[STORAGE_KEY]).toBeUndefined()
    })
  })
})
