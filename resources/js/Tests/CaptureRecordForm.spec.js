import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordForm from '@/Components/CaptureRecordForm.vue'

// Mock Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
  },
}))

// Mock fetch for file upload
global.fetch = vi.fn()

describe('CaptureRecordForm', () => {
  let wrapper
  const defaultProps = {
    tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
    fishId: 1,
    fishName: 'Test Fish',
    fishImage: 'test-image.jpg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch.mockClear()
  })

  it('renders correctly with all required elements', () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('#image').exists()).toBe(true)
    expect(wrapper.find('#tribe').exists()).toBe(true)
    expect(wrapper.find('#location').exists()).toBe(true)
    expect(wrapper.find('#capture_method').exists()).toBe(true)
    expect(wrapper.find('#capture_date').exists()).toBe(true)
    expect(wrapper.find('#notes').exists()).toBe(true)
  })

  it('displays fish information correctly', () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    expect(wrapper.text()).toContain('正在為 Test Fish 新增捕獲紀錄')
  })

  it('renders all tribe options', () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe')
    const options = tribeSelect.findAll('option')

    expect(options).toHaveLength(7) // 6 tribes + 1 default option
    expect(options[0].text()).toBe('請選擇部落')
    expect(options[1].text()).toBe('ivalino')
  })

  it('updates form data when user fills inputs', async () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    await wrapper.find('#tribe').setValue('iraraley')
    await wrapper.find('#location').setValue('Test Beach')
    await wrapper.find('#capture_method').setValue('網捕')
    await wrapper.find('#capture_date').setValue('2024-01-15')
    await wrapper.find('#notes').setValue('Test capture notes')

    expect(wrapper.find('#tribe').element.value).toBe('iraraley')
    expect(wrapper.find('#location').element.value).toBe('Test Beach')
    expect(wrapper.find('#capture_method').element.value).toBe('網捕')
    expect(wrapper.find('#capture_date').element.value).toBe('2024-01-15')
    expect(wrapper.find('#notes').element.value).toBe('Test capture notes')
  })

  it('exposes submitForm method', () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    expect(wrapper.vm.submitForm).toBeDefined()
    expect(typeof wrapper.vm.submitForm).toBe('function')
  })
})
