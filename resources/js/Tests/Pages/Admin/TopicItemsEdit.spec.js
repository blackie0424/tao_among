import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import Edit from '@/Pages/Admin/TopicItems/Edit.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { put: vi.fn() },
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))

const imagePreview = ref(null)
const uploadImage = vi.fn()
vi.mock('@/composables/useImageUpload', () => ({
  useImageUpload: () => ({
    imagePreview,
    uploading: ref(false),
    uploadedFilename: ref(null),
    imageError: ref(null),
    uploadImage,
  }),
}))

const item = {
  id: 9,
  topic_id: 1,
  title: '飛魚文化',
  description: '',
  image_path: 'topic-items/original.jpg',
  image_url: '/storage/topic-items/original.jpg',
  is_published: true,
}

const mountEdit = (overrides = {}) => mount(Edit, {
  props: { item: { ...item, ...overrides }, topics: [{ id: 1, title: '文化' }] },
})

describe('Admin/TopicItems/Edit 圖片移除', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    imagePreview.value = null
    uploadImage.mockResolvedValue('replacement.jpg')
  })

  it('只有目前有圖片時顯示移除選項', () => {
    expect(mountEdit().find('input[name="remove_image"]').exists()).toBe(true)
    expect(mountEdit({ image_path: null, image_url: null }).find('input[name="remove_image"]').exists()).toBe(false)
  })

  it('勾選移除後隱藏圖片並送出 remove_image', async () => {
    const wrapper = mountEdit()
    await wrapper.get('input[name="remove_image"]').setValue(true)

    expect(wrapper.find('img[alt="目前圖片"]').exists()).toBe(false)
    await wrapper.get('form').trigger('submit')
    expect(router.put).toHaveBeenCalledWith('/admin/topic-items/9', expect.objectContaining({
      remove_image: true,
    }), expect.any(Object))
  })

  it('選新檔案會取消移除狀態', async () => {
    const wrapper = mountEdit()
    await wrapper.get('input[name="remove_image"]').setValue(true)
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'new.jpg')], configurable: true })
    await input.trigger('change')
    await flushPromises()

    expect(wrapper.vm.form.remove_image).toBe(false)
    expect(wrapper.vm.form.image_path).toBe('topic-items/replacement.jpg')
  })
})
