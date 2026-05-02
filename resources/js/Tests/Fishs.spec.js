import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Fishs from '@/Pages/Fishs.vue'

// ── Inertia mock ──────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a><slot /></a>', props: ['href'] },
  usePage: () => ({ props: { auth: { user: null } } }),
}))

// ── Layout mock ───────────────────────────────────────────────
vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template: '<div><slot /><slot name="desktop-nav" /><slot name="mobile-actions" /><slot name="header-extension" /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText', 'showBottomNav'],
  },
}))

// ── 子元件 mock（只驗證頁面層邏輯）──────────────────────────
vi.mock('@/Components/Fish/FishList/FishListNavActions.vue', () => ({
  default: { template: '<div />', props: ['variant', 'user'], emits: ['toggle'] },
}))
vi.mock('@/Components/Fish/FishList/FishSearchModal.vue', () => ({
  default: { template: '<div />', props: ['show', 'filters', 'nameQuery', 'searchOptions'] },
}))
vi.mock('@/Components/Fish/FishList/FishSearchStatsBar.vue', () => ({
  default: { template: '<div />', props: ['variant', 'showTotalCount', 'totalCount', 'appliedFilters'] },
}))
vi.mock('@/Components/Fish/FishList/FishSearchLoading.vue', () => ({
  default: { template: '<div />', props: ['show'] },
}))
vi.mock('@/Components/Fish/FishList/FishSearchCursorErrorBanner.vue', () => ({
  default: { template: '<div />', props: ['show'] },
}))
vi.mock('@/Components/Fish/FishList/FishCard.vue', () => ({
  default: { template: '<li data-testid="fish-card" />', props: ['fish', 'index'] },
}))

// ── composable mock（隔離頁面層邏輯，使用 Vue ref 確保響應式正確）─
vi.mock('@/composables/useFishList', async () => {
  const { ref } = await import('vue')
  return {
    useFishList: () => ({
      items: ref([]),
      pageInfo: ref({ hasMore: false, nextCursor: null }),
      isLoading: ref(false),
      showCursorError: ref(false),
      sentinel: ref(null),
      fetchPage: vi.fn(),
      performSearch: vi.fn(),
      retryFromStart: vi.fn(),
      initObserver: vi.fn(),
      disconnectObserver: vi.fn(),
      cleanPaginationFromUrl: vi.fn(),
    }),
  }
})

vi.mock('@/composables/useFishListCache', () => ({
  useFishListCache: () => ({
    saveStateToStorage: vi.fn(),
    clearStateStorage: vi.fn(),
    restoreStateFromStorage: vi.fn().mockResolvedValue(false),
  }),
}))

vi.mock('@/composables/useFishSearch', async () => {
  const { ref } = await import('vue')
  return {
    useFishSearch: () => ({
      showSearchDialog: ref(false),
      appliedFilters: ref([]),
      handleSearchToggle: vi.fn(),
      submitUnifiedSearch: vi.fn(),
      resetUnifiedSearch: vi.fn(),
      removeFilter: vi.fn(),
    }),
  }
})

// ── IntersectionObserver stub ────────────────────────────────
beforeEach(() => {
  global.IntersectionObserver = class {
    observe() {}
    disconnect() {}
    unobserve() {}
  }
})

const defaultProps = {
  items: [],
  pageInfo: { hasMore: false, nextCursor: null },
  filters: {},
  searchOptions: {
    tribes: [],
    dietaryClassifications: [],
    processingMethods: [],
    captureMethods: [],
    captureLocations: [],
  },
  searchStats: {},
}

const mountFishs = (propsData = {}) =>
  mount(Fishs, { props: { ...defaultProps, ...propsData } })

// ──────────────────────────────────────────────
// totalCount computed
// ──────────────────────────────────────────────
describe('totalCount', () => {
  it('searchStats.total_results 有數值時，應回傳該數值', () => {
    const wrapper = mountFishs({ searchStats: { total_results: 42 } })
    expect(wrapper.vm.totalCount).toBe(42)
  })

  it('searchStats.total_results 為 undefined 時，應退回 items.length（watcher 已同步 props）', async () => {
    const wrapper = mountFishs({
      items: [{ id: 1 }, { id: 2 }, { id: 3 }],
      searchStats: {},
    })
    await wrapper.vm.$nextTick()
    // watcher { immediate: true } 將 props.items 同步至 composable items，
    // totalCount 退回 items.value.length = 3
    expect(wrapper.vm.totalCount).toBe(3)
  })

  it('searchStats.total_results 為 0 時，應回傳 0', () => {
    const wrapper = mountFishs({ searchStats: { total_results: 0 } })
    expect(wrapper.vm.totalCount).toBe(0)
  })
})

// ──────────────────────────────────────────────
// without_audio 初始化轉換
// ──────────────────────────────────────────────
describe('currentFilters without_audio 初始化', () => {
  it('props.filters.without_audio 為 truthy 時，currentFilters.without_audio 應為 1', () => {
    const wrapper = mountFishs({ filters: { without_audio: true } })
    expect(wrapper.vm.currentFilters.without_audio).toBe(1)
  })

  it('props.filters.without_audio 為 falsy 時，currentFilters.without_audio 應為 ""', () => {
    const wrapper = mountFishs({ filters: { without_audio: false } })
    expect(wrapper.vm.currentFilters.without_audio).toBe('')
  })

  it('props.filters.without_audio 未傳入時，currentFilters.without_audio 應為 ""', () => {
    const wrapper = mountFishs({ filters: {} })
    expect(wrapper.vm.currentFilters.without_audio).toBe('')
  })
})

// ──────────────────────────────────────────────
// FishCard 渲染
// ──────────────────────────────────────────────
describe('FishCard 渲染', () => {
  it('props.items 為空時，不應渲染任何 FishCard', async () => {
    const wrapper = mountFishs({ items: [] })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="fish-card"]').length).toBe(0)
  })

  it('props.items 有資料時，watcher 同步後應渲染對應數量的 FishCard', async () => {
    const wrapper = mountFishs({
      items: [{ id: 1, name: 'A' }, { id: 2, name: 'B' }],
    })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="fish-card"]').length).toBe(2)
  })
})
