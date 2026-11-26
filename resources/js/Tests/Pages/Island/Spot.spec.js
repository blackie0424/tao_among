import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}))

import Spot from '@/Pages/Island/Spot.vue'

const RESULT_OPTIONS = {
  food_category: ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'],
  processing_method: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'],
}

const mountPage = (props = {}) =>
  mount(Spot, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribe: 'iratay',
      spot: { code: 'main-house', name: '主屋', npc: '阿姨' },
      fish: [],
      page: 1,
      has_more_pages: false,
      empty_reason: null,
      tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
      result_options: RESULT_OPTIONS,
      unknown_processing_method: '我不知道',
      ...props,
    },
  })

describe('Island/Spot 地點清單', () => {
  it('標題是「{地點}的魚」，副標寫部落「族語（中文）」', () => {
    const wrapper = mountPage()

    expect(wrapper.find('h1').text()).toBe('主屋的魚')
    expect(wrapper.text()).toContain('iratay（漁人）')
  })

  it('這個地點沒有魚時顯示「這裡還沒有魚」', () => {
    expect(mountPage({ empty_reason: 'no_fish' }).text()).toContain('這裡還沒有魚')
  })

  it('頁碼超過最後一頁時顯示「沒有更多了」', () => {
    expect(mountPage({ empty_reason: 'no_more', page: 3 }).text()).toContain('沒有更多了')
  })

  it('換頁連到這個地點清單的頁碼', () => {
    const wrapper = mountPage({
      fish: [{ id: 1, name: '浪人鰺', image_url: 'x', results: null }],
      page: 2,
      has_more_pages: true,
    })

    expect(wrapper.find('[data-testid="next-page"]').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house?page=3'
    )
    expect(wrapper.find('[data-testid="previous-page"]').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house?page=1'
    )
  })

  it('沒有結果的魚卡連到這個部落的作答頁', () => {
    const wrapper = mountPage({ fish: [{ id: 7, name: '浪人鰺', image_url: 'x', results: null }] })

    expect(wrapper.find('[data-testid="fish-card"] a').attributes('href')).toBe(
      '/island/tribes/iratay/fish/7'
    )
  })

  it('右上角顯示角色名字', () => {
    expect(mountPage().text()).toContain('阿海')
  })
})
