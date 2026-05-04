import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCreateCaptureRecord from '@/Pages/BatchCreateCaptureRecord.vue'

// ─── Inertia mock ────────────────────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
  router: {
    post: vi.fn(),
    visit: vi.fn(),
  },
}))

// ─── BatchCaptureImageUploader mock ──────────────────────────────────────────
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

// ─── FormActionBar mock ───────────────────────────────────────────────────────
vi.mock('@/Components/Global/FormActionBar.vue', () => ({
  default: {
    name: 'FormActionBar',
    props: ['title', 'goBack', 'showSubmit', 'submitNote', 'submitLabel', 'showLoading'],
    template: `<div><button data-testid="submit-btn" v-if="showSubmit" @click="submitNote">{{ submitLabel }}</button></div>`,
  },
}))

describe('BatchCreateCaptureRecord', () => {
  const defaultProps = {
    fish: {
      id: 1,
      name: '苦花',
      display_image_url: 'fish.jpg',
      image_url: 'fish.jpg',
    },
    tribes: ['ivalino', 'iranmeilek', 'iratay'],
    capture_methods: { 網捕: '網捕', 釣魚: '釣魚' },
    upload_limits: { max_files_desktop: 10, max_files_mobile: 5 },
  }

  const recentSessions = [
    {
      tribe: 'ivalino',
      location: '溪流A',
      capture_method: '網捕',
      capture_date: '2024-05-01',
      record_count: 3,
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders Step 1 by default', () => {
    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
    expect(wrapper.find('[data-testid="mock-uploader"]').exists()).toBe(true)
  })

  it('shows CaptureRecordSessionSelector in Step 2 when recent_sessions provided', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    // 模擬 Step 1 上傳完成，進入 Step 2
    await wrapper.vm.onUploaded(['file1.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('溪流A')
  })

  it('fills sharedForm fields when a session option is selected', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    await wrapper.vm.onUploaded(['file1.jpg'])
    await nextTick()

    await wrapper.find('[data-testid="session-option"]').trigger('click')
    await nextTick()

    expect(wrapper.vm.sharedForm.tribe).toBe('ivalino')
    expect(wrapper.vm.sharedForm.location).toBe('溪流A')
    expect(wrapper.vm.sharedForm.capture_date).toBe('2024-05-01')
    expect(wrapper.vm.sharedForm.capture_method).toBe('網捕')
  })

  it('shows manual form fields directly when no recent_sessions provided', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })

    await wrapper.vm.onUploaded(['file1.jpg'])
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    // 部落選單應直接顯示
    expect(wrapper.find('select').exists()).toBe(true)
  })

  it('hides selector and shows form after manual option clicked', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, {
      props: { ...defaultProps, recent_sessions: recentSessions },
    })

    await wrapper.vm.onUploaded(['file1.jpg'])
    await nextTick()

    await wrapper.find('[data-testid="manual-option"]').trigger('click')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').exists()).toBe(false)
    expect(wrapper.find('select').exists()).toBe(true)
  })
})
