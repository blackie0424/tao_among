import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import FishKnowledgeCard from '@/Components/FishKnowledgeCard.vue'
import OverflowMenu from '@/Components/OverflowMenu.vue'

// Mock OverflowMenu component
vi.mock('@/Components/OverflowMenu.vue', () => ({
  default: {
    name: 'OverflowMenu',
    props: ['apiUrl', 'fishId', 'editUrl'],
    emits: ['deleted'],
    template: '<div class="overflow-menu-mock" @click="$emit(\'deleted\')">Mock OverflowMenu</div>',
  },
}))

describe('FishKnowledgeCard', () => {
  let wrapper
  const defaultProps = {
    note: {
      id: 1,
      note: '這是一個測試知識',
      note_type: '生態習性',
      locate: '台灣東部海域',
      created_at: '2023-10-13T10:30:00Z',
    },
    fishId: 123,
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount()
    }
  })

  describe('基本渲染測試', () => {
    it('應該正確渲染知識卡片的基本結構', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      expect(wrapper.find('.bg-gray-50').exists()).toBe(true)
      expect(wrapper.find('.rounded-lg').exists()).toBe(true)
      expect(wrapper.find('.border').exists()).toBe(true)
    })

    it('應該顯示知識分類標籤', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const categoryTag = wrapper.find('.bg-blue-100')
      expect(categoryTag.exists()).toBe(true)
      expect(categoryTag.text()).toBe('生態習性')
    })

    it('當沒有分類時應該顯示預設分類', () => {
      const propsWithoutType = {
        ...defaultProps,
        note: {
          ...defaultProps.note,
          note_type: null,
        },
      }

      wrapper = mount(FishKnowledgeCard, {
        props: propsWithoutType,
      })

      const categoryTag = wrapper.find('.bg-blue-100')
      expect(categoryTag.text()).toBe('一般知識')
    })

    it('應該顯示知識內容', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const noteContent = wrapper.find('.text-gray-800')
      expect(noteContent.exists()).toBe(true)
      expect(noteContent.text()).toBe('這是一個測試知識')
    })

    it('應該顯示位置資訊', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const locationSection = wrapper.find('.text-gray-500')
      expect(locationSection.exists()).toBe(true)
      expect(locationSection.text()).toBe('位置')

      const locationContent = wrapper.find('.text-gray-700')
      expect(locationContent.exists()).toBe(true)
      expect(locationContent.text()).toBe('台灣東部海域')
    })

    it('當沒有位置資訊時不應該顯示位置區塊', () => {
      const propsWithoutLocation = {
        ...defaultProps,
        note: {
          ...defaultProps.note,
          locate: null,
        },
      }

      wrapper = mount(FishKnowledgeCard, {
        props: propsWithoutLocation,
      })

      const locationSection = wrapper.find('.text-gray-500')
      expect(locationSection.exists()).toBe(false)
    })

    it('應該顯示格式化的建立時間', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const timeInfo = wrapper.find('.text-gray-400')
      expect(timeInfo.exists()).toBe(true)
      expect(timeInfo.text()).toContain('記錄時間:')
    })
  })

  describe('OverflowMenu 整合測試', () => {
    it('應該正確傳遞 props 給 OverflowMenu', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const overflowMenu = wrapper.findComponent(OverflowMenu)
      expect(overflowMenu.exists()).toBe(true)
      expect(overflowMenu.props('apiUrl')).toBe('/fish/123/knowledge/1')
      expect(overflowMenu.props('fishId')).toBe('123')
      expect(overflowMenu.props('editUrl')).toBe('/fish/123/knowledge/1/edit')
    })

    it('當 OverflowMenu 觸發 deleted 事件時應該向上傳遞', async () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const overflowMenu = wrapper.findComponent(OverflowMenu)
      await overflowMenu.vm.$emit('deleted')

      expect(wrapper.emitted('deleted')).toBeTruthy()
      expect(wrapper.emitted('deleted')).toHaveLength(1)
    })
  })

  describe('事件處理測試', () => {
    it('應該正確定義 emits', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      // 檢查組件是否定義了正確的 emits
      expect(wrapper.vm.$options.emits).toContain('updated')
      expect(wrapper.vm.$options.emits).toContain('deleted')
    })
  })

  describe('Props 驗證測試', () => {
    it('應該要求必要的 props', () => {
      // 測試缺少必要 props 時的行為
      const consoleWarn = vi.spyOn(console, 'warn').mockImplementation(() => {})

      wrapper = mount(FishKnowledgeCard, {
        props: {
          note: {}, // 提供空的 note 對象
          fishId: 1, // 提供 fishId
        },
      })

      // 組件應該能夠渲染，但可能會有警告
      expect(wrapper.exists()).toBe(true)
      consoleWarn.mockRestore()
    })

    it('應該接受字串或數字類型的 fishId', () => {
      // 測試數字類型的 fishId
      wrapper = mount(FishKnowledgeCard, {
        props: {
          ...defaultProps,
          fishId: 123,
        },
      })

      expect(wrapper.vm.fishId).toBe(123)

      wrapper.unmount()

      // 測試字串類型的 fishId
      wrapper = mount(FishKnowledgeCard, {
        props: {
          ...defaultProps,
          fishId: '123',
        },
      })

      expect(wrapper.vm.fishId).toBe('123')
    })
  })

  describe('日期格式化測試', () => {
    it('應該正確格式化日期時間', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      // 檢查 formatDateTime 方法是否存在
      expect(typeof wrapper.vm.formatDateTime).toBe('function')

      // 測試日期格式化
      const testDate = '2023-10-13T10:30:00Z'
      const formatted = wrapper.vm.formatDateTime(testDate)
      expect(formatted).toBeTruthy()
      expect(typeof formatted).toBe('string')
    })
  })

  describe('響應式設計測試', () => {
    it('應該包含響應式 CSS 類別', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      const cardElement = wrapper.find('.bg-gray-50')
      expect(cardElement.classes()).toContain('rounded-lg')
      expect(cardElement.classes()).toContain('p-4')
      expect(cardElement.classes()).toContain('border')
    })
  })

  describe('無障礙功能測試', () => {
    it('應該有適當的文字對比度類別', () => {
      wrapper = mount(FishKnowledgeCard, {
        props: defaultProps,
      })

      // 檢查文字顏色類別是否提供足夠對比度
      expect(wrapper.find('.text-gray-800').exists()).toBe(true)
      expect(wrapper.find('.text-gray-700').exists()).toBe(true)
      expect(wrapper.find('.text-gray-500').exists()).toBe(true)
      expect(wrapper.find('.text-gray-400').exists()).toBe(true)
    })
  })
})
