import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import CaptureRecordForm from '@/Components/CaptureRecord/CaptureRecordForm.vue'

// Mock Inertia router
vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn() },
  usePage: () => ({ props: { auth: { user: null }, flash: {} } }),
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

  it('shows CaptureRecordSessionSelector in Step 2 when recent_sessions provided', async () => {
    const recentSessions = [
      {
        tribe: 'ivalino',
        location: '溪流A',
        capture_method: '網捕',
        capture_date: '2024-05-01',
        record_count: 3,
      },
    ]

    wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('溪流A')
  })

  it('fills form fields when a session option is selected', async () => {
    const recentSessions = [
      {
        tribe: 'iranmeilek',
        location: '水庫B',
        capture_method: '釣魚',
        capture_date: '2024-04-15',
        record_count: 1,
      },
    ]

    wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    await wrapper.find('[data-testid="session-option"]').trigger('click')
    await nextTick()

    expect(wrapper.find('#tribe').element.value).toBe('iranmeilek')
    expect(wrapper.find('#location').element.value).toBe('水庫B')
    expect(wrapper.find('#capture_date').element.value).toBe('2024-04-15')
  })

  it('hides selector and shows manual form when manual option clicked', async () => {
    const recentSessions = [
      {
        tribe: 'ivalino',
        location: '溪流A',
        capture_method: '網捕',
        capture_date: '2024-05-01',
        record_count: 3,
      },
    ]

    wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    // 初始：selector 可見，表單欄位隱藏
    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(true)
    expect(wrapper.find('#tribe').exists()).toBe(false)

    // 點選手動填寫後：selector 隱藏，表單欄位顯示
    await wrapper.find('[data-testid="manual-option"]').trigger('click')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    expect(wrapper.find('#tribe').exists()).toBe(true)
  })

  it('shows form fields directly when no recent_sessions provided', async () => {
    wrapper = mount(CaptureRecordForm, {
      props: defaultProps,
    })

    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    expect(wrapper.find('#tribe').exists()).toBe(true)
  })

  // ==================== 上傳使用 apiFetch ====================

  // ==================== Edit mode ====================

  describe('edit mode（傳入 record prop）', () => {
    const editProps = {
      ...defaultProps,
      record: {
        id: 1,
        tribe: 'ivalino',
        location: '溪流A',
        capture_method: '網捕',
        capture_date: '2024-05-01',
        notes: '備註',
        image_url: 'https://example.com/existing.jpg',
      },
    }

    it('有 record prop 時不顯示 wizard 步驟，直接顯示所有欄位', async () => {
      const wrapper = mount(CaptureRecordForm, { props: editProps })
      // 不需要 setPrefillImage，所有欄位應直接可見
      expect(wrapper.find('#tribe').exists()).toBe(true)
      expect(wrapper.find('#location').exists()).toBe(true)
      expect(wrapper.find('#capture_method').exists()).toBe(true)
      expect(wrapper.find('#capture_date').exists()).toBe(true)
      expect(wrapper.find('#notes').exists()).toBe(true)
    })

    it('有 record prop 時以 record 資料預填欄位', async () => {
      const wrapper = mount(CaptureRecordForm, { props: editProps })
      await nextTick()
      expect(wrapper.find('#tribe').element.value).toBe('ivalino')
      expect(wrapper.find('#location').element.value).toBe('溪流A')
      expect(wrapper.find('#capture_method').element.value).toBe('網捕')
      expect(wrapper.find('#notes').element.value).toBe('備註')
    })

    it('edit mode 的 submitForm 送出 PUT（含 _method: PUT）', async () => {
      const { router } = await import('@inertiajs/vue3')
      const wrapper = mount(CaptureRecordForm, { props: editProps })

      wrapper.vm.submitForm()
      await nextTick()

      expect(router.post).toHaveBeenCalledWith(
        expect.stringContaining('/capture-records/1'),
        expect.objectContaining({ _method: 'PUT' }),
        expect.any(Object)
      )
    })

    it('edit mode 圖片上傳在選檔後自動觸發（autoUpload）', async () => {
      global.fetch
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({ url: 'https://s3.example.com/upload', filename: 'new.jpg' }),
        })
        .mockResolvedValueOnce({ ok: true })

      const wrapper = mount(CaptureRecordForm, { props: editProps })
      const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
      const input = wrapper.find('input[type="file"]')
      Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
      await input.trigger('change')
      const { flushPromises } = await import('@vue/test-utils')
      await flushPromises()

      expect(global.fetch).toHaveBeenCalledWith(
        '/prefix/api/storage/signed-upload-url',
        expect.objectContaining({ method: 'POST' })
      )
    })
  })

  it('圖片上傳透過 apiFetch 取得 signed URL（含 Accept 與 XSRF-TOKEN header）', async () => {
    // 先設定 signed URL 回應，再設定 S3 PUT 回應
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    wrapper = mount(CaptureRecordForm, { props: defaultProps })

    // 透過 file input change 觸發 handleImageChange，設定 form.image
    const file = new File(['x'], 'fish.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await nextTick()

    // 呼叫 nextStep 觸發 uploadImage → apiFetch → global.fetch
    wrapper.vm.nextStep()
    const { flushPromises } = await import('@vue/test-utils')
    await flushPromises()

    // apiFetch 內部呼叫 global.fetch，第一次呼叫是 signed URL 請求
    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({
          'Accept': 'application/json',
          'X-XSRF-TOKEN': expect.any(String),
        }),
      })
    )
  })
})
