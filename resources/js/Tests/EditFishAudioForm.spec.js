import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import EditFishAudioForm from '@/Components/FishAudio/EditFishAudioForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn(), put: vi.fn() },
  usePage: () => ({
    props: { auth: { user: null }, flash: {} },
  }),
}))

global.fetch = vi.fn()

const defaultProps = {
  audio: {
    id: 1,
    name: '原住民語發音',
    audio_filename: 'test.mp3',
  },
  fishId: 1,
  fishName: 'Test Fish',
  fishImage: 'test-image.jpg',
}

describe('EditFishAudioForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders form correctly', () => {
    const wrapper = mount(EditFishAudioForm, { props: defaultProps })
    expect(wrapper.find('form').exists()).toBe(true)
  })

  // ==================== 上傳 header 驗證 ====================

  it('音訊上傳 signed URL 請求使用 apiFetch（包含 Accept 與 X-XSRF-TOKEN header）', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded.mp3' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const wrapper = mount(EditFishAudioForm, { props: defaultProps })

    // 使用合法的 audio MIME type 才能通過元件內部的 audioFile 驗證
    const file = new File(['x'], 'test.mp3', { type: 'audio/mpeg' })
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
