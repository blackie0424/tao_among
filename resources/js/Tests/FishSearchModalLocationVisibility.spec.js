import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'

const mountModal = (canAccessLocation, extraProps = {}) =>
  mount(FishSearchModal, {
    props: {
      show: true,
      canAccessLocation,
      ...extraProps,
      filters: {},
      nameQuery: '',
      searchOptions: {
        tribes: [],
        dietaryClassifications: [],
        processingMethods: [],
      },
    },
  })

describe('FishSearchModal location visibility', () => {
  it('does not render location filter without access', () => {
    const wrapper = mountModal(false)

    expect(wrapper.text()).not.toContain('捕獲地點')
    expect(wrapper.find('input[placeholder="可留空"]').exists()).toBe(true)
  })

  it('renders location filter with access', () => {
    const wrapper = mountModal(true)

    expect(wrapper.text()).toContain('捕獲地點')
  })
})

describe('FishSearchModal 進階篩選可見度', () => {
  it('showAdvanced 為 false 時只有魚名欄位', () => {
    const wrapper = mountModal(true, { showAdvanced: false })

    expect(wrapper.findAll('input')).toHaveLength(1)
    expect(wrapper.find('select').exists()).toBe(false)
    expect(wrapper.text()).toContain('名稱')
    for (const label of ['部落', '分類', '魚鱗的處理', '捕獲地點']) {
      expect(wrapper.text()).not.toContain(label)
    }
  })

  it('showAdvanced 為 true 時顯示進階篩選', () => {
    const wrapper = mountModal(false, { showAdvanced: true })

    expect(wrapper.findAll('select')).toHaveLength(3)
    expect(wrapper.text()).toContain('部落')
  })
})
