import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}))

import Tribe from '@/Pages/Island/Tribe.vue'

const fishCard = (overrides) => ({
  id: 1,
  name: '浪人鰺',
  image_url: 'https://example.test/1.webp',
  responder_count: 0,
  ...overrides,
})

const mountPage = (props = {}) =>
  mount(Tribe, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribe: 'iratay',
      fish: [],
      page: 1,
      has_more_pages: false,
      empty_reason: null,
      ...props,
    },
  })

const cards = (wrapper) => wrapper.findAll('[data-testid="fish-card"]')

describe('Island/Tribe 部落頁', () => {
  it('標題用「族語（中文）」顯示部落', () => {
    expect(mountPage().find('h1').text()).toBe('iratay（漁人）')
  })

  it('魚卡顯示編號、魚名與照片', () => {
    const card = cards(mountPage({ fish: [fishCard({ id: 7, name: '浪人鰺' })] }))[0]

    expect(card.text()).toContain('浪人鰺')
    expect(card.text()).toContain('#7')
    expect(card.find('img').attributes('src')).toBe('https://example.test/1.webp')
  })

  it('沒有正式名字的魚顯示「還沒有名字」', () => {
    const card = cards(mountPage({ fish: [fishCard({ name: null })] }))[0]

    expect(card.text()).toContain('還沒有名字')
    expect(card.text()).not.toContain('我不知道')
  })

  it('回答人數顯示「N 人回答過」，0 人顯示「還沒有人回答」不顯示 0', () => {
    const wrapper = mountPage({
      fish: [fishCard({ id: 1, responder_count: 3 }), fishCard({ id: 2, responder_count: 0 })],
    })
    const [answered, empty] = cards(wrapper)

    expect(answered.text()).toContain('3 人回答過')
    expect(empty.text()).toContain('還沒有人回答')
    expect(empty.text()).not.toContain('0 人')
  })

  it('沒有可顯示的魚：顯示「目前沒有魚」，不顯示「沒有更多了」', () => {
    const wrapper = mountPage({ page: 2, empty_reason: 'no_fish' })

    expect(wrapper.text()).toContain('目前沒有魚')
    expect(wrapper.text()).not.toContain('沒有更多了')
  })

  it('有魚但頁碼超過最後一頁：顯示「沒有更多了」', () => {
    const wrapper = mountPage({ page: 3, empty_reason: 'no_more' })

    expect(wrapper.text()).toContain('沒有更多了')
    expect(wrapper.text()).not.toContain('目前沒有魚')
  })

  it('還有下一頁時有「下一頁」連到下一個頁碼；第 2 頁起有「上一頁」', () => {
    const wrapper = mountPage({ fish: [fishCard()], page: 2, has_more_pages: true })

    expect(wrapper.find('[data-testid="next-page"]').attributes('href')).toBe(
      '/island/tribes/iratay?page=3'
    )
    expect(wrapper.find('[data-testid="previous-page"]').attributes('href')).toBe(
      '/island/tribes/iratay?page=1'
    )
  })

  it('第 1 頁、沒有下一頁時不顯示換頁', () => {
    const wrapper = mountPage({ fish: [fishCard()] })

    expect(wrapper.find('[data-testid="next-page"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="previous-page"]').exists()).toBe(false)
  })

  it('有回地圖的連結', () => {
    expect(mountPage().find('[data-testid="back-to-map"]').attributes('href')).toBe('/island')
  })
})
