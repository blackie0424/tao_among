import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import Edit from '@/Pages/Admin/Topics/Edit.vue'
import { router } from '@inertiajs/vue3'

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

vi.mock('@/Layouts/AdminLayout.vue', () => ({
  default: {
    template: '<div><slot /></div>',
    props: ['title'],
  },
}))

const mockUploadImage = vi.fn()
const mockImagePreview = ref(null)
const mockUploading = ref(false)
const mockUploadedFilename = ref(null)
const mockImageError = ref(null)

vi.mock('@/composables/useImageUpload', () => ({
  useImageUpload: () => ({
    imagePreview: mockImagePreview,
    uploading: mockUploading,
    uploadedFilename: mockUploadedFilename,
    imageError: mockImageError,
    uploadImage: mockUploadImage,
  }),
}))

const topicWithImage = {
  id: 1,
  title: '魚類圖鑑',
  slug: 'fish-guide',
  image_path: 'topics/original.jpg',
  image_url: '/storage/topics/original.jpg',
  is_fish_category: true,
  is_published: true,
}

function mountEdit(topic = topicWithImage) {
  return mount(Edit, { props: { topic } })
}

describe('Admin/Topics/Edit.vue 圖片移除', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockImagePreview.value = null
    mockUploading.value = false
    mockUploadedFilename.value = null
    mockImageError.value = null
    mockUploadImage.mockResolvedValue('replacement.jpg')
  })

  it('只有目前有圖片時才顯示移除選項', () => {
    const withImage = mountEdit()
    const withoutImage = mountEdit({
      ...topicWithImage,
      image_path: null,
      image_url: null,
    })

    expect(withImage.find('input[name="remove_image"]').exists()).toBe(true)
    expect(withImage.text()).toContain('移除目前圖片')
    expect(withoutImage.find('input[name="remove_image"]').exists()).toBe(false)
    expect(withoutImage.text()).not.toContain('移除目前圖片')
  })

  it('勾選移除會隱藏目前圖片並在送出時帶 remove_image true', async () => {
    const wrapper = mountEdit()

    await wrapper.get('input[name="remove_image"]').setValue(true)
    expect(wrapper.find('img[alt="目前圖片"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('目前：topics/original.jpg')

    await wrapper.get('form').trigger('submit')

    expect(router.put).toHaveBeenCalledWith(
      '/admin/topics/1',
      expect.objectContaining({
        title: '魚類圖鑑',
        remove_image: true,
      }),
      expect.any(Object),
    )
  })

  it('選擇新檔案會取消移除狀態並改送新圖片', async () => {
    const wrapper = mountEdit()
    await wrapper.get('input[name="remove_image"]').setValue(true)

    const file = new File(['image'], 'replacement.jpg', { type: 'image/jpeg' })
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', {
      configurable: true,
      value: [file],
    })
    await input.trigger('change')
    await flushPromises()

    expect(wrapper.vm.form.remove_image).toBe(false)
    expect(wrapper.vm.form.image_path).toBe('topics/replacement.jpg')
  })
})
