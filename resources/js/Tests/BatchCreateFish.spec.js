import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCreateFish from '@/Pages/BatchCreateFish.vue'
const { apiFetch } = vi.hoisted(() => ({ apiFetch: vi.fn() }))
vi.mock('@/utils/apiFetch', () => ({ apiFetch }))

vi.mock('@/utils/fishListCache', () => ({ markFishCreated: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn(), visit: vi.fn() },
  Head: { template: '<div />' },
}))
vi.mock('@/Components/CaptureRecord/BatchCaptureImageUploader.vue', () => ({
  default: {
    name: 'BatchCaptureImageUploader',
    props: ['maxFiles', 'isLineApp'],
    emits: ['uploaded', 'upload-error'],
    setup(_, { expose }) {
      expose({ uploadAll: vi.fn(), items: [] })
      return {}
    },
    template: '<div data-testid="mock-uploader" />',
  },
}))
vi.mock('@/Components/Global/FormActionBar.vue', () => ({
  default: {
    name: 'FormActionBar',
    props: ['title', 'showSubmit', 'submitNote', 'submitLabel', 'showLoading'],
    template: '<div><span data-testid="form-title">{{ title }}</span><button v-if="showSubmit" data-testid="submit-btn" @click="submitNote">{{ submitLabel }}</button></div>',
  },
}))

const session = {
  id: 9,
  capture_date: '2026-10-08',
  tribe: 'ivalino',
  capture_method: 'mamasil',
  place_name: '測試灣',
  location_hint: null,
  record_count: 0,
}
const defaultProps = {
  tribes: ['ivalino'],
  capture_methods: { mamasil: 'mamasil' },
  upload_limits: { max_files_desktop: 10, max_files_mobile: 5 },
  selectable_sessions: [session],
  legacy_combos: [],
}

async function advanceToSelection(wrapper, filenames = ['one.jpg']) {
  wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).vm.$emit('uploaded', filenames)
  await nextTick()
}

describe('BatchCreateFish', () => {
  beforeEach(async () => {
    vi.clearAllMocks()
    const { router } = await import('@inertiajs/vue3')
    router.post.mockReset()
    Object.defineProperty(window, 'innerWidth', { value: 1280, writable: true })
    Object.defineProperty(window, 'navigator', { value: { userAgent: 'Mozilla/5.0' }, writable: true })
  })

  it('shows upload first and the required session selector after upload', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    expect(wrapper.find('[data-testid="mock-uploader"]').exists()).toBe(true)

    await advanceToSelection(wrapper, ['one.jpg', 'two.jpg'])
    expect(wrapper.find('[data-testid="step-2"]').text()).toContain('2 張照片')
    expect(wrapper.find('[data-testid="session-option"]').text()).toContain('0 筆')
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)
  })

  it.each([
    [1280, 10],
    [375, 5],
  ])('uses the configured upload limit at viewport width %i', (width, expected) => {
    Object.defineProperty(window, 'innerWidth', { value: width, writable: true })
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    expect(wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).props('maxFiles')).toBe(expected)
  })

  it('marks the created fish in the list cache after a successful response', async () => {
    const { markFishCreated } = await import('@/utils/fishListCache')
    const { router } = await import('@inertiajs/vue3')
    router.post.mockImplementation((_url, _data, options) => options.onSuccess({ props: { fish: { id: 42 } } }))
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await advanceToSelection(wrapper)
    await wrapper.find('[data-testid="session-option"]').trigger('click')
    wrapper.vm.doSubmit()
    await nextTick()
    expect(markFishCreated).toHaveBeenCalledWith(42)
  })

  it('identifies the LINE browser for the uploader', () => {
    Object.defineProperty(window, 'navigator', { value: { userAgent: 'Mozilla/5.0 Line/12.0.0' }, writable: true })
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    expect(wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).props('isLineApp')).toBe(true)
  })
  it('submits only the selected session contract and disables submit while pending', async () => {
    const { router } = await import('@inertiajs/vue3')
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    await advanceToSelection(wrapper, ['one.jpg', 'two.jpg'])
    await wrapper.find('[data-testid="session-option"]').trigger('click')
    await nextTick()

    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(true)
    await wrapper.find('[data-testid="submit-btn"]').trigger('click')
    await nextTick()

    expect(router.post).toHaveBeenCalledWith('/fish/batch-create', {
      name: '我不知道',
      filenames: ['one.jpg', 'two.jpg'],
      session_id: 9,
      legacy_combo: null,
      notes: '',
    }, expect.any(Object))
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)
  })

  it('keeps submit disabled when both session lists are empty', async () => {
    const wrapper = mount(BatchCreateFish, {
      props: { ...defaultProps, selectable_sessions: [] },
    })
    await advanceToSelection(wrapper)

    expect(wrapper.text()).toContain('還沒有情境')
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)
  })

  it('shows upload failures', async () => {
    const wrapper = mount(BatchCreateFish, { props: defaultProps })
    wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).vm.$emit('upload-error', ['圖片失敗'])
    await nextTick()
    expect(wrapper.find('[data-testid="upload-error"]').text()).toContain('圖片失敗')
  })
})
it('keeps uploaded photos and fish name after inline session creation', async () => {
  apiFetch.mockResolvedValue({
    ok: true,
    json: vi.fn().mockResolvedValue({ session: { ...session, id: 21, place_name: '新地點', record_count: 0 } }),
  })
  const wrapper = mount(BatchCreateFish, { props: defaultProps })
  await advanceToSelection(wrapper, ['one.jpg', 'two.jpg'])
  await wrapper.find('[data-testid="fish-name-input"]').setValue('飛魚')
  await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
  const selects = wrapper.findAll('select')
  await selects[0].setValue('ivalino')
  await selects[1].setValue('mamasil')
  await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
  await Promise.resolve()
  await nextTick()

  expect(wrapper.find('[data-testid="fish-name-input"]').element.value).toBe('飛魚')
  expect(wrapper.find('[data-testid="step-2"]').text()).toContain('2 張照片')
  expect(wrapper.find('[data-testid="session-option"]').text()).toContain('新地點')
})
