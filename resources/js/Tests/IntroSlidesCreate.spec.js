import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Create from '@/Pages/Admin/IntroSlides/Create.vue'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: {
    post: vi.fn(),
  },
}))

// Mock AdminLayout
vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
  },
}))

// Mock useImageUpload
const mockUploadImage = vi.fn()
const mockImagePreview = { value: null }
const mockUploading = { value: false }
const mockUploadedFilename = { value: null }
const mockImageError = { value: null }

vi.mock('@/composables/useImageUpload', () => ({
  useImageUpload: () => ({
    imagePreview: mockImagePreview,
    uploading: mockUploading,
    uploadedFilename: mockUploadedFilename,
    imageError: mockImageError,
    uploadImage: mockUploadImage,
  }),
}))

describe('IntroSlides Create.vue', () => {
  let wrapper

  const defaultProps = {
    categories: [
      { id: 1, name: '分類A', sort_order: 1 },
      { id: 2, name: '分類B', sort_order: 2 },
    ],
  }

  beforeEach(() => {
    vi.clearAllMocks()
    mockImagePreview.value = null
    mockUploading.value = false
    mockUploadedFilename.value = null
    mockImageError.value = null
  })

  it('clears media_path when switching from youtube to photo', async () => {
    wrapper = mount(Create, { props: defaultProps })

    // 設定 YouTube 類型和網址
    await wrapper.find('input[value="youtube"]').setValue(true)
    const youtubeInput = wrapper.find('input[type="url"]')
    await youtubeInput.setValue('https://youtube.com/watch?v=abc123')
    await flushPromises()

    // 確認 media_path 有值
    expect(wrapper.vm.form.media_path).toBe('https://youtube.com/watch?v=abc123')

    // 切換到圖片類型
    await wrapper.find('input[value="photo"]').setValue(true)
    await flushPromises()

    // 驗證 media_path 被清空
    expect(wrapper.vm.form.media_path).toBe('')
  })

  it('clears media_path when switching from photo to youtube', async () => {
    wrapper = mount(Create, { props: defaultProps })

    // 設定圖片類型和路徑
    await wrapper.find('input[value="photo"]').setValue(true)
    wrapper.vm.form.media_path = 'intro-slides/test.jpg'
    await flushPromises()

    // 確認 media_path 有值
    expect(wrapper.vm.form.media_path).toBe('intro-slides/test.jpg')

    // 切換到 YouTube 類型
    await wrapper.find('input[value="youtube"]').setValue(true)
    await flushPromises()

    // 驗證 media_path 被清空
    expect(wrapper.vm.form.media_path).toBe('')
  })

  it('clears imagePreview when switching media_type', async () => {
    wrapper = mount(Create, { props: defaultProps })
    mockImagePreview.value = 'data:image/jpeg;base64,test'

    // 從 photo 切換到 youtube
    await wrapper.find('input[value="photo"]').setValue(true)
    await wrapper.find('input[value="youtube"]').setValue(true)
    await flushPromises()

    // 驗證預覽被清空
    expect(mockImagePreview.value).toBeNull()
  })

  it('disables submit button when uploading', async () => {
    wrapper = mount(Create, { props: defaultProps })

    // 預設可以送出
    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeUndefined()

    // 設定上傳中
    mockUploading.value = true
    await flushPromises()

    // 驗證按鈕被 disabled
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('disables submit button when processing', async () => {
    wrapper = mount(Create, { props: defaultProps })

    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeUndefined()

    // 設定處理中
    wrapper.vm.processing = true
    await flushPromises()

    // 驗證按鈕被 disabled
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('disables submit button when both uploading and processing', async () => {
    wrapper = mount(Create, { props: defaultProps })

    mockUploading.value = true
    wrapper.vm.processing = true
    await flushPromises()

    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
  })
})
