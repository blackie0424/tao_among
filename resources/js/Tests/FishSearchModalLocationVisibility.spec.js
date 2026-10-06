import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FishSearchModal from '@/Components/FishList/FishSearchModal.vue'

const mountModal = (canAccessLocation) =>
  mount(FishSearchModal, {
    props: {
      show: true,
      canAccessLocation,
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