import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordCard from '@/Components/CaptureRecord/CaptureRecordCard.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({ default: { template: '<img />', props: ['src', 'alt'] } }))
vi.mock('@/Components/UI/OverflowMenu.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/Components/UI/ImageRotateModal.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@inertiajs/vue3', () => ({ router: { put: vi.fn() } }))

function mountCard(sessionNotes) {
  return mount(CaptureRecordCard, {
    props: {
      fishId: 1,
      displayCaptureRecordId: null,
      record: {
        id: 1,
        tribe: 'ivalino',
        location: '測試灣',
        capture_method: '釣魚',
        image_url: null,
        image_position: null,
        image_scale: null,
        notes: '單筆備註',
        session_notes: sessionNotes,
      },
    },
  })
}

describe('CaptureRecordCard 情境備註', () => {
  it('separately displays populated session notes', () => {
    const wrapper = mountCard('本次出海備註')
    expect(wrapper.text()).toContain('備註：單筆備註')
    expect(wrapper.text()).toContain('情境備註：本次出海備註')
  })

  it.each([null, ''])('does not render an empty session notes label for %j', (notes) => {
    expect(mountCard(notes).text()).not.toContain('情境備註')
  })
})
