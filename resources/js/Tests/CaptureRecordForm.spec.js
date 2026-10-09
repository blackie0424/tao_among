import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import CaptureRecordForm from '@/Components/CaptureRecord/CaptureRecordForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  router: { post: vi.fn() },
  usePage: () => ({ props: { auth: { user: null }, flash: {} } }),
}))
global.fetch = vi.fn()

const defaultProps = {
  tribes: ['ivalino', 'yayo'],
  capture_methods: { mamasil: 'mamasil', fishing: '釣魚' },
  fishId: 1,
  fishName: 'Test Fish',
  fishImage: 'fish.jpg',
  selectable_sessions: [{
    id: 5,
    capture_date: '2026-10-08',
    tribe: 'ivalino',
    capture_method: 'mamasil',
    place_name: '測試灣',
    location_hint: null,
    record_count: 0,
  }],
  legacy_combos: [],
}
const legacyRecord = {
  id: 1,
  session_id: null,
  tribe: 'ivalino',
  location: '溪流A',
  capture_method: 'fishing',
  capture_date: '2026-10-07T00:00:00.000000Z',
  notes: '備註',
  image_url: 'https://example.com/existing.jpg',
}

beforeEach(() => {
  vi.clearAllMocks()
  global.fetch.mockClear()
})

describe('CaptureRecordForm', () => {
  it('shows a required real-session selector after image upload', async () => {
    const wrapper = mount(CaptureRecordForm, { props: defaultProps })
    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()

    expect(wrapper.find('[data-testid="session-option"]').text()).toContain('0 筆')
    expect(wrapper.find('#tribe').exists()).toBe(false)
    expect(wrapper.find('#location').exists()).toBe(false)
  })

  it('submits only session selection, notes, and image in create mode', async () => {
    const wrapper = mount(CaptureRecordForm, { props: defaultProps })
    wrapper.vm.setPrefillImage('prefill.jpg')
    await nextTick()
    await wrapper.find('[data-testid="session-option"]').trigger('click')
    wrapper.vm.nextStep()
    await nextTick()
    await wrapper.find('#notes').setValue('新備註')
    wrapper.vm.finalSubmit()
    await nextTick()

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      session_id: 5,
      legacy_combo: null,
      notes: '新備註',
      image_filename: 'prefill.jpg',
    })
  })

  it('does not progress or submit without a selected session', async () => {
    const wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, selectable_sessions: [] },
    })
    wrapper.vm.setPrefillImage('prefill.jpg')
    wrapper.vm.nextStep()
    await nextTick()

    expect(wrapper.vm.step).toBe(2)
    expect(wrapper.text()).toContain('還沒有情境')
    expect(wrapper.text()).toContain('請選擇情境')
    expect(wrapper.emitted('submit')).toBeFalsy()
  })

  it('shows linked context as readonly text with a session edit link', async () => {
    const record = { ...legacyRecord, session_id: 19, location: null }
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record } })
    await nextTick()

    const readonly = wrapper.find('[data-testid="linked-session-fields"]')
    expect(readonly.text()).toContain('未標地點')
    expect(readonly.find('a').attributes('href')).toBe('/capture-sessions/19/edit')
    expect(wrapper.find('#tribe').exists()).toBe(false)
    expect(wrapper.find('#capture_method').exists()).toBe(false)
  })

  it('omits context fields when submitting a linked record edit', async () => {
    const wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, record: { ...legacyRecord, session_id: 19 } },
    })
    await nextTick()
    await wrapper.find('#notes').setValue('只改備註')
    wrapper.vm.submitForm()

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      notes: '只改備註',
      image_position: 'center',
      image_scale: 1,
      _method: 'PUT',
    })
  })

  it('keeps legacy record context editable and normalizes serialized dates', async () => {
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    await nextTick()

    expect(wrapper.get('#capture_date').element.value).toBe('2026-10-07')
    await wrapper.get('#tribe').setValue('yayo')
    wrapper.vm.submitForm()

    expect(wrapper.emitted('submit')[0][0]).toMatchObject({
      tribe: 'yayo',
      location: '溪流A',
      capture_method: 'fishing',
      capture_date: '2026-10-07',
      _method: 'PUT',
    })
  })

  it('keeps current image visible before replacing an edit image', () => {
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    expect(wrapper.find('img[alt="當前捕獲照片"]').exists()).toBe(true)
  })

  it('automatically uploads a selected image in edit mode', async () => {
    global.fetch
      .mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://s3.example/upload', filename: 'new.jpg' }) })
      .mockResolvedValueOnce({ ok: true })
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await flushPromises()
    expect(global.fetch).toHaveBeenCalledWith('/prefix/api/storage/signed-upload-url', expect.objectContaining({ method: 'POST' }))
    expect(global.fetch).toHaveBeenCalledWith('https://s3.example/upload', expect.objectContaining({ method: 'PUT', body: file }))
  })

  it('shows an edit upload failure and restores submit availability', async () => {
    global.fetch.mockResolvedValueOnce({ ok: false, json: async () => ({ message: '伺服器錯誤' }) })
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await flushPromises()
    expect(wrapper.text()).toContain('伺服器錯誤')
    expect(wrapper.emitted('statusChange')?.at(-1)?.[0]).toMatchObject({ canSubmit: true, uploading: false })
  })

  it('emits submit-disabled and submit-enabled states around an edit upload', async () => {
    let resolveSignedUrl
    global.fetch
      .mockImplementationOnce(() => new Promise((resolve) => {
        resolveSignedUrl = () => resolve({ ok: true, json: async () => ({ url: 'https://s3.example/upload', filename: 'new.jpg' }) })
      }))
      .mockResolvedValueOnce({ ok: true })
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    expect(wrapper.emitted('statusChange')?.[0]?.[0]).toMatchObject({ canSubmit: false, uploading: true })
    resolveSignedUrl()
    await flushPromises()
    expect(wrapper.emitted('statusChange')?.at(-1)?.[0]).toMatchObject({ canSubmit: true, uploading: false })
  })

  it('restores the current edit image after removing a selected preview', async () => {
    global.fetch
      .mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://s3.example/upload', filename: 'new.jpg' }) })
      .mockResolvedValueOnce({ ok: true })
    const wrapper = mount(CaptureRecordForm, { props: { ...defaultProps, record: legacyRecord } })
    const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 10))
    await nextTick()
    await wrapper.find('button[type="button"]').trigger('click')
    expect(wrapper.find('img[alt="當前捕獲照片"]').exists()).toBe(true)
  })

  it('rejects an incomplete unlinked record edit without emitting submit', async () => {
    const wrapper = mount(CaptureRecordForm, {
      props: { ...defaultProps, record: { ...legacyRecord, tribe: '', location: '', capture_method: '', capture_date: '' } },
    })
    wrapper.vm.submitForm()
    await nextTick()
    expect(wrapper.emitted('submit')).toBeFalsy()
    expect(wrapper.text()).toContain('請選擇部落')
    expect(wrapper.text()).toContain('請輸入地點')
    expect(wrapper.text()).toContain('請選擇日期')
    expect(wrapper.text()).toContain('請選擇捕獲方式')
  })

  it('does not advance create mode when image upload fails', async () => {
    global.fetch.mockResolvedValueOnce({ ok: false, json: async () => ({ message: '上傳失敗' }) })
    const wrapper = mount(CaptureRecordForm, { props: defaultProps })
    const file = new File(['x'], 'fish.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    wrapper.vm.nextStep()
    await flushPromises()
    expect(wrapper.vm.step).toBe(1)
    expect(wrapper.text()).toContain('上傳失敗')
    expect(wrapper.emitted('submit')).toBeFalsy()
  })
  it('uploads images through the signed upload endpoint', async () => {
    global.fetch
      .mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://s3.example/upload', filename: 'new.jpg' }) })
      .mockResolvedValueOnce({ ok: true })
    const wrapper = mount(CaptureRecordForm, { props: defaultProps })
    const file = new File(['x'], 'new.jpg', { type: 'image/jpeg' })
    const input = wrapper.find('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
    await input.trigger('change')
    wrapper.vm.nextStep()
    await flushPromises()

    expect(global.fetch).toHaveBeenCalledWith('/prefix/api/storage/signed-upload-url', expect.objectContaining({ method: 'POST' }))
  })
})