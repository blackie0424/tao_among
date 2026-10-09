import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCreateCaptureRecord from '@/Pages/BatchCreateCaptureRecord.vue'
const { apiFetch } = vi.hoisted(() => ({ apiFetch: vi.fn() }))
vi.mock('@/utils/apiFetch', () => ({ apiFetch }))

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), visit: vi.fn() } }))
vi.mock('@/Components/CaptureRecord/BatchCaptureImageUploader.vue', () => ({
  default: {
    name: 'BatchCaptureImageUploader',
    props: ['maxFiles', 'isLineApp'],
    emits: ['uploaded', 'upload-error'],
    setup(_, { expose }) { expose({ uploadAll: vi.fn(), items: [] }); return {} },
    template: '<div data-testid="mock-uploader" />',
  },
}))
vi.mock('@/Components/Global/FormActionBar.vue', () => ({
  default: {
    name: 'FormActionBar',
    props: ['showSubmit', 'submitNote', 'submitLabel'],
    template: '<button v-if="showSubmit" data-testid="submit-btn" @click="submitNote">{{ submitLabel }}</button>',
  },
}))

const defaultProps = {
  fish: { id: 4, name: '測試魚', display_image_url: 'fish.jpg' },
  tribes: ['ivalino'],
  capture_methods: { mamasil: 'mamasil' },
  upload_limits: { max_files_desktop: 10, max_files_mobile: 5 },
  selectable_sessions: [{
    id: 12,
    capture_date: '2026-10-08',
    tribe: 'ivalino',
    capture_method: 'mamasil',
    place_name: null,
    location_hint: null,
    record_count: 0,
  }],
  legacy_combos: [],
}

async function advance(wrapper, files = ['one.jpg']) {
  wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).vm.$emit('uploaded', files)
  await nextTick()
}

describe('BatchCreateCaptureRecord', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    Object.defineProperty(window, 'innerWidth', { value: 1280, writable: true })
    Object.defineProperty(window, 'navigator', { value: { userAgent: 'Mozilla/5.0' }, writable: true })
  })

  it.each([
    [1280, 10],
    [375, 5],
  ])('uses the configured upload limit at viewport width %i', (width, expected) => {
    Object.defineProperty(window, 'innerWidth', { value: width, writable: true })
    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
    expect(wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).props('maxFiles')).toBe(expected)
  })

  it('identifies the LINE browser for the uploader', () => {
    Object.defineProperty(window, 'navigator', { value: { userAgent: 'Mozilla/5.0 Line/12.0.0' }, writable: true })
    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
    expect(wrapper.findComponent({ name: 'BatchCaptureImageUploader' }).props('isLineApp')).toBe(true)
  })
  it('requires a selection and shows zero-count missing-place semantics', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
    await advance(wrapper)

    expect(wrapper.find('[data-testid="session-option"]').text()).toContain('未標地點')
    expect(wrapper.find('[data-testid="session-option"]').text()).toContain('0 筆')
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)
  })

  it('posts each image with the selected session and disables submit while pending', async () => {
    const { router } = await import('@inertiajs/vue3')
    let firstOptions
    router.post.mockImplementation((_url, _data, options) => { firstOptions = options })

    const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
    await advance(wrapper, ['one.jpg', 'two.jpg'])
    await wrapper.find('[data-testid="session-option"]').trigger('click')
    await nextTick()
    await wrapper.find('[data-testid="submit-btn"]').trigger('click')
    await nextTick()

    expect(router.post).toHaveBeenCalledWith('/fish/4/capture-records', {
      image_filename: 'one.jpg',
      session_id: 12,
      legacy_combo: null,
      notes: '',
    }, expect.any(Object))
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)

    firstOptions.onSuccess()
  })

  it('shows the create-session empty state and cannot submit', async () => {
    const wrapper = mount(BatchCreateCaptureRecord, {
      props: { ...defaultProps, selectable_sessions: [] },
    })
    await advance(wrapper)

    expect(wrapper.text()).toContain('還沒有情境')
    expect(wrapper.find('[data-testid="open-inline-session-form"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="submit-btn"]').exists()).toBe(false)
  })
})
it('keeps uploaded photos after inline session creation', async () => {
  apiFetch.mockResolvedValue({
    ok: true,
    json: vi.fn().mockResolvedValue({ session: { ...defaultProps.selectable_sessions[0], id: 22, place_name: '新地點', record_count: 0 } }),
  })
  const wrapper = mount(BatchCreateCaptureRecord, { props: defaultProps })
  await advance(wrapper, ['one.jpg', 'two.jpg'])
  await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
  const selects = wrapper.findAll('select')
  await selects[0].setValue('ivalino')
  await selects[1].setValue('mamasil')
  await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
  await Promise.resolve()
  await nextTick()

  expect(wrapper.text()).toContain('2 張照片')
  expect(wrapper.find('[data-testid="session-option"]').text()).toContain('新地點')
})
