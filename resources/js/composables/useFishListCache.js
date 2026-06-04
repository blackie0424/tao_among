/**
 * useFishListCache — 魚類清單 SessionStorage 快取 composable
 *
 * 職責（SRP）：
 *   - 將列表狀態（items / pageInfo / filters / nameQuery / scrollY）序列化至 sessionStorage
 *   - 還原時驗證 TTL 過期與篩選條件一致性
 *   - 處理快取失效（stale update、deleted、created）
 *
 * @param {import('vue').Ref} items          魚類清單 ref（來自 useFishList）
 * @param {import('vue').Ref} pageInfo       分頁資訊 ref（來自 useFishList）
 * @param {import('vue').Ref} currentFilters 篩選條件 ref（來自 Fishs.vue）
 * @param {import('vue').Ref} nameQuery      名稱關鍵字 ref（來自 Fishs.vue）
 * @param {() => Object} getPropsFilters     取得目前 URL 篩選條件的 getter function
 */

import { nextTick } from 'vue'
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

export function useFishListCache(items, pageInfo, currentFilters, nameQuery, getPropsFilters) {
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

  /**
   * 嘗試從 sessionStorage 還原狀態
   * @returns {Promise<boolean>} 是否成功還原
   */
  const restoreStateFromStorage = async () => {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY)
      if (!raw) return false

      const state = JSON.parse(raw)
      if (Date.now() - state.timestamp > CACHE_TTL) {
        sessionStorage.removeItem(STORAGE_KEY)
        return false
      }

      // 比對後端最新 updated_at，若有比快取更新的資料則強制重取
      const latestAt = await getFishLatestAt()
      if (latestAt !== null && latestAt > state.timestamp) {
        sessionStorage.removeItem(STORAGE_KEY)
        return false
      }

      // 驗證篩選條件與目前 URL 一致
      const urlFilters = getPropsFilters() || {}
      const cachedFilters = state.filters || {}
      const filterKeys = [
        'tribe',
        'food_category',
        'processing_method',
        'capture_location',
        'without_audio',
      ]
      const filtersMatch = filterKeys.every(
        (key) => (urlFilters[key] || '') === (cachedFilters[key] || '')
      )
      const nameMatch = (urlFilters.name || '') === (state.nameQuery || '')

      if (!filtersMatch || !nameMatch) {
        sessionStorage.removeItem(STORAGE_KEY)
        return false
      }

      // 還原狀態
      items.value = state.items || []
      pageInfo.value = state.pageInfo || { hasMore: false, nextCursor: null }
      currentFilters.value = state.filters || currentFilters.value
      nameQuery.value = state.nameQuery || ''

      let cacheNeedsUpdate = false

      const createdIds = getCreatedIds()
      if (createdIds.length > 0) {
        await fetchAndPrependCreatedItems(createdIds)
        clearCreatedIds()
        cacheNeedsUpdate = true
      }

      const deletedIds = getDeletedIds()
      if (deletedIds.length > 0) {
        items.value = items.value.filter((item) => !deletedIds.includes(item.id))
        clearDeletedIds()
        cacheNeedsUpdate = true
      }

      const staleIds = getStaleIds()
      if (staleIds.length > 0) {
        await refreshStaleItems(staleIds)
        clearStaleIds()
        cacheNeedsUpdate = true
      }

      if (cacheNeedsUpdate) saveStateToStorage()

      nextTick(() => {
        setTimeout(() => {
          window.scrollTo(0, state.scrollY || 0)
        }, 50)
      })

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
