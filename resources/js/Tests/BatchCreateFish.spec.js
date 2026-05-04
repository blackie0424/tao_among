import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCreateFish from '@/Pages/BatchCreateFish.vue'

// ─── fishListCache mock ──────────────────────────────────────────────────────
vi.mock('@/utils/fishListCache', () => ({
  markFishCreated: vi.fn(),
}))

// ─── Inertia mock ───────────────────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
    visit: vi.fn(),
  },
  Head: { template: '<div />' },
}))

// ─── BatchCaptureImageUploader mock ─────────────────────────────────────────
vi.mock('@/Components/CaptureRecord/BatchCaptureImageUploader.vue', () => ({
  default: {
    name: 'BatchCaptureImageUploader',
    props: ['maxFiles', 'isLineApp'],
    emits: ['uploaded', 'upload-error'],
    expose: ['uploadAll', 'addFiles', 'items'],
    setup(_, { expose }) {
      const uploadAll = vi.fn()
      const addFiles = vi.fn()
      const items = []
      expose({ uploadAll, addFiles, items })
      return { uploadAll, addFiles, items }
    },
    template: '<div data-testid="mock-uploader" />',
  },
}))

// ─── FormActionBar mock ──────────────────────────────────────────────────────
vi.mock('@/Components/Global/FormActionBar.vue', () => ({
  default: {
    name: 'FormActionBar',
    props: ['title', 'goBack', 'showSubmit', 'submitNote', 'submitLabel', 'showLoading'],
    template: `
      <div>
        <span data-testid="form-title">{{ title }}</span>
        <button data-testid="submit-btn" v-if="showSubmit" @click="submitNote">{{ submitLabel }}</button>
      </div>
    `,
  },
}))

// ─── Default props ────────────────────────────────────────────────────────────
const defaultProps = {
  tribes: ['iraraley', 'imowrod'],
  capture_methods: { mamasil: '陷阱', kaanen: '釣魚' },
  upload_limits: { max_files_desktop: 10, max_files_mobile: 5 },
}

describe('BatchCreateFish', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // 模擬桌面寬度
    Object.defineProperty(window, 'innerWidth', { value: 1280, writable: true })
    Object.defineProperty(window, 'navigator', {
      value: { userAgent: 'Mozilla/5.0 (Macintosh)' },
      writable: true,
    })
  })

  // ── 渲染 ──────────────────────────────────────────────────────────────────

  it('初始渲染顯示步驟一（照片選擇區）', () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    expect(wrapper.find('[data-testid="mock-uploader"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="step-2"]').exists()).toBe(false)
  })

  it('顯示正確的頁面標題', () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    expect(wrapper.find('[data-testid="form-title"]').text()).toContain('批次新增魚類')
  })

  it('桌機時 maxFiles 使用 max_files_desktop', () => {
    Object.defineProperty(window, 'innerWidth', { value: 1280, writable: true })
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    const uploader = wrapper.findComponent({ name: 'BatchCaptureImageUploader' })
    expect(uploader.props('maxFiles')).toBe(10)
  })

  it('手機時 maxFiles 使用 max_files_mobile', () => {
    Object.defineProperty(window, 'innerWidth', { value: 375, writable: true })
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    const uploader = wrapper.findComponent({ name: 'BatchCaptureImageUploader' })
    expect(uploader.props('maxFiles')).toBe(5)
  })

  it('LINE 瀏覽器時 isLineApp 為 true', () => {
    Object.defineProperty(window, 'navigator', {
      value: { userAgent: 'Mozilla/5.0 Line/12.0.0' },
      writable: true,
    })
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    const uploader = wrapper.findComponent({ name: 'BatchCaptureImageUploader' })
    expect(uploader.props('isLineApp')).toBe(true)
  })

  // ── 步驟切換 ───────────────────────────────────────────────────────────────

  it('上傳成功後切換至步驟二（魚類資訊表單）', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo1.jpg', 'photo2.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="mock-uploader"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="step-2"]').exists()).toBe(true)
  })

  it('步驟二顯示已上傳照片數量', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo1.jpg', 'photo2.jpg', 'photo3.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="step-2"]').text()).toContain('3')
  })

  it('步驟二顯示部落選單', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="tribe-select"]').exists()).toBe(true)
  })

  it('步驟二顯示捕獲方式選單', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="capture-method-select"]').exists()).toBe(true)
  })

  it('步驟二顯示魚類名稱輸入框', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="fish-name-input"]').exists()).toBe(true)
  })

  // ── 上傳錯誤 ──────────────────────────────────────────────────────────────

  it('上傳失敗時顯示錯誤訊息', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploadError(['圖片 1 上傳失敗'])
    await nextTick()

    expect(wrapper.find('[data-testid="upload-error"]').exists()).toBe(true)
  })

  // ── 送出 ──────────────────────────────────────────────────────────────────

  it('步驟二送出時呼叫 router.post 並帶入正確路徑', async () => {
    const { router } = await import('@inertiajs/vue3')
    router.post.mockImplementation((path, data, options) => {
      options?.onSuccess?.({ props: { fish: { id: 1 } } })
    })

    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo1.jpg', 'photo2.jpg'])
    await nextTick()

    // 補上必填欄位
    wrapper.vm.fishName = 'Test Fish'
    wrapper.vm.sharedForm.tribe = 'iraraley'
    wrapper.vm.sharedForm.location = '海邊'
    wrapper.vm.sharedForm.capture_date = '2026-05-01'
    wrapper.vm.sharedForm.capture_method = 'mamasil'
    await wrapper.vm.doSubmit()

    expect(router.post).toHaveBeenCalledWith(
      '/fish/batch-create',
      expect.objectContaining({
        filenames: ['photo1.jpg', 'photo2.jpg'],
      }),
      expect.any(Object)
    )
  })

  it('名稱空白時送出使用「我不知道」', async () => {
    const { router } = await import('@inertiajs/vue3')
    router.post.mockImplementation((path, data, options) => {
      options?.onSuccess?.({ props: { fish: { id: 1 } } })
    })

    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    // 補上必填欄位
    wrapper.vm.fishName = ''
    wrapper.vm.sharedForm.tribe = 'iraraley'
    wrapper.vm.sharedForm.location = '海邊'
    wrapper.vm.sharedForm.capture_date = '2026-05-01'
    wrapper.vm.sharedForm.capture_method = 'mamasil'
    await wrapper.vm.doSubmit()

    expect(router.post).toHaveBeenCalledWith(
      '/fish/batch-create',
      expect.objectContaining({ name: '我不知道' }),
      expect.any(Object)
    )
  })

  it('送出成功後呼叫 markFishCreated 更新魚類列表快取', async () => {
    const { markFishCreated } = await import('@/utils/fishListCache')
    const { router } = await import('@inertiajs/vue3')

    router.post.mockImplementation((path, data, options) => {
      options?.onSuccess?.({ props: { fish: { id: 42 } } })
    })

    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    wrapper.vm.sharedForm.tribe = 'iraraley'
    wrapper.vm.sharedForm.location = '海邊'
    wrapper.vm.sharedForm.capture_date = '2026-05-01'
    wrapper.vm.sharedForm.capture_method = 'mamasil'
    await wrapper.vm.doSubmit()

    expect(markFishCreated).toHaveBeenCalledWith(42)
  })

  // ── 過去捕獲資訊選擇器 ──────────────────────────────────────────────────
  const recentSessions = [
    {
      tribe: 'iraraley',
      location: '溪流A',
      capture_method: 'mamasil',
      capture_date: '2024-05-01',
      record_count: 3,
    },
  ]

  it('shows CaptureRecordSessionSelector in Step 2 when recent_sessions provided', async () => {
    const wrapper = mount(BatchCreateFish, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('溪流A')
  })

  it('fills sharedForm fields when a session option is selected', async () => {
    const wrapper = mount(BatchCreateFish, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    await wrapper.find('[data-testid="session-option"]').trigger('click')
    await nextTick()

    expect(wrapper.vm.sharedForm.tribe).toBe('iraraley')
    expect(wrapper.vm.sharedForm.location).toBe('溪流A')
    expect(wrapper.vm.sharedForm.capture_date).toBe('2024-05-01')
    expect(wrapper.vm.sharedForm.capture_method).toBe('mamasil')
  })

  it('shows form fields directly when no recent_sessions provided', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })

    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tribe-select"]').exists()).toBe(true)
  })

  it('hides selector and shows form after manual option clicked', async () => {
    const wrapper = mount(BatchCreateFish, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    await wrapper.vm.onUploaded(['photo.jpg'])
    await nextTick()

    await wrapper.find('[data-testid="manual-option"]').trigger('click')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="tribe-select"]').exists()).toBe(true)
  })
})
