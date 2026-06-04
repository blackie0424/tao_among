import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import CaptureRecordEditForm from '@/Components/CaptureRecord/CaptureRecordEditForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn(), put: vi.fn() },
  usePage: () => ({
    props: { auth: { user: null }, flash: {} },
  }),
}))

global.fetch = vi.fn()

const defaultProps = {
  record: {
    id: 1,
    tribe: 'ivalino',
    location: '溪流A',
    capture_method: '網捕',
    capture_date: '2024-05-01',
    notes: '',
    image: 'existing.jpg',
  },
  tribes: ['ivalino', 'iranmeilek'],
  capture_methods: ['網捕', '釣魚'],
  fishId: 1,
  fishName: 'Test Fish',
  fishImage: 'test-image.jpg',
}

describe('CaptureRecordEditForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders form correctly', () => {
    const wrapper = mount(CaptureRecordEditForm, { props: defaultProps })
    expect(wrapper.find('form').exists()).toBe(true)
  })

  // ==================== 上傳 header 驗證 ====================

  it('圖片上傳 signed URL 請求使用 apiFetch（包含 Accept 與 X-XSRF-TOKEN header）', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const wrapper = mount(CaptureRecordEditForm, { props: defaultProps })

    const file = new File(['x'], 'fish.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    const { flushPromises } = await import('@vue/test-utils')
    await flushPromises()

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Accept': 'application/json',
          'X-XSRF-TOKEN': expect.any(String),
        }),
      })
    )
  })
})
