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
  majority_name: null,
  first_responders: [],
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
      progress: { answered: 0, total: 0 },
      ...props,
    },
  })

const cards = (wrapper) => wrapper.findAll('[data-testid="fish-card"]')

describe('Island/Tribe 部落頁', () => {
  it('標題用「族語（中文）」顯示部落', () => {
    expect(mountPage().find('h1').text()).toBe('iratay（漁人）')
  })

  it('副標是「{部落}的人怎麼說這些魚？」', () => {
    expect(mountPage().text()).toContain('iratay（漁人）的人怎麼說這些魚？')
  })

  it('魚卡顯示魚名與照片，不顯示魚的編號', () => {
    const card = cards(mountPage({ fish: [fishCard({ id: 7, name: '浪人鰺' })] }))[0]

    expect(card.text()).toContain('浪人鰺')
    expect(card.text()).not.toContain('#7')
    expect(card.find('img').attributes('src')).toBe('https://example.test/1.webp')
  })

  it('沒人回答的卡：顯示「搶先回答」，不顯示 0', () => {
    const card = cards(mountPage({ fish: [fishCard({ responder_count: 0 })] }))[0]

    expect(card.text()).toContain('搶先回答')
    expect(card.text()).not.toContain('0 人')
    expect(card.text()).not.toContain('大家叫它')
  })

  it('沒人回答、也沒有正式名字的卡：顯示「還沒有名字」與「幫它取名」', () => {
    const card = cards(mountPage({ fish: [fishCard({ name: null, responder_count: 0 })] }))[0]

    expect(card.text()).toContain('還沒有名字')
    expect(card.text()).toContain('幫它取名')
    expect(card.text()).not.toContain('搶先回答')
    expect(card.text()).not.toContain('我不知道')
  })

  it('有人回答、叫法有多數：顯示「大家叫它」＋寫法、回答者與「你也來說」', () => {
    const card = cards(
      mountPage({
        fish: [
          fishCard({
            responder_count: 3,
            majority_name: 'angsa',
            first_responders: [{ name: '阿海' }, { name: 'Siyapen' }],
          }),
        ],
      })
    )[0]

    expect(card.find('[data-testid="majority-name"]').text()).toBe('angsa')
    expect(card.text()).toContain('大家叫它')
    expect(card.find('[data-testid="responders"]').text()).toBe('阿海、Siyapen 等 3 人')
    expect(card.text()).toContain('你也來說')
    expect(card.text()).not.toContain('搶先回答')
  })

  it('有人回答、叫法沒有多數：不顯示「大家叫它」，只顯示回答者與人數', () => {
    const card = cards(
      mountPage({
        fish: [
          fishCard({
            responder_count: 1,
            majority_name: null,
            first_responders: [{ name: '阿海' }],
          }),
        ],
      })
    )[0]

    expect(card.text()).not.toContain('大家叫它')
    expect(card.find('[data-testid="responders"]').text()).toBe('阿海 1 人')
  })

  it('2 人回答時不加「等」；沒有魚島角色的回答者顯示「among 族人」', () => {
    const card = cards(
      mountPage({
        fish: [
          fishCard({ responder_count: 2, first_responders: [{ name: '阿海' }, { name: null }] }),
        ],
      })
    )[0]

    expect(card.find('[data-testid="responders"]').text()).toBe('阿海、among 族人 2 人')
  })

  it('進度顯示「{部落}的魚：X / Y 條有人說過」，X 為 0 也顯示', () => {
    const wrapper = mountPage({ fish: [fishCard()], progress: { answered: 0, total: 200 } })

    expect(wrapper.find('[data-testid="progress"]').text()).toContain(
      'iratay（漁人）的魚：0 / 200 條有人說過'
    )
  })

  it('沒有魚（total 0）時不顯示進度', () => {
    const wrapper = mountPage({ empty_reason: 'no_fish', progress: { answered: 0, total: 0 } })

    expect(wrapper.find('[data-testid="progress"]').exists()).toBe(false)
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
