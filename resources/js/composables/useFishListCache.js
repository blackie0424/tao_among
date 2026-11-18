/**
 * useFishListCache — 魚類清單 SessionStorage 快取 composable
 *
 * 職責（SRP）：
 *   - 將列表狀態（items / pageInfo / filters / nameQuery / scrollY）序列化至 sessionStorage
 *   - 還原時驗證 TTL 過期、篩選條件一致性與身分一致（不還原別人或舊身分的清單）
 *   - 處理快取失效（stale update、deleted、created）
 *
 * @param {import('vue').Ref} items          魚類清單 ref（來自 useFishList）
 * @param {import('vue').Ref} pageInfo       分頁資訊 ref（來自 useFishList）
 * @param {import('vue').Ref} currentFilters 篩選條件 ref（來自 Fishs.vue）
 * @param {import('vue').Ref} nameQuery      名稱關鍵字 ref（來自 Fishs.vue）
 * @param {() => Object} getPropsFilters     取得目前 URL 篩選條件的 getter function
 */

import { nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getFishCompact, getFishLatestAt } from '@/api/fishApi'
import {
  getStaleIds,
  clearStaleIds,
  getDeletedIds,
  clearDeletedIds,
  getCreatedIds,
  clearCreatedIds,
} from '@/utils/fishListCache'

const STORAGE_KEY = 'fishs_list_state'
const CACHE_TTL = 30 * 60 * 1000 // 30 分鐘
const SCROLL_RESTORE_DELAY_MS = 50
const GUEST_IDENTITY = 'guest'
// 還原時要與目前 URL 比對的篩選欄位（name 另以 nameQuery 比對）
const CACHE_FILTER_KEYS = [
  'tribe',
  'food_category',
  'processing_method',
  'capture_location',
  'without_audio',
]

/** 身分標記：未登入為 guest，登入者為「使用者 id:角色」 */
const identityOf = (user) => (user ? `${user.id}:${user.role}` : GUEST_IDENTITY)

export function useFishListCache(items, pageInfo, currentFilters, nameQuery, getPropsFilters) {
  const page = usePage()

  // 記下「這份清單建立時」的身分：返回上一頁時 Inertia 會先換成新頁面的 props，舊頁面才卸載並儲存，
  // 若儲存時才讀目前身分，會把舊身分的清單標成新身分
  const identity = identityOf(page.props.auth?.user)

  // --- 私有方法 ---

  const refreshStaleItems = async (staleIds) => {
    const freshDataList = await Promise.all(
      staleIds.map(async (id) => {
        try {
          return await getFishCompact(id)
        } catch (e) {
          return null
        }
      })
    )
    freshDataList.forEach((freshData) => {
      if (!freshData) return
      const index = items.value.findIndex((item) => item.id === freshData.id)
      if (index !== -1) items.value[index] = freshData
    })
  }

  const fetchAndPrependCreatedItems = async (createdIds) => {
    const newDataList = await Promise.all(
      createdIds.map(async (id) => {
        try {
          return await getFishCompact(id)
        } catch (e) {
          return null
        }
      })
    )
    const validNewItems = newDataList.filter((item) => item !== null).sort((a, b) => b.id - a.id)

    validNewItems.forEach((newItem) => {
      const exists = items.value.some((item) => item.id === newItem.id)
      if (!exists) items.value.unshift(newItem)
    })
  }

  // --- 公開方法 ---

  /** 將目前狀態序列化至 sessionStorage */
  const saveStateToStorage = () => {
    try {
      const state = {
        items: items.value,
        pageInfo: pageInfo.value,
        scrollY: window.scrollY,
        filters: currentFilters.value,
        nameQuery: nameQuery.value,
        identity,
        timestamp: Date.now(),
      }
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state))
    } catch (e) {
      // sessionStorage 不可用或容量已滿，忽略
    }
  }

  /** 清除 sessionStorage 快取 */
  const clearStateStorage = () => {
    try {
      sessionStorage.removeItem(STORAGE_KEY)
    } catch (e) {
      // 忽略
    }
  }

  /** 篩選條件與名稱關鍵字都和目前 URL 一致 */
  const matchesCurrentFilters = (state) => {
    const urlFilters = getPropsFilters() || {}
    const cachedFilters = state.filters || {}
    const filtersMatch = CACHE_FILTER_KEYS.every(
      (key) => (urlFilters[key] || '') === (cachedFilters[key] || '')
    )
    const nameMatch = (urlFilters.name || '') === (state.nameQuery || '')
    return filtersMatch && nameMatch
  }

  /**
   * 讀出身分相符且未過期的快取；身分不同（含舊版沒有身分標記的暫存）或過期就丟棄並回傳 null，
   * 避免看到別的身分的資料
   */
  const loadCachedState = () => {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null

    const state = JSON.parse(raw)
    const usable = state.identity === identity && Date.now() - state.timestamp <= CACHE_TTL
    if (usable) return state

    clearStateStorage()
    return null
  }

  const applyState = (state) => {
    items.value = state.items || []
    pageInfo.value = state.pageInfo || { hasMore: false, nextCursor: null }
    currentFilters.value = state.filters || currentFilters.value
    nameQuery.value = state.nameQuery || ''
  }

  /** 套用其他頁面留下的新增、刪除、更新記號，回傳是否改動了清單 */
  const applyPendingChanges = async () => {
    let changed = false

    const createdIds = getCreatedIds()
    if (createdIds.length > 0) {
      await fetchAndPrependCreatedItems(createdIds)
      clearCreatedIds()
      changed = true
    }

    const deletedIds = getDeletedIds()
    if (deletedIds.length > 0) {
      items.value = items.value.filter((item) => !deletedIds.includes(item.id))
      clearDeletedIds()
      changed = true
    }

    const staleIds = getStaleIds()
    if (staleIds.length > 0) {
      await refreshStaleItems(staleIds)
      clearStaleIds()
      changed = true
    }

    return changed
  }

  const restoreScrollPosition = (scrollY) => {
    nextTick(() => {
      setTimeout(() => {
        window.scrollTo(0, scrollY || 0)
      }, SCROLL_RESTORE_DELAY_MS)
    })
  }

  /**
   * 嘗試從 sessionStorage 還原狀態
   * @returns {Promise<boolean>} 是否成功還原
   */
  const restoreStateFromStorage = async () => {
    try {
      const state = loadCachedState()
      if (!state) return false

      // 後端有比快取更新的資料、或篩選條件與目前 URL 不一致：丟棄快取，重新取得
      const latestAt = await getFishLatestAt()
      const backendIsNewer = latestAt !== null && latestAt > state.timestamp
      if (backendIsNewer || !matchesCurrentFilters(state)) {
        clearStateStorage()
        return false
      }

      applyState(state)
      if (await applyPendingChanges()) saveStateToStorage()
      restoreScrollPosition(state.scrollY)

      return true
    } catch (e) {
      return false
    }
  }

  /**
   * 不依賴快取，直接對目前 items 處理 stale IDs（供快取未還原時呼叫）
   * @returns {Promise<boolean>} 是否有 stale IDs 被處理
   */
  const processStaleItems = async () => {
    const staleIds = getStaleIds()
    if (staleIds.length === 0) return false
    await refreshStaleItems(staleIds)
    clearStaleIds()
    return true
  }

  return {
    saveStateToStorage,
    clearStateStorage,
    restoreStateFromStorage,
    processStaleItems,
  }
}
