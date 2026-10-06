import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import TopicItemMediaEditor from '@/Components/Admin/TopicItemMediaEditor.vue'

const uploadImage = vi.fn()
vi.mock('@/composables/useImageUpload', () => ({
  useImageUpload: () => ({
    uploading: ref(false),
    imageError: ref(null),
    uploadImage,
  }),
}))

function mountEditor(media = [], extraProps = {}) {
  let wrapper
  wrapper = mount(TopicItemMediaEditor, {
    props: {
      modelValue: media,
      ...extraProps,
      'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
    },
  })
  return wrapper
}

describe('TopicItemMediaEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.stubGlobal('crypto', { randomUUID: vi.fn(() => `key-${Math.random()}`) })
  })

  it('可新增多個 YouTube 欄位並更新網址', async () => {
    const wrapper = mountEditor()
    await wrapper.get('[data-testid="add-youtube"]').trigger('click')
    await wrapper.get('[data-testid="add-youtube"]').trigger('click')
    await wrapper.get('[data-testid="youtube-url-0"]').setValue('https://youtu.be/dQw4w9WgXcQ')

    expect(wrapper.props('modelValue')).toHaveLength(2)
    expect(wrapper.props('modelValue')[0]).toMatchObject({
      type: 'youtube',
      source: 'https://youtu.be/dQw4w9WgXcQ',
    })
  })

  it('一次上傳多張圖片並依選取順序加入', async () => {
    uploadImage
      .mockResolvedValueOnce('first.jpg')
      .mockResolvedValueOnce('second.jpg')
    const wrapper = mountEditor()
    const input = wrapper.get('[data-testid="media-image-input"]')
    Object.defineProperty(input.element, 'files', {
      value: [new File(['1'], 'first.jpg'), new File(['2'], 'second.jpg')],
      configurable: true,
    })
    await input.trigger('change')
    await flushPromises()

    expect(uploadImage).toHaveBeenNthCalledWith(1, expect.any(File), { folder: 'topic-items' })
    expect(uploadImage).toHaveBeenNthCalledWith(2, expect.any(File), { folder: 'topic-items' })
    expect(wrapper.props('modelValue').map(({ type, source }) => ({ type, source }))).toEqual([
      { type: 'image', source: 'topic-items/first.jpg' },
      { type: 'image', source: 'topic-items/second.jpg' },
    ])
  })

  it('可上移、下移與刪除個別媒體', async () => {
    const wrapper = mountEditor([
      { id: 1, type: 'image', source: 'topic-items/a.jpg' },
      { id: 2, type: 'youtube', source: 'https://youtu.be/dQw4w9WgXcQ' },
      { id: 3, type: 'image', source: 'topic-items/c.jpg' },
    ])

    await wrapper.get('[data-testid="move-down-0"]').trigger('click')
    expect(wrapper.props('modelValue').map((media) => media.id)).toEqual([2, 1, 3])
    await wrapper.get('[data-testid="move-up-2"]').trigger('click')
    expect(wrapper.props('modelValue').map((media) => media.id)).toEqual([2, 3, 1])
    await wrapper.get('[data-testid="remove-media-1"]').trigger('click')
    expect(wrapper.props('modelValue').map((media) => media.id)).toEqual([2, 1])
  })

  it('顯示對應排序位置的後端驗證錯誤', () => {
    const wrapper = mountEditor(
      [{ type: 'youtube', source: 'bad-url' }],
      { errors: { 'media.0.source': 'YouTube 連結格式無法辨識。' } },
    )

    expect(wrapper.text()).toContain('YouTube 連結格式無法辨識。')
  })
})
