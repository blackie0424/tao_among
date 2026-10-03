import { beforeEach, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Create from '@/Pages/Admin/TopicItems/Create.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { post: vi.fn() },
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }))
vi.mock('@/Components/Admin/TopicItemMediaEditor.vue', () => ({
  default: {
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template: '<button type="button" data-testid="set-media" @click="$emit(\'update:modelValue\', [{ localKey: \'x\', type: \'youtube\', source: \'https://youtu.be/dQw4w9WgXcQ\' }, { localKey: \'y\', type: \'image\', source: \'topic-items/a.jpg\' }])">設定媒體</button>',
  },
}))

beforeEach(() => vi.clearAllMocks())

it('可不帶媒體建立純文字項目', async () => {
  const wrapper = mount(Create, { props: { topics: [{ id: 1, title: '文化' }], selectedTopicId: 1 } })
  wrapper.vm.form.title = '純文字項目'
  await wrapper.get('form').trigger('submit')

  expect(router.post).toHaveBeenCalledWith('/admin/topic-items', expect.objectContaining({ media: [] }), expect.any(Object))
})

it('依介面順序送出媒體且移除前端 localKey', async () => {
  const wrapper = mount(Create, { props: { topics: [{ id: 1, title: '文化' }], selectedTopicId: 1 } })
  await wrapper.get('[data-testid="set-media"]').trigger('click')
  await wrapper.get('form').trigger('submit')

  expect(router.post).toHaveBeenCalledWith('/admin/topic-items', expect.objectContaining({
    media: [
      { type: 'youtube', source: 'https://youtu.be/dQw4w9WgXcQ' },
      { type: 'image', source: 'topic-items/a.jpg' },
    ],
  }), expect.any(Object))
})
