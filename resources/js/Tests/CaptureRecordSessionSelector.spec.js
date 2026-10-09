import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'

const { apiFetch } = vi.hoisted(() => ({ apiFetch: vi.fn() }))
vi.mock('@/utils/apiFetch', () => ({ apiFetch }))

const sessions = [
  {
    id: 7,
    capture_date: '2026-10-08',
    tribe: 'ivalino',
    capture_method: 'mamasil',
    place_id: 2,
    place_name: '測試灣',
    location_hint: null,
    record_count: 2,
  },
  {
    id: 8,
    capture_date: '2026-10-07',
    tribe: 'yayo',
    capture_method: '非自捕（見到或他人提供）',
    place_id: null,
    place_name: null,
    location_hint: null,
    record_count: 0,
  },
]

const legacyCombos = [{
  capture_date: '2026-10-01',
  tribe: 'iraraley',
  capture_method: '釣魚',
  location: null,
  record_count: 3,
}]

const defaultProps = {
  selectableSessions: sessions,
  legacyCombos,
  tribes: ['ivalino', 'yayo'],
  captureMethods: { mamasil: 'mamasil', 釣魚: '釣魚' },
}

function mountSelector(props = {}) {
  return mount(CaptureRecordSessionSelector, { props: { ...defaultProps, ...props } })
}

async function fillInlineForm(wrapper) {
  const selects = wrapper.findAll('select')
  await selects[0].setValue('ivalino')
  await selects[1].setValue('mamasil')
}

describe('CaptureRecordSessionSelector', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows real session semantics including zero and missing place', () => {
    const wrapper = mountSelector({ legacyCombos: [] })
    const options = wrapper.findAll('[data-testid="session-option"]')

    expect(options).toHaveLength(2)
    expect(options[0].text()).toContain('測試灣')
    expect(options[0].text()).toContain('2 筆')
    expect(options[1].text()).toContain('未標地點')
    expect(options[1].text()).toContain('0 筆')
    expect(wrapper.find('[data-testid="session-empty-state"]').exists()).toBe(false)
  })

  it('orders session and legacy summaries as date, method, tribe, then place', () => {
    const wrapper = mountSelector()
    const sessionText = wrapper.findAll('[data-testid="session-option"]')[0].text()
    const legacyText = wrapper.find('[data-testid="legacy-combo-option"]').text()

    expect(sessionText.indexOf('2026-10-08')).toBeLessThan(sessionText.indexOf('mamasil'))
    expect(sessionText.indexOf('mamasil')).toBeLessThan(sessionText.indexOf('ivalino'))
    expect(sessionText.indexOf('ivalino')).toBeLessThan(sessionText.indexOf('測試灣'))
    expect(legacyText.indexOf('2026-10-01')).toBeLessThan(legacyText.indexOf('釣魚'))
    expect(legacyText.indexOf('釣魚')).toBeLessThan(legacyText.indexOf('iraraley'))
    expect(legacyText.indexOf('iraraley')).toBeLessThan(legacyText.indexOf('未標地點'))
  })

  it('emits only the selected real session id', async () => {
    const wrapper = mountSelector()
    await wrapper.findAll('[data-testid="session-option"]')[0].trigger('click')
    expect(wrapper.emitted('select')[0][0]).toEqual({ session_id: 7, legacy_combo: null })
  })

  it('shows and emits a canonical legacy combo without extra fields', async () => {
    const wrapper = mountSelector({ selectableSessions: [] })
    const option = wrapper.find('[data-testid="legacy-combo-option"]')

    expect(option.text()).toContain('未標地點')
    expect(option.text()).toContain('3 筆・舊資料')
    await option.trigger('click')
    expect(wrapper.emitted('select')[0][0]).toEqual({
      session_id: null,
      legacy_combo: {
        capture_date: '2026-10-01',
        tribe: 'iraraley',
        capture_method: '釣魚',
        location: null,
      },
    })
  })

  it('offers inline creation even when both lists are empty', async () => {
    const wrapper = mountSelector({ selectableSessions: [], legacyCombos: [] })
    expect(wrapper.find('[data-testid="session-empty-state"]').text()).toContain('還沒有情境')

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    expect(wrapper.find('[data-testid="inline-session-form"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('日期')
  })

  it('prepends, selects, and renders the selectable response contract after creation', async () => {
    apiFetch.mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        session: {
          id: 99,
          capture_date: '2026-10-09',
          tribe: 'ivalino',
          capture_method: 'mamasil',
          place_id: 3,
          place_name: '新地點',
          location_hint: null,
          record_count: 0,
        },
      }),
    })
    const wrapper = mountSelector({ selectableSessions: [], legacyCombos: [] })

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    await fillInlineForm(wrapper)
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
    await Promise.resolve()

    const option = wrapper.find('[data-testid="session-option"]')
    expect(apiFetch).toHaveBeenCalledWith('/capture-sessions', expect.objectContaining({ method: 'POST' }))
    expect(option.exists()).toBe(true)
    expect(option.text()).toContain('新地點')
    expect(option.text()).toContain('0 筆')
    expect(wrapper.emitted('select')[0][0]).toEqual({ session_id: 99, legacy_combo: null })
    expect(wrapper.find('[data-testid="inline-session-form"]').exists()).toBe(false)
  })

  it('selects an already listed reused session without prepending and explains why', async () => {
    apiFetch.mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({ session: { ...sessions[0], reused: true } }),
    })
    const wrapper = mountSelector({ legacyCombos: [] })

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    await fillInlineForm(wrapper)
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
    await Promise.resolve()

    expect(wrapper.findAll('[data-testid="session-option"]')).toHaveLength(2)
    expect(wrapper.emitted('select')[0][0]).toEqual({ session_id: 7, legacy_combo: null })
    expect(wrapper.find('[data-testid="session-reused-message"]').text()).toBe('已有相同情境,已為你選取')
  })

  it('prepends a reused session absent from the source list and explains why', async () => {
    apiFetch.mockResolvedValue({
      ok: true,
      json: vi.fn().mockResolvedValue({
        session: { id: 99, capture_date: '2026-10-09', tribe: 'ivalino', capture_method: 'mamasil', place_id: 3, place_name: '新地點', location_hint: null, record_count: 0, reused: true },
      }),
    })
    const wrapper = mountSelector({ legacyCombos: [] })

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    await fillInlineForm(wrapper)
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
    await Promise.resolve()

    expect(wrapper.findAll('[data-testid="session-option"]')).toHaveLength(3)
    expect(wrapper.emitted('select')[0][0]).toEqual({ session_id: 99, legacy_combo: null })
    expect(wrapper.find('[data-testid="session-reused-message"]').text()).toBe('已有相同情境,已為你選取')
  })

  it('shows the first 422 validation error without leaving the inline form', async () => {
    apiFetch.mockResolvedValue({
      ok: false,
      json: vi.fn().mockResolvedValue({ errors: { capture_date: ['日期不可晚於今天'] } }),
    })
    const wrapper = mountSelector()

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
    await Promise.resolve()

    expect(wrapper.find('[role="alert"]').text()).toBe('日期不可晚於今天')
    expect(wrapper.find('[data-testid="inline-session-form"]').exists()).toBe(true)
  })

  it('locks synchronously so a double click sends one request', async () => {
    let resolveRequest
    apiFetch.mockReturnValue(new Promise(resolve => { resolveRequest = resolve }))
    const wrapper = mountSelector()

    await wrapper.find('[data-testid="open-inline-session-form"]').trigger('click')
    await fillInlineForm(wrapper)
    const submit = wrapper.find('[data-testid="submit-inline-session"]')
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')
    await wrapper.find('[data-testid="inline-session-form"]').trigger('submit')

    expect(apiFetch).toHaveBeenCalledTimes(1)
    expect(submit.attributes('disabled')).toBeDefined()

    resolveRequest({ ok: false, json: vi.fn().mockResolvedValue({ errors: { form: ['失敗'] } }) })
    await Promise.resolve()
  })
})
