import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import FormActionBar from '@/Components/Global/FormActionBar.vue'
import EditFishName from '@/Pages/EditFishName.vue'

vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }))

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

it('取消編輯名稱固定回基本 tab', async () => {
  const { router } = await import('@inertiajs/vue3')
  const wrapper = mount(EditFishName, { props: { fish: { id: 4, name: '測試魚' } } })
  await wrapper.get('button[aria-label="關閉"]').trigger('click')
  expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=basic')
})
