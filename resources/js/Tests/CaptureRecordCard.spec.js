import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordCard from '@/Components/CaptureRecord/CaptureRecordCard.vue'
import { router } from '@inertiajs/vue3'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: { template: '<img />', props: ['src', 'alt'] },
}))
vi.mock('@/Components/UI/ImageRotateModal.vue', () => ({
  default: {
    template: '<div data-testid="rotate-modal" :data-open="String(open)" />',
    props: ['open'],
  },
}))
vi.mock('@/utils/fishListCache', () => ({ markFishStale: vi.fn() }))
vi.mock('@inertiajs/vue3', () => ({
  router: { put: vi.fn() },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

function mountCard({ sessionNotes = null, displayCaptureRecordId = null, imageUrl = null } = {}) {
  return mount(CaptureRecordCard, {
    props: {
      fishId: 1,
      displayCaptureRecordId,
      record: {
        id: 7,
        tribe: 'ivalino',
        location: '測試灣',
        capture_method: '釣魚',
        image_url: imageUrl,
        image_position: null,
        image_scale: null,
        notes: '單筆備註',
        session_notes: sessionNotes,
      },
    },
  })
}

const linkByText = (wrapper, text) => wrapper.findAll('a').find((a) => a.text() === text)
const buttonByText = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text)

describe('CaptureRecordCard 情境備註', () => {
  it('separately displays populated session notes', () => {
    const wrapper = mountCard({ sessionNotes: '本次出海備註' })
    expect(wrapper.text()).toContain('備註：單筆備註')
    expect(wrapper.text()).toContain('情境備註：本次出海備註')
  })

  it.each([null, ''])('does not render an empty session notes label for %j', (notes) => {
    expect(mountCard({ sessionNotes: notes }).text()).not.toContain('情境備註')
  })
})

describe('CaptureRecordCard 文字連結操作', () => {
  beforeEach(() => vi.clearAllMocks())

  it('編輯連結指向這筆紀錄的編輯頁', () => {
    expect(linkByText(mountCard(), '編輯').attributes('href')).toBe(
      '/fish/1/capture-records/7/edit'
    )
  })

  it('沒有三點選單與刪除入口', () => {
    const wrapper = mountCard({ imageUrl: '/a.jpg' })
    expect(wrapper.text()).not.toContain('刪除')
    expect(wrapper.find('[aria-haspopup="menu"]').exists()).toBe(false)
    expect(wrapper.text()).not.toContain('⋮')
  })

  it('非主圖：顯示「設為圖鑑主圖」，按下送出這筆紀錄', async () => {
    const wrapper = mountCard({ displayCaptureRecordId: 99 })
    expect(wrapper.find('[data-testid="display-image-badge"]').exists()).toBe(false)
    await buttonByText(wrapper, '設為圖鑑主圖').trigger('click')
    expect(router.put).toHaveBeenCalledWith(
      '/fish/1/display-image',
      { capture_record_id: 7 },
      expect.any(Object)
    )
  })

  it('主圖那筆：顯示「圖鑑主圖」標記，沒有設定連結', () => {
    const wrapper = mountCard({ displayCaptureRecordId: 7 })
    expect(wrapper.find('[data-testid="display-image-badge"]').text()).toBe('圖鑑主圖')
    expect(buttonByText(wrapper, '設為圖鑑主圖')).toBeUndefined()
  })

  it('有照片才有「旋轉照片」，按下開啟旋轉視窗', async () => {
    expect(buttonByText(mountCard(), '旋轉照片')).toBeUndefined()
    const wrapper = mountCard({ imageUrl: '/a.jpg' })
    expect(wrapper.get('[data-testid="rotate-modal"]').attributes('data-open')).toBe('false')
    await buttonByText(wrapper, '旋轉照片').trigger('click')
    expect(wrapper.get('[data-testid="rotate-modal"]').attributes('data-open')).toBe('true')
  })

  it('套用博物誌：沒有白卡、陰影、Tailwind 預設色', () => {
    const html = mountCard({ imageUrl: '/a.jpg', displayCaptureRecordId: 7 }).html()
    expect(html).not.toMatch(
      /(?:bg|text|border)-(?:blue|gray|green|teal|slate|emerald|amber|yellow)-\d|shadow|bg-white|rounded-(?:lg|full)/
    )
  })
})
