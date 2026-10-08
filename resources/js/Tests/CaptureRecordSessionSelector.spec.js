import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'

const sessions = [
  {
    id: 7,
    capture_date: '2026-10-08',
    tribe: 'ivalino',
    capture_method: 'mamasil',
    place_name: '測試灣',
    location_hint: null,
    record_count: 2,
  },
  {
    id: 8,
    capture_date: '2026-10-07',
    tribe: 'yayo',
    capture_method: '非自捕（見到或他人提供）',
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

describe('CaptureRecordSessionSelector', () => {
  it('shows real session semantics including zero and missing place', () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { selectableSessions: sessions },
    })

    const options = wrapper.findAll('[data-testid="session-option"]')
    expect(options).toHaveLength(2)
    expect(options[0].text()).toContain('測試灣')
    expect(options[0].text()).toContain('2 筆')
    expect(options[1].text()).toContain('未標地點')
    expect(options[1].text()).toContain('0 筆')
  })

  it('emits only the selected real session id', async () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { selectableSessions: sessions },
    })

    await wrapper.findAll('[data-testid="session-option"]')[0].trigger('click')

    expect(wrapper.emitted('select')[0][0]).toEqual({ session_id: 7, legacy_combo: null })
  })

  it('shows and emits a canonical legacy combo without extra fields', async () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { legacyCombos },
    })

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

  it('shows a create-session link and no options when both lists are empty', () => {
    const wrapper = mount(CaptureRecordSessionSelector)

    expect(wrapper.find('[data-testid="session-empty-state"]').text()).toContain('還沒有情境')
    expect(wrapper.find('a').attributes('href')).toBe('/capture-sessions/create')
    expect(wrapper.findAll('[data-testid="session-option"]')).toHaveLength(0)
    expect(wrapper.findAll('[data-testid="legacy-combo-option"]')).toHaveLength(0)
  })
})