import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Index from '@/Pages/CaptureSessions/Index.vue'

vi.mock('@inertiajs/vue3', () => ({ Head: { template: '<div />' }, Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as'] } }))
vi.mock('@/Layouts/FishAppLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['pageTitle', 'mobileBackUrl', 'mobileBackText'] } }))

describe('CaptureSessions/Index', () => {
  it('shows place, hint fallback, zero count and independent delete eligibility', () => {
    const wrapper = mount(Index, { props: { sessions: { data: [
      { id: 1, capture_date: '2026-10-01', tribe: 'ivalino', capture_method: '釣魚', place: { name: '東清灣' }, location_hint: null, record_count: 2, can_delete: false },
      { id: 2, capture_date: '2026-10-02', tribe: 'yayo', capture_method: '網捕', place: null, location_hint: '舊地點', record_count: 0, can_delete: false },
      { id: 3, capture_date: '2026-10-03', tribe: 'iratay', capture_method: '魚叉', place: null, location_hint: null, record_count: 0, can_delete: true },
    ] } } })
    expect(wrapper.text()).toContain('東清灣')
    expect(wrapper.text()).toContain('地點未填（舊紀錄：舊地點）')
    expect(wrapper.text()).toContain('地點未填')
    expect(wrapper.text()).toContain('2 筆')
    expect(wrapper.text()).toContain('0 筆')
    expect(wrapper.text()).toContain('已有紀錄（含已刪除），不能刪除')
    expect(wrapper.findAll('a').filter(link => link.attributes('href') === '/capture-sessions/3')).toHaveLength(1)
    for (const actions of wrapper.findAll('[data-testid="session-actions"]')) {
      expect(actions.classes()).toEqual(expect.arrayContaining(['flex', 'items-center', 'gap-3']))
      for (const action of actions.findAll('a')) {
        expect(action.classes()).toEqual(expect.arrayContaining(['px-2', 'py-1']))
      }
    }
  })
})
