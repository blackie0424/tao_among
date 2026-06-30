import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FishListNavActions from '@/Components/FishList/FishListNavActions.vue'

// stub Inertia Link（href 在 props，需明確綁定；class 等其他 attr 由 $attrs 轉發）
const LinkStub = {
  name: 'Link',
  template: '<a :href="href" v-bind="$attrs"><slot /></a>',
  props: ['href'],
}

// stub SearchToggleButton
const SearchToggleButtonStub = {
  name: 'SearchToggleButton',
  template: '<button class="search-toggle-btn" @click="$emit(\'toggle\')"></button>',
  emits: ['toggle'],
}

const globalStubs = {
  global: {
    stubs: {
      Link: LinkStub,
      SearchToggleButton: SearchToggleButtonStub,
    },
  },
}

describe('FishListNavActions', () => {
  // ─── desktop variant ──────────────────────────────────────────────────────

  describe('variant="desktop"', () => {
    it('有 user 時渲染新增按鈕（文字樣式）', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'desktop', user: { id: 1 } },
        ...globalStubs,
      })
      const link = wrapper.find('a[href="/fish/batch-create"]')
      expect(link.exists()).toBe(true)
      expect(link.text()).toContain('新增')
    })

    it('無 user 時不渲染新增按鈕', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'desktop', user: null },
        ...globalStubs,
      })
      expect(wrapper.find('a[href="/fish/batch-create"]').exists()).toBe(false)
    })

    it('渲染 SearchToggleButton', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'desktop', user: null },
        ...globalStubs,
      })
      expect(wrapper.find('.search-toggle-btn').exists()).toBe(true)
    })

    it('SearchToggleButton 觸發 toggle 時 emit toggle', async () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'desktop', user: null },
        ...globalStubs,
      })
      await wrapper.find('.search-toggle-btn').trigger('click')
      expect(wrapper.emitted('toggle')).toBeTruthy()
    })

    it('外層 container 帶有 justify-end 與 h-10', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'desktop', user: null },
        ...globalStubs,
      })
      const root = wrapper.find('div')
      expect(root.classes()).toContain('justify-end')
      expect(root.classes()).toContain('h-10')
    })
  })

  // ─── mobile variant ───────────────────────────────────────────────────────

  describe('variant="mobile"', () => {
    it('有 user 時渲染新增按鈕（圖示樣式，觸控目標 w-14 h-14）', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'mobile', user: { id: 1 } },
        ...globalStubs,
      })
      const link = wrapper.find('a[href="/fish/batch-create"]')
      expect(link.exists()).toBe(true)
      expect(link.classes()).toContain('w-14')
      expect(link.classes()).toContain('h-14')
    })

    it('無 user 時不渲染新增按鈕', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'mobile', user: null },
        ...globalStubs,
      })
      expect(wrapper.find('a[href="/fish/batch-create"]').exists()).toBe(false)
    })

    it('渲染 SearchToggleButton', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'mobile', user: null },
        ...globalStubs,
      })
      expect(wrapper.find('.search-toggle-btn').exists()).toBe(true)
    })

    it('SearchToggleButton 觸發 toggle 時 emit toggle', async () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'mobile', user: null },
        ...globalStubs,
      })
      await wrapper.find('.search-toggle-btn').trigger('click')
      expect(wrapper.emitted('toggle')).toBeTruthy()
    })

    it('外層 container 帶有 justify-end 與 px-2', () => {
      const wrapper = mount(FishListNavActions, {
        props: { variant: 'mobile', user: null },
        ...globalStubs,
      })
      const root = wrapper.find('div')
      expect(root.classes()).toContain('justify-end')
      expect(root.classes()).toContain('px-2')
    })
  })
})
