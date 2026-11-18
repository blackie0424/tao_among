import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Fishs from '@/Pages/Fishs.vue'

const { clearAllFiltersMock, handleSearchToggleMock, authState, listState } = vi.hoisted(() => ({
  clearAllFiltersMock: vi.fn(),
  handleSearchToggleMock: vi.fn(),
  authState: { user: null },
  listState: { showCursorError: false },
}))

// ── Inertia mock ──────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => ({ props: { auth: { user: authState.user } } }),
}))

// ── Layout mock ───────────────────────────────────────────────
vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    template:
      '<div><slot /><slot name="desktop-nav" /><slot name="mobile-actions" /><slot name="header-extension" /></div>',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText', 'showBottomNav'],
  },
}))

// ── 子元件 mock（只驗證頁面層邏輯）──────────────────────────
vi.mock('@/Components/UI/BackToTopButton.vue', () => ({
  default: { template: '<button data-testid="back-to-top" />' },
}))
vi.mock('@/Components/UI/SearchToggleButton.vue', () => ({
  default: {
    template: '<button data-testid="search-toggle" @click="$emit(\'toggle\')">{{ label }}</button>',
    props: ['label'],
    emits: ['toggle'],
  },
}))
vi.mock('@/Components/FishList/FishSearchModal.vue', () => ({
  default: {
    template: '<div data-testid="fish-search-modal" />',
    props: ['show', 'filters', 'nameQuery', 'searchOptions', 'showAdvanced'],
  },
}))
vi.mock('@/Components/FishList/FishSearchStatsBar.vue', () => ({
  default: {
    name: 'FishSearchStatsBar',
    template: '<div data-testid="fish-search-stats" />',
    props: ['variant', 'showTotalCount', 'totalCount', 'appliedFilters'],
    emits: ['remove-filter', 'clear-all'],
  },
}))
vi.mock('@/Components/FishList/FishSearchLoading.vue', () => ({
  default: { template: '<div />', props: ['show'] },
}))
vi.mock('@/Components/FishList/FishSearchCursorErrorBanner.vue', () => ({
  default: { template: '<div v-if="show" data-testid="cursor-error" />', props: ['show'] },
}))
vi.mock('@/Components/FishList/FishCard.vue', () => ({
  default: {
    template:
      '<li data-testid="fish-card" :data-gated="String(gated)"><a href="#" data-testid="fish-card-link" @click.prevent="gated && $emit(\'blocked\', $event.currentTarget)">{{ fish.name }}</a></li>',
    props: ['fish', 'index', 'gated'],
    emits: ['blocked'],
  },
}))

// ── composable mock（隔離頁面層邏輯，使用 Vue ref 確保響應式正確）─
vi.mock('@/composables/useFishList', async () => {
  const { ref } = await import('vue')
  return {
    useFishList: () => ({
      items: ref([]),
      pageInfo: ref({ hasMore: false, nextCursor: null }),
      isLoading: ref(false),
      showCursorError: ref(listState.showCursorError),
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
    processStaleItems: vi.fn().mockResolvedValue(false),
  }),
}))

vi.mock('@/composables/useFishSearch', async () => {
  const { ref } = await import('vue')
  return {
    useFishSearch: () => ({
      showSearchDialog: ref(false),
      appliedFilters: ref([]),
      handleSearchToggle: handleSearchToggleMock,
      submitUnifiedSearch: vi.fn(),
      resetUnifiedSearch: vi.fn(),
      clearAllFilters: clearAllFiltersMock,
      removeFilter: vi.fn(),
    }),
  }
})

// ── IntersectionObserver stub ────────────────────────────────
beforeEach(() => {
  authState.user = null
  listState.showCursorError = false
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

const mountFishs = (propsData = {}) => mount(Fishs, { props: { ...defaultProps, ...propsData } })

describe('搜尋列位置與事件', () => {
  it('篩選列位於搜尋對話框後、魚類格線前', () => {
    const wrapper = mountFishs()
    const html = wrapper.html()
    expect(html.indexOf('fish-search-modal')).toBeLessThan(html.indexOf('fish-search-stats'))
    expect(html.indexOf('fish-search-stats')).toBeLessThan(html.indexOf('grid grid-cols-1'))
  })

  it('clear-all 事件交給 clearAllFilters', () => {
    clearAllFiltersMock.mockClear()
    const wrapper = mountFishs()
    wrapper.getComponent({ name: 'FishSearchStatsBar' }).vm.$emit('clear-all')
    expect(clearAllFiltersMock).toHaveBeenCalledOnce()
  })
})

describe('搜尋入口（AC 4）', () => {
  it('搜尋欄常駐在清單上方，且只有一個入口', () => {
    const wrapper = mountFishs()
    const bar = wrapper.get('[data-testid="search-toggle"]')

    expect(wrapper.findAll('[data-testid="search-toggle"]')).toHaveLength(1)
    expect(bar.element.closest('main')).not.toBeNull()
    expect(wrapper.html().indexOf('search-toggle')).toBeLessThan(
      wrapper.html().indexOf('grid grid-cols-1')
    )
  })

  it('點搜尋欄開啟相同的搜尋對話框', async () => {
    handleSearchToggleMock.mockClear()
    const wrapper = mountFishs()
    await wrapper.get('[data-testid="search-toggle"]').trigger('click')
    expect(handleSearchToggleMock).toHaveBeenCalledOnce()
  })

  it.each([
    ['未登入', null, false],
    ['guest', { role: 'guest' }, false],
    ['viewer', { role: 'viewer' }, true],
    ['editor', { role: 'editor' }, true],
    ['admin', { role: 'admin' }, true],
  ])('%s 對話框的進階篩選：%s', (_label, user, expected) => {
    authState.user = user
    const wrapper = mountFishs()
    const modal = wrapper.findComponent('[data-testid="fish-search-modal"]')
    expect(modal.props('showAdvanced')).toBe(expected)
  })
})

describe('搜尋沒有結果（AC 5）', () => {
  it('清單為空且載入完成時顯示「找不到符合的魚」', async () => {
    const wrapper = mountFishs({ items: [] })
    await flushPromises()
    expect(wrapper.get('[data-testid="fish-list-empty"]').text()).toBe('找不到符合的魚')
  })

  it('游標失效時顯示錯誤提示，不顯示「找不到符合的魚」', async () => {
    listState.showCursorError = true
    const wrapper = mountFishs({ items: [] })
    await flushPromises()

    expect(wrapper.find('[data-testid="cursor-error"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="fish-list-empty"]').exists()).toBe(false)
  })

  it('有魚時不顯示空清單文字', async () => {
    const wrapper = mountFishs({ items: [{ id: 1, name: 'A' }] })
    await flushPromises()
    expect(wrapper.find('[data-testid="fish-list-empty"]').exists()).toBe(false)
  })
})

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
      items: [
        { id: 1, name: 'A' },
        { id: 2, name: 'B' },
      ],
    })
    await wrapper.vm.$nextTick()
    expect(wrapper.findAll('[data-testid="fish-card"]').length).toBe(2)
  })
})

// ──────────────────────────────────────────────
// 訪客面板（AC 7–9）
// ──────────────────────────────────────────────
describe('點魚卡的身分分流', () => {
  const fishItems = [
    { id: 5, name: '飛魚' },
    { id: 6, name: '' },
  ]
  const mountWithAttach = async (user) => {
    authState.user = user
    const wrapper = mount(Fishs, { props: { ...defaultProps, items: fishItems }, attachTo: document.body })
    await flushPromises()
    return wrapper
  }

  it.each([
    ['未登入', null, true],
    ['guest', { role: 'guest' }, true],
    ['viewer', { role: 'viewer' }, false],
    ['editor', { role: 'editor' }, false],
    ['admin', { role: 'admin' }, false],
  ])('%s：魚卡 gated = %s', async (_label, user, gated) => {
    const wrapper = await mountWithAttach(user)
    const cards = wrapper.findAll('[data-testid="fish-card"]')
    expect(cards.every((c) => c.attributes('data-gated') === String(gated))).toBe(true)
    wrapper.unmount()
  })

  it('訪客點魚卡：滑出登入面板，標題帶魚名、登入鈕帶回該魚', async () => {
    const wrapper = await mountWithAttach(null)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    await wrapper.findAll('[data-testid="fish-card-link"]')[0].trigger('click')

    expect(wrapper.get('[data-testid="sheet-title"]').text()).toBe('想認識「飛魚」？')
    expect(wrapper.get('[data-testid="sheet-login"]').attributes('href')).toBe('/login?redirect=/fish/5')
    wrapper.unmount()
  })

  it('魚名為空時面板標題用「這條魚」', async () => {
    const wrapper = await mountWithAttach(null)
    await wrapper.findAll('[data-testid="fish-card-link"]')[1].trigger('click')
    expect(wrapper.get('[data-testid="sheet-title"]').text()).toBe('想認識「這條魚」？')
    wrapper.unmount()
  })

  it('guest 點魚卡：顯示等待開通、沒有登入鈕', async () => {
    const wrapper = await mountWithAttach({ role: 'guest' })
    await wrapper.findAll('[data-testid="fish-card-link"]')[0].trigger('click')
    expect(wrapper.get('[data-testid="sheet-body"]').text()).toBe('你的帳號還在等待開通')
    expect(wrapper.find('[data-testid="sheet-login"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('已開通者的魚卡不 gated，不會出現面板', async () => {
    const wrapper = await mountWithAttach({ role: 'viewer' })
    await wrapper.findAll('[data-testid="fish-card-link"]')[0].trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('Esc 關閉面板後，焦點回到被點的魚卡', async () => {
    const wrapper = await mountWithAttach(null)
    const link = wrapper.findAll('[data-testid="fish-card-link"]')[0]
    link.element.focus()
    await link.trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(link.element)
    wrapper.unmount()
  })

  it('清單頁有回到頂端鈕', () => {
    expect(mountFishs().find('[data-testid="back-to-top"]').exists()).toBe(true)
  })
})
