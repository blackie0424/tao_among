import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FormActionBar from '@/Components/Global/FormActionBar.vue'
import EditFishName from '@/Pages/EditFishName.vue'

describe('EditFishName', () => {
  it('載入 EditFishName 時，應該顯示 FormActionBar', () => {
    const wrapper = mount(EditFishName, {
      props: {
        fish: {},
      },
    })
    expect(wrapper.findComponent(FormActionBar).exists()).toBe(true)
  })
})
