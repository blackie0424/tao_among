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

    it('does not emit submit when validation fails', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })

      wrapper.vm.submitForm()

      expect(wrapper.emitted('submit')).toBeFalsy()
    })

    it('shows error when note content is less than 10 characters', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('太短了')

      wrapper.vm.submitForm()
      await flushPromises()

      expect(wrapper.emitted('submit')).toBeFalsy()
      expect(wrapper.text()).toContain('知識內容至少需要 10 個字元')
    })

    it('emits submit with correct formData in create mode', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')

      wrapper.vm.submitForm()

      expect(wrapper.emitted('submit')).toBeTruthy()
      expect(wrapper.emitted('submit')[0][0]).toMatchObject({
        locate: 'ivalino',
        note_type: '生態習性',
      })
    })

    it('create mode formData 不包含 _method', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')

      wrapper.vm.submitForm()

      expect(wrapper.emitted('submit')[0][0]._method).toBeUndefined()
    })

    it('reset() 清空所有欄位', async () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      await wrapper.find('#locate').setValue('ivalino')
      await wrapper.find('#note_type').setValue('生態習性')
      await wrapper.find('#note').setValue('這條魚通常在夜間活動，習慣棲息於礁石附近。')

      wrapper.vm.reset()
      await flushPromises()

      expect(wrapper.find('#locate').element.value).toBe('')
      expect(wrapper.find('#note').element.value).toBe('')
    })

    it('exposes submitForm and reset methods', () => {
      wrapper = mount(FishNoteForm, { props: defaultProps })
      expect(typeof wrapper.vm.submitForm).toBe('function')
      expect(typeof wrapper.vm.reset).toBe('function')
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

    it('emits submit with _method PUT in edit mode', async () => {
      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()

      wrapper.vm.submitForm()

      expect(wrapper.emitted('submit')).toBeTruthy()
      expect(wrapper.emitted('submit')[0][0]).toMatchObject({
        _method: 'PUT',
        locate: 'ivalino',
        note_type: '生態習性',
      })
    })

    it('shows network error block when setNetworkError is called', async () => {
      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()

      wrapper.vm.setNetworkError('無網路連線，請檢查網路狀態後重試')
      await flushPromises()

      expect(wrapper.find('[class*="bg-red-50"]').exists()).toBe(true)
    })

    it('setErrors 顯示伺服器回傳的欄位錯誤', async () => {
      wrapper = mount(FishNoteForm, {
        props: { ...defaultProps, initialData: noteData },
      })
      await flushPromises()

      wrapper.vm.setErrors({ note: '知識內容不可包含違禁詞彙' })
      await flushPromises()

      expect(wrapper.text()).toContain('知識內容不可包含違禁詞彙')
    })
  })
})
