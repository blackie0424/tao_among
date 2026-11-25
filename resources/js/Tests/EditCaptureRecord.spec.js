import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EditCaptureRecord from '@/Pages/EditCaptureRecord.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: { post: vi.fn(), visit: vi.fn(), delete: vi.fn() },
}))
vi.mock('@/Layouts/FishAppLayout.vue', () => ({
  default: {
    name: 'FishAppLayout',
    props: ['pageTitle', 'mobileBackUrl', 'mobileBackText'],
    template: '<div><slot /></div>',
  },
}))
vi.mock('@/Components/CaptureRecord/CaptureRecordForm.vue', () => ({
  default: {
    name: 'CaptureRecordForm',
    emits: ['submit', 'statusChange'],
    setup(_, { expose, emit }) {
      expose({ submitForm: () => emit('submit', { notes: 'x' }), setErrors: vi.fn() })
      return {}
    },
    template: '<div data-testid="form" />',
  },
}))

function mountPage({ displayId = null } = {}) {
  return mount(EditCaptureRecord, {
    props: {
      fish: { id: 4, name: '測試魚', display_capture_record_id: displayId },
      record: { id: 9, session_id: 3 },
      tribes: [],
      capture_methods: [],
    },
  })
}

describe('EditCaptureRecord 頁', () => {
  beforeEach(() => vi.clearAllMocks())

  it('標題為「{魚名}・編輯捕獲紀錄」，返回管理頁', () => {
    const wrapper = mountPage()
    expect(wrapper.get('h1').text()).toBe('測試魚・編輯捕獲紀錄')
    expect(wrapper.getComponent({ name: 'FishAppLayout' }).props()).toMatchObject({
      pageTitle: '測試魚・編輯捕獲紀錄',
      mobileBackUrl: '/fish/4/capture-records',
      mobileBackText: '回管理捕獲紀錄',
    })
  })

  it('儲存成功後回管理頁', async () => {
    const wrapper = mountPage()
    await wrapper.get('[data-testid="save-btn"]').trigger('click')
    expect(router.post).toHaveBeenCalledWith(
      '/fish/4/capture-records/9',
      { notes: 'x' },
      expect.any(Object)
    )
    router.post.mock.calls[0][2].onSuccess()
    expect(router.visit).toHaveBeenCalledWith('/fish/4/capture-records')
  })

  it('最下方「危險操作」有紅框刪除，確認後才送出刪除', async () => {
    const wrapper = mountPage()
    const zone = wrapper.get('[data-testid="danger-zone"]')
    expect(zone.text()).toContain('危險操作')
    const del = zone.get('[data-testid="delete-btn"]')
    expect(del.text()).toBe('刪除這筆捕獲紀錄')
    expect(del.classes()).toEqual(expect.arrayContaining(['border', 'border-red-600']))
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false)
    await del.trigger('click')
    expect(confirm).toHaveBeenCalledWith('確定要刪除這筆捕獲紀錄嗎？')
    expect(router.delete).not.toHaveBeenCalled()
    confirm.mockReturnValue(true)
    await del.trigger('click')
    expect(router.delete).toHaveBeenCalledWith('/fish/4/capture-records/9')
    confirm.mockRestore()
  })

  it('這筆是圖鑑主圖：刪除停用並寫明原因，不送出請求', async () => {
    const wrapper = mountPage({ displayId: 9 })
    const del = wrapper.get('[data-testid="delete-btn"]')
    expect(del.attributes('disabled')).toBeDefined()
    expect(wrapper.get('[data-testid="delete-blocked"]').text()).toBe(
      '這是圖鑑主圖，請先把別張設為主圖才能刪除'
    )
    await del.trigger('click')
    expect(router.delete).not.toHaveBeenCalled()
  })

  it('別張是主圖時，這筆可以刪除且不顯示原因', () => {
    const wrapper = mountPage({ displayId: 10 })
    expect(wrapper.get('[data-testid="delete-btn"]').attributes('disabled')).toBeUndefined()
    expect(wrapper.find('[data-testid="delete-blocked"]').exists()).toBe(false)
  })
})
