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

const spot = (code, name, npc, withResult, total) => ({
  code,
  name,
  npc,
  with_result: withResult,
  total,
})

const SPOTS = [
  spot('main-house', '主屋', '阿姨', 6, 15),
  spot('backrest-stone', '靠背石', '爺爺', 0, 15),
  spot('drying-yard', '曬魚庭院', '叔叔', 4, 14),
  spot('pavilion', '涼亭', '年輕人', 0, 0),
]

const mountPage = (props = {}) =>
  mount(Tribe, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribe: 'iratay',
      spots: SPOTS,
      progress: { with_result: 10, total: 44 },
      ...props,
    },
  })

const spotCards = (wrapper) => wrapper.findAll('[data-testid="spot-card"]')

describe('Island/Tribe 家屋（#189）', () => {
  it('標題是「{族語（中文）}的家屋」', () => {
    expect(mountPage().find('h1').text()).toBe('iratay（漁人）的家屋')
  })

  it('右上角顯示角色名字', () => {
    expect(mountPage().text()).toContain('阿海')
  })

  it('有回地圖的連結', () => {
    expect(mountPage().find('[data-testid="back-to-map"]').attributes('href')).toBe('/island')
  })

  it('四張地點卡依序顯示地點名與 NPC 稱呼', () => {
    const cards = spotCards(mountPage())

    expect(cards.map((card) => card.find('[data-testid="spot-name"]').text())).toEqual([
      '主屋',
      '靠背石',
      '曬魚庭院',
      '涼亭',
    ])
    expect(cards[0].text()).toContain('阿姨')
    expect(cards[3].text()).toContain('年輕人')
  })

  it('地點卡寫「X/Y 有結果」；X＝0 時照寫 0/Y', () => {
    const cards = spotCards(mountPage())

    expect(cards[0].find('[data-testid="spot-progress"]').text()).toBe('6/15 有結果')
    expect(cards[1].find('[data-testid="spot-progress"]').text()).toBe('0/15 有結果')
  })

  it('Y＝0 時不寫 0/0，改寫「這裡還沒有魚」', () => {
    const card = spotCards(mountPage())[3]

    expect(card.find('[data-testid="spot-progress"]').text()).toBe('這裡還沒有魚')
    expect(card.text()).not.toContain('0/0')
  })

  it('每張卡的「看清單」連到這個部落、這個地點的清單；Y＝0 也可以點', () => {
    const links = spotCards(mountPage()).map((card) =>
      card.find('[data-testid="spot-list-link"]').attributes('href')
    )

    expect(links).toEqual([
      '/island/tribes/iratay/spots/main-house',
      '/island/tribes/iratay/spots/backrest-stone',
      '/island/tribes/iratay/spots/drying-yard',
      '/island/tribes/iratay/spots/pavilion',
    ])
  })

  it('地點卡不用 emoji 圖示', () => {
    expect(mountPage().find('[data-testid="spot-cards"]').text()).not.toMatch(
      /\p{Extended_Pictographic}/u
    )
  })
})

describe('#183 家屋：部落進度（沿用）', () => {
  it('上方顯示「{族語} 的魚」與 X/Y、進度條', () => {
    const progress = mountPage({ progress: { with_result: 7, total: 85 } }).find(
      '[data-testid="progress"]'
    )

    expect(progress.text()).toContain('iratay 的魚')
    expect(progress.text()).toContain('7/85')
    const bar = progress.find('[role="progressbar"]')
    expect(bar.attributes('aria-valuenow')).toBe('7')
    expect(bar.attributes('aria-valuemax')).toBe('85')
  })

  it('沒有進度（沒有魚）時不顯示', () => {
    expect(mountPage({ progress: null }).find('[data-testid="progress"]').exists()).toBe(false)
  })
})
