import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'

describe('CaptureRecordSessionSelector', () => {
  const defaultSessions = [
    {
      tribe: 'ivalino',
      location: '溪流A',
      capture_method: '網捕',
      capture_date: '2024-05-01',
      record_count: 3,
    },
    {
      tribe: 'iranmeilek',
      location: '水庫B',
      capture_method: '釣魚',
      capture_date: '2024-04-15',
      record_count: 1,
    },
  ]

  it('renders a list of session options', () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: defaultSessions },
    })

    expect(wrapper.text()).toContain('溪流A')
    expect(wrapper.text()).toContain('水庫B')
  })

  it('displays capture_date, tribe, location, capture_method and record_count', () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: defaultSessions },
    })

    expect(wrapper.text()).toContain('2024-05-01')
    expect(wrapper.text()).toContain('ivalino')
    expect(wrapper.text()).toContain('網捕')
    expect(wrapper.text()).toContain('3')
  })

  it('emits select event with session data when an option is clicked', async () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: defaultSessions },
    })

    const buttons = wrapper.findAll('[data-testid="session-option"]')
    expect(buttons.length).toBe(2)

    await buttons[0].trigger('click')

    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')[0][0]).toEqual(defaultSessions[0])
  })

  it('renders a manual input option', () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: defaultSessions },
    })

    expect(wrapper.find('[data-testid="manual-option"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('手動填寫')
  })

  it('emits select with null when manual option is clicked', async () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: defaultSessions },
    })

    await wrapper.find('[data-testid="manual-option"]').trigger('click')

    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')[0][0]).toBeNull()
  })

  it('renders correctly with empty sessions', () => {
    const wrapper = mount(CaptureRecordSessionSelector, {
      props: { sessions: [] },
    })

    expect(wrapper.find('[data-testid="manual-option"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-testid="session-option"]').length).toBe(0)
  })
})
