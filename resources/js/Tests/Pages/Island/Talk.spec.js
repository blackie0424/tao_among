import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}))

import Talk from '@/Pages/Island/Talk.vue'

const RESULTS = {
  food_category: { iratay: [{ value: 'oyod', count: 2 }] },
  processing_method: {},
}

const mountPage = (props = {}) =>
  mount(Talk, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribe: 'iratay',
      spot: { code: 'main-house', name: '主屋', npc: '阿姨' },
      n: 2,
      total: 4,
      is_last: false,
      fish: {
        id: 9,
        name: '魚A',
        image_url: 'https://example.test/9.webp',
        ask: null,
        results: RESULTS,
      },
      speech: '這是 魚A。我們 iratay 多數人說它是 oyod；怎麼處理還沒有答案。',
      tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
      result_options: {
        food_category: ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'],
        processing_method: ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '我不知道'],
      },
      unknown_processing_method: '我不知道',
      ...props,
    },
    attachTo: document.body,
  })

const byTestId = (wrapper, id) => wrapper.find(`[data-testid="${id}"]`)

describe('Island/Talk NPC 對話（#189）', () => {
  it('顯示 NPC 稱呼「{地點}的{NPC}」與「第 N／M 條」', () => {
    const wrapper = mountPage()

    expect(byTestId(wrapper, 'npc-name').text()).toBe('主屋的阿姨')
    expect(byTestId(wrapper, 'talk-count').text()).toBe('第 2／4 條')
  })

  it('顯示這條魚的照片與 NPC 說的話（照後端給的逐字顯示）', () => {
    const wrapper = mountPage()

    expect(wrapper.find('img').attributes('src')).toBe('https://example.test/9.webp')
    expect(byTestId(wrapper, 'speech').text()).toBe(
      '這是 魚A。我們 iratay 多數人說它是 oyod；怎麼處理還沒有答案。'
    )
  })

  it('有回家屋與看清單的連結', () => {
    const wrapper = mountPage()

    expect(byTestId(wrapper, 'back-to-house').attributes('href')).toBe('/island/tribes/iratay')
    expect(byTestId(wrapper, 'talk-list-link').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house'
    )
  })

  it('「下一條」連到 n＋1', () => {
    expect(byTestId(mountPage(), 'next-fish').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house/talk?n=3'
    )
  })

  it('最後一條：沒有「下一條」，改成「回到家屋」', () => {
    const wrapper = mountPage({ n: 4, is_last: true })

    expect(byTestId(wrapper, 'next-fish').exists()).toBe(false)
    expect(byTestId(wrapper, 'finish-to-house').attributes('href')).toBe('/island/tribes/iratay')
  })

  it('有要問的題時「我來說」連到作答頁的那一題；沒有時不顯示', () => {
    const asking = mountPage({
      fish: { id: 9, name: null, image_url: 'x', ask: 'name', results: null },
    })

    expect(byTestId(asking, 'answer-here').attributes('href')).toBe(
      '/island/tribes/iratay/fish/9?question=name'
    )
    expect(byTestId(mountPage(), 'answer-here').exists()).toBe(false)
  })

  it('至少一題有結果時有「誰說的？」，點了開細節視窗；都沒結果時不顯示', async () => {
    const wrapper = mountPage()

    await byTestId(wrapper, 'who-said').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    const noResult = mountPage({
      fish: { id: 9, name: '魚A', image_url: 'x', ask: 'food_category', results: null },
    })
    expect(byTestId(noResult, 'who-said').exists()).toBe(false)
  })

  it('細節視窗切到沒人答的一題時，長條寬度是 0%，不是 NaN', async () => {
    const wrapper = mountPage()
    await byTestId(wrapper, 'who-said').trigger('click')

    await wrapper.find('[data-testid="tab-processing_method"]').trigger('click')

    const widths = wrapper
      .findAll('[data-testid="detail-row"] [data-testid="result-bar"]')
      .map((bar) => bar.element.style.width)
    expect(widths.length).toBeGreaterThan(0)
    expect(widths.every((width) => width === '0%')).toBe(true)
  })

  it('這個地點沒有魚：NPC 說「這裡還沒有魚」，只有「回到家屋」', () => {
    const wrapper = mountPage({
      fish: null,
      total: 0,
      n: 1,
      is_last: false,
      speech: '這裡還沒有魚',
    })

    expect(byTestId(wrapper, 'speech').text()).toBe('這裡還沒有魚')
    expect(byTestId(wrapper, 'talk-count').exists()).toBe(false)
    expect(byTestId(wrapper, 'next-fish').exists()).toBe(false)
    expect(byTestId(wrapper, 'finish-to-house').attributes('href')).toBe('/island/tribes/iratay')
    expect(byTestId(wrapper, 'who-said').exists()).toBe(false)
    expect(byTestId(wrapper, 'answer-here').exists()).toBe(false)
  })

  it('這個地點沒有魚：上方照常有「← 家屋」與「看清單」（chung 裁決）', () => {
    const wrapper = mountPage({
      fish: null,
      total: 0,
      n: 1,
      is_last: false,
      speech: '這裡還沒有魚',
    })

    expect(byTestId(wrapper, 'back-to-house').attributes('href')).toBe('/island/tribes/iratay')
    expect(byTestId(wrapper, 'talk-list-link').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house'
    )
  })

  it('NPC 頭像)不用 emoji 圖示', () => {
    expect(byTestId(mountPage(), 'npc-name').text()).not.toMatch(/\p{Extended_Pictographic}/u)
  })
})
