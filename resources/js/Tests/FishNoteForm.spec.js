import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import FishNoteForm from '@/Components/FishNote/FishNoteForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
  },
  usePage: () => ({
    props: {
      auth: { user: null },
      fish: null,
      storageFolders: { image: 'images', webp: 'webp' },
      flash: {},
    },
  }),
}))

describe('FishNoteForm', () => {
  let wrapper
  const defaultProps = {
    tribes: ['ivalino', 'iranmeilek', 'imowrod'],
    noteTypes: ['生態習性', '捕獲技巧', '文化意義'],
    fishId: 1,
    fishName: 'Test Fish',
    fishImage: 'test-image.jpg',
  }

  const noteData = {
    id: 99,
    locate: 'ivalino',
    note_type: '生態習性',
    note: '這條魚通常在夜間活動，喜歡棲息在礁石附近的水域。',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  // --- 新增模式（不傳 initialData）---

  describe('新增模式', () => {
    it('renders all form fields', () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })

      expect(wrapper.find('#locate').exists()).toBe(true)
      expect(wrapper.find('#note_type').exists()).toBe(true)
      expect(wrapper.find('#note').exists()).toBe(true)
    })

    it('initializes form with empty values', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await flushPromises()

      expect(wrapper.find('#locate').element.value).toBe('')
      expect(wrapper.find('#note_type').element.value).toBe('')
      expect(wrapper.find('#note').element.value).toBe('')
    })

    it('renders tribe options correctly', () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      const options = wrapper.find('#locate').findAll('option')

      expect(options).toHaveLength(4) // 3 tribes + 1 default
      expect(options[0].text()).toBe('請選擇部落')
    })

    it('shows character count for note field', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#note').setValue('測試內容')

      expect(wrapper.text()).toContain('/2000')
    })

    it('does not submit when validation fails', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(FishNoteForm, { props: defaultProps })

      wrapper.vm.submitForm()

      expect(router.post).not.toHaveBeenCalled()
    })

    it('shows error when note content is less than 10 characters', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('太短了')

      wrapper.vm.submitForm()
      await flushPromises()

      expect(router.post).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('知識內容至少需要 10 個字元')
    })

    it('calls router.post to create endpoint when valid', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')

      wrapper.vm.submitForm()

      expect(router.post).toHaveBeenCalledWith(
        '/fish/1/knowledge',
        expect.objectContaining({ locate: 'ivalino', note_type: '生態習性' }),
        expect.any(Object)
      )
    })

    it('does not include _method in create mode payload', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')

      wrapper.vm.submitForm()

      const payload = router.post.mock.calls[0][1]
      expect(payload._method).toBeUndefined()
    })

    it('resets form after successful creation', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')
      wrapper.vm.submitForm()
      await flushPromises()

      expect(wrapper.find('#locate').element.value).toBe('')
      expect(wrapper.find('#note').element.value).toBe('')
    })

    it('emits submitted after successful creation', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')
      wrapper.vm.submitForm()

      expect(wrapper.emitted('submitted')).toHaveLength(1)
    })

    it('does not emit statusChange in create mode', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')
      wrapper.vm.submitForm()

      expect(wrapper.emitted('statusChange')).toBeUndefined()
    })

    it('exposes submitForm method', () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      expect(typeof wrapper.vm.submitForm).toBe('function')
    })
  })

  // --- 編輯模式（傳入 initialData）---

  describe('編輯模式', () => {
    it('pre-fills form with initialData values after mount', async () => {
      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()

      expect(wrapper.find('#locate').element.value).toBe('ivalino')
      expect(wrapper.find('#note_type').element.value).toBe('生態習性')
      expect(wrapper.find('#note').element.value).toBe(noteData.note)
    })

    it('calls router.post to edit endpoint with _method PUT', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()

      wrapper.vm.submitForm()

      expect(router.post).toHaveBeenCalledWith(
        '/fish/1/knowledge/99',
        expect.objectContaining({ _method: 'PUT' }),
        expect.any(Object)
      )
    })

    it('does not reset form after successful edit', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()
      wrapper.vm.submitForm()

      expect(wrapper.find('#locate').element.value).toBe('ivalino')
    })

    it('emits statusChange when submitting in edit mode', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()
      wrapper.vm.submitForm()

      expect(wrapper.emitted('statusChange')).toBeTruthy()
      expect(wrapper.emitted('statusChange')[0][0]).toEqual({
        canSubmit: false,
        processing: true,
      })
    })

    it('shows network error block on submit failure', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        // Simulate offline
        vi.stubGlobal('navigator', { onLine: false })
        callbacks.onError({})
        vi.unstubAllGlobals()
      })

      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()
      wrapper.vm.submitForm()
      await flushPromises()

      expect(wrapper.find('[class*="bg-red-50"]').exists()).toBe(true)
    })
  })
})
