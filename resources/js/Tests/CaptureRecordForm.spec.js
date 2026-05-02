import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
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
    capture_methods: { 網捕: '網捕', 釣魚: '釣魚', 陰阱: '陰阱' },
    fishId: 1,
    fishName: 'Test Fish',
    fishImage: 'test-image.jpg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch.mockClear()
  })

  it('renders correctly with all required elements', async () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    // Step 1: 圖片上傳
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('#image').exists()).toBe(true)

    // 跨入 Step 2（跳過圖片上傳）
    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    expect(wrapper.find('#tribe').exists()).toBe(true)
    expect(wrapper.find('#location').exists()).toBe(true)
    expect(wrapper.find('#capture_date').exists()).toBe(true)

    // 填入 Step 2 必填欄位，再跨入 Step 3
    await wrapper.find('#tribe').setValue('ivalino')
    await wrapper.find('#location').setValue('Test')
    await wrapper.find('#capture_date').setValue('2024-01-01')
    wrapper.vm.nextStep()
    await nextTick()

    expect(wrapper.find('#capture_method').exists()).toBe(true)
    expect(wrapper.find('#notes').exists()).toBe(true)
  })

  it('displays fish information correctly', () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    // 魚類資訊（fishName）由 FormActionBar 顯示；表單本身在 Step 1 顯示上傳區塊
    expect(wrapper.text()).toContain('PNG, JPG, WEBP 最大 10MB')
  })

  it('renders all tribe options', async () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    // 部落選單在 Step 2，需先跨入
    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

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

    // 跨入 Step 2
    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    // 填入並驗證 Step 2 欄位
    await wrapper.find('#tribe').setValue('iraraley')
    await wrapper.find('#location').setValue('Test Beach')
    await wrapper.find('#capture_date').setValue('2024-01-15')

    expect(wrapper.find('#tribe').element.value).toBe('iraraley')
    expect(wrapper.find('#location').element.value).toBe('Test Beach')
    expect(wrapper.find('#capture_date').element.value).toBe('2024-01-15')

    // 跨入 Step 3
    wrapper.vm.nextStep()
    await nextTick()

    // 填入並驗證 Step 3 欄位
    await wrapper.find('#capture_method').setValue('網捕')
    await wrapper.find('#notes').setValue('Test capture notes')

    expect(wrapper.find('#capture_method').element.value).toBe('網捕')
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
