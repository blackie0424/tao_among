import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CaptureRecordSection from '@/Components/CaptureRecord/CaptureRecordSection.vue'

vi.mock('@/Components/UI/LazyImage.vue', () => ({
  default: { template: '<img />', props: ['src', 'alt'] },
}))
vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))

// #132 AC 9：同一天有多筆時，標題（日期）相同，靠地點等欄位區分。使用真實的卡片元件。
describe('同一天多筆捕獲紀錄（#132 AC 9）', () => {
  const records = [
    { id: 1, capture_date: '2024-03-05T12:00:00.000Z', location: '甲地', image_url: 'a.jpg' },
    { id: 2, capture_date: '2024-03-05T12:00:00.000Z', location: '乙地', image_url: 'b.jpg' },
    { id: 3, capture_date: '2024-02-01T12:00:00.000Z', location: '丙地', image_url: 'c.jpg' },
  ]

  const mountSection = () =>
    mount(CaptureRecordSection, {
      props: { captureRecords: records, fishName: '飛魚', fishId: 1, user: null },
    })

  it('同日兩筆的日期標題相同，各自顯示自己的地點；不同日的那筆標題不同', () => {
    const cards = mountSection().findAll('[data-testid="capture-card-title"]')
    const titles = cards.map((c) => c.text())

    expect(titles).toEqual(['2024/03/05', '2024/03/05', '2024/02/01'])
  })

  it('每張卡的地點對應自己的紀錄，且卡片不再顯示「第 N 筆紀錄」', () => {
    const wrapper = mountSection()
    const cardTexts = wrapper
      .findAll('[data-testid="capture-card-title"]')
      .map((title) => title.element.parentElement.textContent)

    expect(cardTexts[0]).toContain('甲地')
    expect(cardTexts[0]).not.toContain('乙地')
    expect(cardTexts[1]).toContain('乙地')
    expect(cardTexts[1]).not.toContain('甲地')
    expect(cardTexts[2]).toContain('丙地')
    expect(wrapper.text()).not.toMatch(/第\s*\d+\s*筆紀錄/)
  })

  it('計數為 3，與筆數一致', () => {
    expect(mountSection().get('[data-testid="capture-count"]').text()).toBe('3')
  })
})
