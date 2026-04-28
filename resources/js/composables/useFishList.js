/**
 * useFishList — 魚類清單資料抓取與無限滾動 composable
 *
 * 職責（SRP）：
 *   - 管理 items / pageInfo / isLoading / showCursorError 響應式狀態
 *   - 封裝 fetchPage（Inertia router.get + URL 清理）
 *   - 封裝 IntersectionObserver 無限滾動觸底偵測
 *
 * @param {import('vue').Ref} currentFilters  搜尋篩選條件 ref
 * @param {import('vue').Ref} nameQuery        名稱關鍵字 ref
 */

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useFishList(currentFilters, nameQuery) {
  const items = ref([])
  const pageInfo = ref({ hasMore: false, nextCursor: null })
  const isLoading = ref(false)
  const showCursorError = ref(false)
  const sentinel = ref(null)

  let observer = null
  let rafId = null

  // --- 內部工具 ---

  const buildQueryParams = (override = {}) => {
    const base = {
      ...currentFilters.value,
      name: nameQuery.value || currentFilters.value.name || '',
      perPage: 20,
    }
    const merged = { ...base, ...override }
    return Object.fromEntries(
      Object.entries(merged).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    )
  }

  const cleanPaginationFromUrl = () => {
    try {
      const url = new URL(window.location.href)
      url.searchParams.delete('last_id')
      url.searchParams.delete('perPage')
      const qs = url.searchParams.toString()
      const clean = url.pathname + (qs ? `?${qs}` : '') + (url.hash || '')
      window.history.replaceState(window.history.state, '', clean)
    } catch (e) {
      // 忽略 URL API 在部分環境不可用的情況
    }
  }

  // --- 公開方法 ---

  /**
   * 發送分頁或搜尋請求
   * @param {Object} opts  覆蓋 query params（例如 { last_id: 123 }）
   */
  const fetchPage = (opts = {}) => {
    if (isLoading.value || showCursorError.value) return
    isLoading.value = true
    const params = buildQueryParams(opts)
    const isPagination = Boolean(params.last_id)
    router.get('/fishs', params, {
      preserveState: true,
      preserveScroll: true,
      replace: isPagination, // 分頁請求不堆疊歷史紀錄，避免返回鍵陷阱
      onSuccess: (page) => {
        const newItems = page.props.items || []
        const newPageInfo = page.props.pageInfo || { hasMore: false, nextCursor: null }
        if (params.last_id) {
          items.value = [...items.value, ...newItems]
        } else {
          items.value = newItems
        }
        pageInfo.value = newPageInfo
        isLoading.value = false
        cleanPaginationFromUrl()
      },
      onError: () => {
        // 422 INVALID_CURSOR → 顯示 Banner
        isLoading.value = false
        showCursorError.value = true
      },
    })
  }

  /**
   * 重新啟動搜尋（第一頁），可傳入 onBeforeSearch callback（例如清快取）
   * @param {Function} [onBeforeSearch]
   */
  const performSearch = (onBeforeSearch) => {
    onBeforeSearch?.()
    showCursorError.value = false
    pageInfo.value = { hasMore: false, nextCursor: null }
    fetchPage({})
  }

  /** 游標錯誤後重試（保留篩選，從第一頁重抓） */
  const retryFromStart = () => {
    showCursorError.value = false
    pageInfo.value = { hasMore: false, nextCursor: null }
    fetchPage({})
  }

  /** 初始化 IntersectionObserver 監聽哨兵元素 */
  const initObserver = () => {
    if (!sentinel.value) return
    observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting && pageInfo.value.hasMore && !isLoading.value) {
            if (rafId) cancelAnimationFrame(rafId)
            rafId = requestAnimationFrame(() => {
              fetchPage({ last_id: pageInfo.value.nextCursor })
              rafId = null
            })
          }
        })
      },
      { rootMargin: '200px' }
    )
    observer.observe(sentinel.value)
  }

  /** 解除 IntersectionObserver 監聽（onBeforeUnmount 呼叫） */
  const disconnectObserver = () => {
    if (observer) {
      observer.disconnect()
      observer = null
    }
  }

  return {
    items,
    pageInfo,
    isLoading,
    showCursorError,
    sentinel,
    fetchPage,
    performSearch,
    retryFromStart,
    initObserver,
    disconnectObserver,
    cleanPaginationFromUrl,
  }
}
