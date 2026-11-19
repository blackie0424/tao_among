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
 * @param {import('vue').Ref} [view]           目前選的看法 ref（'all' 為不分類）
 */

import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const ALL_VIEW = 'all'

export function useFishList(currentFilters, nameQuery, view = ref(ALL_VIEW)) {
  const items = ref([])
  const pageInfo = ref({ hasMore: false, nextCursor: null })
  const isLoading = ref(false)
  const showCursorError = ref(false)
  const sentinel = ref(null)
  // 字母順序的段標題魚數：只在第一頁回應給，往下載入時保留
  const groupCounts = ref(null)

  let observer = null
  let rafId = null
  // 每發出一次請求加一：較早的請求被取代後，它的回應不能再改畫面
  let requestSeq = 0

  // --- 內部工具 ---

  const buildQueryParams = (override = {}) => {
    // 選了看法就不帶搜尋條件（看法與搜尋互斥）
    const base =
      view.value === ALL_VIEW
        ? {
            ...currentFilters.value,
            name: nameQuery.value || currentFilters.value.name || '',
            perPage: 20,
          }
        : { view: view.value, perPage: 20 }
    const merged = { ...base, ...override }
    const params = Object.fromEntries(
      Object.entries(merged).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    )
    // 名字游標的 last_name 可以是空字串（空名魚），必須照樣送出
    if ('last_name' in override) params.last_name = override.last_name ?? ''
    return params
  }

  const cleanPaginationFromUrl = () => {
    try {
      const url = new URL(window.location.href)
      url.searchParams.delete('last_id')
      url.searchParams.delete('last_name')
      url.searchParams.delete('perPage')
      // 看法不留在網址：重新整理時回到全部
      url.searchParams.delete('view')
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
   * @param {{ replaceInFlight?: boolean }} [flags]  切換看法或重新搜尋時，取代進行中的請求
   */
  const fetchPage = (opts = {}, { replaceInFlight = false } = {}) => {
    if (!replaceInFlight && (isLoading.value || showCursorError.value)) return
    isLoading.value = true
    const seq = ++requestSeq
    // 回應（成功或錯誤）是否已處理；沒處理就結束的請求是網路中斷或非 Inertia 的伺服器錯誤
    let settled = false
    const params = buildQueryParams(opts)
    const isPagination = Boolean(params.last_id)
    router.get('/', params, {
      preserveState: true,
      preserveScroll: true,
      replace: isPagination, // 分頁請求不堆疊歷史紀錄，避免返回鍵陷阱
      onSuccess: (page) => {
        settled = true
        // 已被較新的選擇取代，或回應屬於別的看法：丟棄，不混入舊結果
        if (seq !== requestSeq || (page.props.view ?? ALL_VIEW) !== (params.view ?? ALL_VIEW))
          return
        const newItems = page.props.items || []
        const newPageInfo = page.props.pageInfo || { hasMore: false, nextCursor: null }
        if (params.last_id) {
          items.value = [...items.value, ...newItems]
        } else {
          items.value = newItems
        }
        pageInfo.value = newPageInfo
        if (!params.last_id) groupCounts.value = page.props.viewGroupCounts ?? null
        isLoading.value = false
        cleanPaginationFromUrl()
      },
      onError: () => {
        settled = true
        if (seq !== requestSeq) return
        // 422 INVALID_CURSOR → 顯示 Banner
        isLoading.value = false
        showCursorError.value = true
      },
      // 不論結果都會呼叫：沒有成功也沒有 onError、也不是被新請求取代，就是網路中斷或伺服器錯誤
      onFinish: (visit) => {
        if (settled || seq !== requestSeq || visit?.cancelled || visit?.interrupted) return
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
    fetchPage({}, { replaceInFlight: true })
  }

  /** 游標錯誤後重試（保留篩選，從第一頁重抓） */
  const retryFromStart = () => {
    showCursorError.value = false
    pageInfo.value = { hasMore: false, nextCursor: null }
    fetchPage({}, { replaceInFlight: true })
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
              fetchPage({
                last_id: pageInfo.value.nextCursor,
                ...(pageInfo.value.nextName !== undefined && {
                  last_name: pageInfo.value.nextName,
                }),
              })
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
    groupCounts,
    fetchPage,
    performSearch,
    retryFromStart,
    initObserver,
    disconnectObserver,
    cleanPaginationFromUrl,
  }
}
