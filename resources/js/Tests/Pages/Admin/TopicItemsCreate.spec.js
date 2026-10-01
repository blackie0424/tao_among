import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import Create from '@/Pages/Admin/TopicItems/Create.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { post: vi.fn() },
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('@/composables/useImageUpload', () => ({
  useImageUpload: () => ({
    imagePreview: ref(null), uploading: ref(false), uploadedFilename: ref(null), imageError: ref(null), uploadImage: vi.fn(),
  }),
}))

it('未選圖片仍可送出新增 topic-item 表單', async () => {
  const wrapper = mount(Create, { props: { topics: [{ id: 1, title: '文化' }], selectedTopicId: 1 } })
  wrapper.vm.form.title = '純文字項目'
  await wrapper.get('form').trigger('submit')

  expect(router.post).toHaveBeenCalledWith('/admin/topic-items', expect.objectContaining({
    image_path: '',
  }), expect.any(Object))
})
