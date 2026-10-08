import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EditorHome from '@/Pages/EditorHome.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  router: { get: vi.fn() },
}))
vi.mock('@/Layouts/FishAppLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['pageTitle', 'mobileBackUrl', 'mobileBackText', 'showHeader'] } }))

const baseProps = { needAudio: [], needPhoto: [], recentEdits: [], limit: 20 }

describe('EditorHome pending places', () => {
  it('shows zero as an explicit empty state', () => {
    const wrapper = mount(EditorHome, { props: { ...baseProps, pendingPlaces: [] } })
    const section = wrapper.get('[data-testid="pending-places"]')
    expect(section.text()).toContain('待補地點 （0 筆）')
    expect(section.text()).toContain('沒有待補的情境')
  })

  it('links each pending session to its editor', () => {
    const wrapper = mount(EditorHome, { props: { ...baseProps, pendingPlaces: [
      { id: 12, capture_date: '2026-10-07', tribe: 'ivalino', capture_method: '釣魚', location_hint: '舊文字' },
    ] } })
    const section = wrapper.get('[data-testid="pending-places"]')
    expect(section.text()).toContain('待補地點 （1 筆）')
    expect(section.get('a').attributes('href')).toBe('/capture-sessions/12/edit')
  })
})
