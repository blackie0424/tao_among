import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCreateCaptureRecord from '@/Pages/BatchCreateCaptureRecord.vue'
import BatchCaptureImageUploader from '@/Components/CaptureRecord/BatchCaptureImageUploader.vue'

vi.mock('@/utils/apiFetch', () => ({ apiFetch: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  router: { visit: vi.fn(), post: vi.fn() },
  usePage: () => ({ props: { auth: { user: { role: 'editor' } }, flash: {} } }),
}))
vi.mock('@/Layouts/FishAppLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))

// 新增頁實際內容（含共用的照片上傳與情境選擇元件）不得有舊的藍／綠／青色、陰影與白卡
const FORBIDDEN =
  /(?:bg|text|border|ring)-(?:blue|green|teal|cyan|emerald)-\d|shadow|bg-white|rounded-(?:lg|xl|full)/

const props = {
  fish: { id: 4, name: '測試魚' },
  tribes: ['ivalino'],
  capture_methods: { mamasil: 'mamasil' },
  selectable_sessions: [
    {
      id: 12,
      capture_date: '2026-10-08',
      tribe: 'ivalino',
      capture_method: 'mamasil',
      place_name: '測試灣',
      location_hint: null,
      record_count: 2,
    },
  ],
  legacy_combos: [
    {
      capture_date: '2026-10-07',
      tribe: 'ivalino',
      capture_method: 'mamasil',
      location: '舊地點',
      record_count: 1,
    },
  ],
}

describe('BatchCreateCaptureRecord 實際呈現', () => {
  it('第一步（照片上傳）沒有舊配色', () => {
    expect(mount(BatchCreateCaptureRecord, { props }).html()).not.toMatch(FORBIDDEN)
  })

  it('第二步（情境選擇、新增情境表單、舊資料選項）沒有舊配色', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, { props })
    wrapper.findComponent(BatchCaptureImageUploader).vm.$emit('uploaded', ['one.jpg'])
    await nextTick()
    await wrapper.get('[data-testid="open-inline-session-form"]').trigger('click')
    expect(wrapper.find('[data-testid="inline-session-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="legacy-combo-option"]').exists()).toBe(true)
    expect(wrapper.html()).not.toMatch(FORBIDDEN)
  })
})
