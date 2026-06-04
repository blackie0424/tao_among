import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TribalClassificationForm from '@/Components/TribalClassification/TribalClassificationForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
    put: vi.fn(),
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

vi.mock('@/utils/fishListCache', () => ({
  markFishStale: vi.fn(),
}))

describe('TribalClassificationForm', () => {
  let wrapper
  const defaultProps = {
    tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
    foodCategories: ['oyod', 'rahet', '不分類', '不食用', '?'],
    processingMethods: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?'],
    fishId: 1,
    fishName: 'Test Fish',
    fishImage: 'test-image.jpg',
  }

  const classificationData = {
    id: 42,
    tribe: 'ivalino',
    food_category: 'oyod',
    processing_method: '去魚鱗',
    notes: '既有備註',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  // --- 新增模式（不傳 initialData）---

  describe('新增模式', () => {
    it('renders correctly with all required elements', () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })

      expect(wrapper.find('form').exists()).toBe(true)
      expect(wrapper.find('#tribe').exists()).toBe(true)
      expect(wrapper.find('#food_category').exists()).toBe(true)
      expect(wrapper.find('#processing_method').exists()).toBe(true)
      expect(wrapper.find('#notes').exists()).toBe(true)
    })

    it('displays fish information correctly', () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      expect(wrapper.find('img').attributes('alt')).toBe('Test Fish')
    })

    it('renders all tribe options', () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      const options = wrapper.find('#tribe').findAll('option')
      expect(options).toHaveLength(7)
      expect(options[0].text()).toBe('請選擇部落')
      expect(options[1].text()).toBe('ivalino')
    })

    it('initializes form with empty values', () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      expect(wrapper.find('#tribe').element.value).toBe('')
      expect(wrapper.find('#food_category').element.value).toBe('')
      expect(wrapper.find('#processing_method').element.value).toBe('')
      expect(wrapper.find('#notes').element.value).toBe('')
    })

    it('updates form data when user selects options', async () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })

      await wrapper.find('#tribe').setValue('iraraley')
      await wrapper.find('#food_category').setValue('oyod')
      await wrapper.find('#notes').setValue('Test notes')

      expect(wrapper.find('#tribe').element.value).toBe('iraraley')
      expect(wrapper.find('#food_category').element.value).toBe('oyod')
      expect(wrapper.find('#notes').element.value).toBe('Test notes')
    })

    it('calls router.post on submit', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(TribalClassificationForm, { props: defaultProps })

      wrapper.vm.submitForm()

      expect(router.post).toHaveBeenCalledWith(
        '/fish/1/tribal-classifications',
        expect.any(Object),
        expect.any(Object)
      )
      expect(router.put).not.toHaveBeenCalled()
    })

    it('resets form after successful submission', async () => {
      const { router } = await import('@inertiajs/vue3')
      const { flushPromises } = await import('@vue/test-utils')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      await wrapper.find('#tribe').setValue('ivalino')
      wrapper.vm.submitForm()
      await flushPromises()

      expect(wrapper.find('#tribe').element.value).toBe('')
    })

    it('emits submitted after successful submission', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.post.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      wrapper.vm.submitForm()

      expect(wrapper.emitted('submitted')).toHaveLength(1)
    })

    it('exposes submitForm method', () => {
      wrapper = mount(TribalClassificationForm, { props: defaultProps })
      expect(typeof wrapper.vm.submitForm).toBe('function')
    })
  })

  // --- 編輯模式（傳入 initialData）---

  describe('編輯模式', () => {
    it('pre-fills form with initialData values', () => {
      wrapper = mount(TribalClassificationForm, {
        props: { ...defaultProps, initialData: classificationData },
      })

      expect(wrapper.find('#tribe').element.value).toBe('ivalino')
      expect(wrapper.find('#food_category').element.value).toBe('oyod')
      expect(wrapper.find('#processing_method').element.value).toBe('去魚鱗')
      expect(wrapper.find('#notes').element.value).toBe('既有備註')
    })

    it('calls router.put with correct URL on submit', async () => {
      const { router } = await import('@inertiajs/vue3')
      wrapper = mount(TribalClassificationForm, {
        props: { ...defaultProps, initialData: classificationData },
      })

      wrapper.vm.submitForm()

      expect(router.put).toHaveBeenCalledWith(
        '/fish/1/tribal-classifications/42',
        expect.any(Object),
        expect.any(Object)
      )
      expect(router.post).not.toHaveBeenCalled()
    })

    it('does not reset form after successful edit submission', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.put.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(TribalClassificationForm, {
        props: { ...defaultProps, initialData: classificationData },
      })
      wrapper.vm.submitForm()

      expect(wrapper.find('#tribe').element.value).toBe('ivalino')
    })

    it('emits submitted after successful edit', async () => {
      const { router } = await import('@inertiajs/vue3')
      router.put.mockImplementationOnce((url, data, callbacks) => {
        callbacks.onSuccess()
      })

      wrapper = mount(TribalClassificationForm, {
        props: { ...defaultProps, initialData: classificationData },
      })
      wrapper.vm.submitForm()

      expect(wrapper.emitted('submitted')).toHaveLength(1)
    })
  })
})
