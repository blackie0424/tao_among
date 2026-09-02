import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Edit from '@/Pages/Admin/IntroSlides/Edit.vue'

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: {
    put: vi.fn(),
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

describe('IntroSlides Edit.vue', () => {
  let wrapper

  const defaultProps = {
    slide: {
      id: 1,
      title: '測試投影片',
      body: '測試內容',
      category_id: 1,
      media_type: 'youtube',
      media_path: 'https://youtube.com/watch?v=original',
      sort_order: 1,
      is_published: true,
    },
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
    wrapper = mount(Edit, { props: defaultProps })

    // 確認初始值是 YouTube 網址
    expect(wrapper.vm.form.media_type).toBe('youtube')
    expect(wrapper.vm.form.media_path).toBe('https://youtube.com/watch?v=original')

    // 切換到圖片類型
    await wrapper.find('input[value="photo"]').setValue(true)
    await flushPromises()

    // 驗證 media_path 被清空
    expect(wrapper.vm.form.media_path).toBe('')
  })

  it('clears media_path when switching from photo to youtube', async () => {
    const photoSlide = {
      ...defaultProps.slide,
      media_type: 'photo',
      media_path: 'intro-slides/existing.jpg',
    }
    wrapper = mount(Edit, { props: { ...defaultProps, slide: photoSlide } })

    // 確認初始值是圖片路徑
    expect(wrapper.vm.form.media_type).toBe('photo')
    expect(wrapper.vm.form.media_path).toBe('intro-slides/existing.jpg')

    // 切換到 YouTube 類型
    await wrapper.find('input[value="youtube"]').setValue(true)
    await flushPromises()

    // 驗證 media_path 被清空
    expect(wrapper.vm.form.media_path).toBe('')
  })

  it('clears imagePreview when switching media_type', async () => {
    wrapper = mount(Edit, { props: defaultProps })
    mockImagePreview.value = 'data:image/jpeg;base64,test'

    // 從 youtube 切換到 photo
    await wrapper.find('input[value="photo"]').setValue(true)
    await flushPromises()

    // 驗證預覽被清空
    expect(mockImagePreview.value).toBeNull()
  })

  it('disables submit button when uploading', async () => {
    wrapper = mount(Edit, { props: defaultProps })

    // 預設可以送出
    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeFalsy()

    // 設定上傳中
    mockUploading.value = true
    await flushPromises()

    // 驗證按鈕被 disabled
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('disables submit button when processing', async () => {
    wrapper = mount(Edit, { props: defaultProps })

    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeFalsy()

    // 設定處理中
    wrapper.vm.processing = true
    await flushPromises()

    // 驗證按鈕被 disabled
    expect(submitButton.attributes('disabled')).toBeDefined()
  })

  it('disables submit button when both uploading and processing', async () => {
    wrapper = mount(Edit, { props: defaultProps })

    mockUploading.value = true
    wrapper.vm.processing = true
    await flushPromises()

    const submitButton = wrapper.find('button[type="submit"]')
    expect(submitButton.attributes('disabled')).toBeDefined()
  })
})
