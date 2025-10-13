import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import TribalClassificationForm from '@/Components/TribalClassificationForm.vue'

// Mock Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
  },
}))

describe('TribalClassificationForm', () => {
  let wrapper
  const defaultProps = {
    tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
    foodCategories: ['oyod', 'rahet', '不分類', '不食用', '?', ''],
    processingMethods: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''],
    fishId: 1,
    fishName: 'Test Fish',
    fishImage: 'test-image.jpg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders correctly with all required elements', () => {
    wrapper = mount(TribalClassificationForm, {
      props: defaultProps,
    })

    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('#tribe').exists()).toBe(true)
    expect(wrapper.find('#food_category').exists()).toBe(true)
    expect(wrapper.find('#processing_method').exists()).toBe(true)
    expect(wrapper.find('#notes').exists()).toBe(true)
  })

  it('displays fish information correctly', () => {
    wrapper = mount(TribalClassificationForm, {
      props: defaultProps,
    })

    expect(wrapper.text()).toContain('正在為 Test Fish 新增部落分類')
  })

  it('renders all tribe options', () => {
    wrapper = mount(TribalClassificationForm, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe')
    const options = tribeSelect.findAll('option')

    expect(options).toHaveLength(7) // 6 tribes + 1 default option
    expect(options[0].text()).toBe('請選擇部落')
    expect(options[1].text()).toBe('ivalino')
  })

  it('updates form data when user selects options', async () => {
    wrapper = mount(TribalClassificationForm, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe')
    await tribeSelect.setValue('iraraley')

    const foodCategorySelect = wrapper.find('#food_category')
    await foodCategorySelect.setValue('oyod')

    const notesTextarea = wrapper.find('#notes')
    await notesTextarea.setValue('Test notes')

    expect(tribeSelect.element.value).toBe('iraraley')
    expect(foodCategorySelect.element.value).toBe('oyod')
    expect(notesTextarea.element.value).toBe('Test notes')
  })

  it('exposes submitForm method', () => {
    wrapper = mount(TribalClassificationForm, {
      props: defaultProps,
    })

    expect(wrapper.vm.submitForm).toBeDefined()
    expect(typeof wrapper.vm.submitForm).toBe('function')
  })
})
