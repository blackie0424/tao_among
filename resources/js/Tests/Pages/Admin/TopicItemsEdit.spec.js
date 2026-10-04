import { beforeEach, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Edit from '@/Pages/Admin/TopicItems/Edit.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { put: vi.fn() },
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('@/Components/Admin/TopicItemMediaEditor.vue', () => ({
  default: {
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template: '<div><span data-testid="media-count">{{ modelValue.length }}</span><button type="button" data-testid="replace-media" @click="$emit(\'update:modelValue\', [modelValue[1]])">保留第二筆</button></div>',
  },
}))

const item = {
  id: 9,
  topic_id: 1,
  title: '飛魚文化',
  description: '',
  is_published: true,
  media: [
    { id: 10, type: 'image', source: 'topic-items/a.jpg', image_url: '/a.jpg' },
    { id: 11, type: 'youtube', source: 'dQw4w9WgXcQ', youtube_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' },
  ],
}

beforeEach(() => vi.clearAllMocks())

it('載入既有多媒體並將 YouTube ID 轉成可編輯網址', () => {
  const wrapper = mount(Edit, { props: { item, topics: [{ id: 1, title: '文化' }] } })

  expect(wrapper.get('[data-testid="media-count"]').text()).toBe('2')
  expect(wrapper.vm.form.media[1].source).toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
})

it('刪除個別媒體後只送出保留項目及其 id', async () => {
  const wrapper = mount(Edit, { props: { item, topics: [{ id: 1, title: '文化' }] } })
  await wrapper.get('[data-testid="replace-media"]').trigger('click')
  await wrapper.get('form').trigger('submit')

  expect(router.put).toHaveBeenCalledWith('/admin/topic-items/9', expect.objectContaining({
    media: [{ id: 11, type: 'youtube', source: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' }],
  }), expect.any(Object))
})
