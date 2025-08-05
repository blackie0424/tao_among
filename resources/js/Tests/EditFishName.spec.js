import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TopNavBar from '@/Components/Global/TopNavBar.vue'
import EditFishName from '@/Pages/EditFishName.vue'

describe('EditFishName', () => {
  it('載入 EditFishName 時，應該顯示 TopNavBar', () => {
    const wrapper = mount(EditFishName, {
      props: {
        fish: {},
      },
    })
    expect(wrapper.findComponent(TopNavBar).exists()).toBe(true)
  })
})
