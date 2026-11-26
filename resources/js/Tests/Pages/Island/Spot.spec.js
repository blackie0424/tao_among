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

const fishCard = (overrides) => ({
  id: 1,
  name: '浪人鰺',
  image_url: 'https://example.test/1.webp',
  results: null,
  ...overrides,
})

const TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']
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
      tribes: TRIBES,
      result_options: RESULT_OPTIONS,
      unknown_processing_method: '我不知道',
      ...props,
    },
    attachTo: document.body,
  })

const cards = (wrapper) => wrapper.findAll('[data-testid="fish-card"]')

describe('Island/Spot 地點清單（#189 由部落頁搬來）', () => {
  it('標題是「{地點}的魚」', () => {
    expect(mountPage().find('h1').text()).toBe('主屋的魚')
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

  it('沒有結果、有正式名字的魚卡：顯示「搶先回答」，不顯示長條或結果', () => {
    const card = cards(mountPage({ fish: [fishCard()] }))[0]

    expect(card.text()).toContain('搶先回答')
    expect(card.text()).not.toContain('0 人')
    expect(card.text()).not.toContain('大家叫它')
    expect(card.find('[data-testid="result-bar"]').exists()).toBe(false)
  })

  it('沒有正式名字的魚卡：顯示「還沒有名字」與「幫它取名」', () => {
    const card = cards(mountPage({ fish: [fishCard({ name: null })] }))[0]

    expect(card.text()).toContain('還沒有名字')
    expect(card.text()).toContain('幫它取名')
    expect(card.text()).not.toContain('搶先回答')
    expect(card.text()).not.toContain('我不知道')
  })

  it('沒有可顯示的魚：顯示「這裡還沒有魚」，不顯示「沒有更多了」', () => {
    const wrapper = mountPage({ page: 2, empty_reason: 'no_fish' })

    expect(wrapper.text()).toContain('這裡還沒有魚')
    expect(wrapper.text()).not.toContain('沒有更多了')
  })

  it('有魚但頁碼超過最後一頁：顯示「沒有更多了」', () => {
    const wrapper = mountPage({ page: 3, empty_reason: 'no_more' })

    expect(wrapper.text()).toContain('沒有更多了')
    expect(wrapper.text()).not.toContain('這裡還沒有魚')
  })

  it('還有下一頁時有「下一頁」連到下一個頁碼；第 2 頁起有「上一頁」', () => {
    const wrapper = mountPage({ fish: [fishCard()], page: 2, has_more_pages: true })

    expect(wrapper.find('[data-testid="next-page"]').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house?page=3'
    )
    expect(wrapper.find('[data-testid="previous-page"]').attributes('href')).toBe(
      '/island/tribes/iratay/spots/main-house?page=1'
    )
  })

  it('第 1 頁、沒有下一頁時不顯示換頁', () => {
    const wrapper = mountPage({ fish: [fishCard()] })

    expect(wrapper.find('[data-testid="next-page"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="previous-page"]').exists()).toBe(false)
  })

  it('整張魚卡（含「搶先回答／幫它取名」）連到這個部落的作答頁', () => {
    const wrapper = mountPage({ fish: [fishCard({ id: 7 }), fishCard({ id: 8, name: null })] })

    const links = cards(wrapper).map((card) => card.find('a'))
    expect(links.map((link) => link.attributes('href'))).toEqual([
      '/island/tribes/iratay/fish/7',
      '/island/tribes/iratay/fish/8',
    ])
    expect(links[0].text()).toContain('搶先回答')
    expect(links[1].text()).toContain('幫它取名')
  })

  it('#189 沒有結果的魚卡連到作答頁「要問的那一題」', () => {
    const wrapper = mountPage({
      fish: [
        fishCard({ id: 7, ask: 'food_category' }),
        fishCard({ id: 8, name: null, ask: 'name' }),
        fishCard({ id: 9, ask: 'processing_method' }),
      ],
    })

    expect(cards(wrapper).map((card) => card.find('a').attributes('href'))).toEqual([
      '/island/tribes/iratay/fish/7?question=food_category',
      '/island/tribes/iratay/fish/8?question=name',
      '/island/tribes/iratay/fish/9?question=processing_method',
    ])
  })

  it('#189 有「跟 {NPC} 聊天」連到這個地點的對話 n＝1', () => {
    const link = mountPage().find('[data-testid="spot-talk-link"]')

    expect(link.text()).toBe('跟阿姨聊天')
    expect(link.attributes('href')).toBe('/island/tribes/iratay/spots/main-house/talk?n=1')
  })

  it('#191 有結果的魚卡按鈕有 hover 樣式', () => {
    const button = cards(mountPage({ fish: [resultCard()] }))[0].find('button')

    expect(button.classes().some((name) => name.startsWith('hover:'))).toBe(true)
  })

  it('#191 細節視窗的捲動面板不帶動背後的頁面（overscroll-contain）', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))

    expect(dialog.classes()).toContain('overscroll-contain')
  })

  it('有回家屋的連結', () => {
    expect(mountPage().find('[data-testid="back-to-house"]').attributes('href')).toBe(
      '/island/tribes/iratay'
    )
  })
})

// #183：魚卡顯示所在部落的結果（目前部落 iratay）
const COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4']
const UNKNOWN_GRAY = '#94a3a8'

const resultCard = (overrides = {}) =>
  fishCard({
    id: 7,
    name: 'ilek',
    results: {
      food_category: {
        ivalino: [{ value: 'rahet', count: 1 }],
        iratay: [
          { value: 'oyod', count: 3 },
          { value: 'rahet', count: 1 },
        ],
        yayo: [
          { value: 'oyod', count: 2 },
          { value: 'kakanen no rarakeh', count: 1 },
        ],
      },
      processing_method: {
        iratay: [
          { value: '去魚鱗', count: 2 },
          { value: '剝皮', count: 1 },
          { value: '我不知道', count: 4 },
        ],
        yayo: [{ value: '我不知道', count: 1 }],
      },
    },
    ...overrides,
  })

const iratayOnly = (food, processing) =>
  fishCard({
    results: { food_category: { iratay: food }, processing_method: { iratay: processing } },
  })

const segmentsOf = (bar) =>
  bar.findAll('[data-testid="result-segment"]').map((segment) => ({
    value: segment.attributes('data-value'),
    grow: segment.element.style.flexGrow,
    minWidth: segment.element.style.minWidth,
    color: segment.element.style.backgroundColor,
  }))

// jsdom 把 #rrggbb 轉成 rgb()
const rgb = (hex) => {
  const n = parseInt(hex.slice(1), 16)
  return `rgb(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255})`
}

const openDetail = async (wrapper, index = 0) => {
  await cards(wrapper)[index].find('button').trigger('click')
  return wrapper.find('[role="dialog"]')
}

const detailRows = (dialog) =>
  dialog.findAll('[data-testid="detail-row"]').map((row) => ({
    label: row.find('[data-testid="detail-row-label"]').text(),
    current: row.attributes('data-current') === 'true',
    width: row.find('[data-testid="result-bar"]').element.style.width,
    minWidth: row.find('[data-testid="result-bar"]').element.style.minWidth,
  }))

describe('#183 地點清單：副標', () => {
  it('副標維持「{部落}的人怎麼說這些魚？」', () => {
    expect(mountPage({ fish: [resultCard()] }).text()).toContain('iratay（漁人）的人怎麼說這些魚？')
  })
})

describe('#183 有結果的魚卡（只算所在部落）', () => {
  it('分類、食用各一條長條，只畫所在部落有人選的選項，段的伸縮比例＝人數、最小 6px、顏色依選項', () => {
    const card = cards(mountPage({ fish: [resultCard()] }))[0]
    const [category, method] = card.findAll('[data-testid="result-bar"]')

    expect(segmentsOf(category)).toEqual([
      { value: 'oyod', grow: '3', minWidth: '6px', color: rgb(COLORS[0]) },
      { value: 'rahet', grow: '1', minWidth: '6px', color: rgb(COLORS[1]) },
    ])
    expect(segmentsOf(method)).toEqual([
      { value: '去魚鱗', grow: '2', minWidth: '6px', color: rgb(COLORS[0]) },
      { value: '剝皮', grow: '1', minWidth: '6px', color: rgb(COLORS[2]) },
    ])
  })

  it('標籤寫最多人選的選項與人數；食用不算「我不知道」', () => {
    const labels = cards(mountPage({ fish: [resultCard()] }))[0]
      .findAll('[data-testid="result-label"]')
      .map((label) => label.text())

    expect(labels).toEqual(['oyod 3', '去魚鱗 2'])
  })

  it('平手並列', () => {
    const card = cards(
      mountPage({
        fish: [
          iratayOnly(
            [
              { value: 'oyod', count: 2 },
              { value: 'rahet', count: 2 },
            ],
            [{ value: '剝皮', count: 1 }]
          ),
        ],
      })
    )[0]

    expect(card.find('[data-testid="result-label"]').text()).toBe('oyod、rahet 2')
  })

  it('選項寫全名，標籤獨立一行', () => {
    const card = cards(
      mountPage({
        fish: [
          iratayOnly([{ value: 'kakanen no rarakeh', count: 2 }], [{ value: '剝皮', count: 1 }]),
        ],
      })
    )[0]
    const label = card.find('[data-testid="result-label"]')

    expect(label.text()).toBe('kakanen no rarakeh 2')
    expect(label.element.tagName).toBe('P')
  })

  it('有結果的魚卡沒有「搶先回答」，點了不換頁', () => {
    const card = cards(mountPage({ fish: [resultCard()] }))[0]

    expect(card.text()).not.toContain('搶先回答')
    expect(card.find('a').exists()).toBe(false)
    expect(card.find('button').exists()).toBe(true)
  })

  it('別的部落的回答不畫進魚卡', () => {
    const card = cards(mountPage({ fish: [resultCard()] }))[0]

    expect(card.text()).not.toContain('kakanen no rarakeh')
  })
})

describe('#183 細節視窗', () => {
  it('點有結果的魚卡跳出視窗，標題是魚名，預設「分類」', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))

    expect(dialog.exists()).toBe(true)
    expect(dialog.find('h2').text()).toBe('ilek')
    expect(dialog.find('[data-testid="tab-food_category"]').attributes('aria-pressed')).toBe('true')
    expect(dialog.find('[data-testid="tab-processing_method"]').attributes('aria-pressed')).toBe(
      'false'
    )
  })

  it('沒有正式名字的魚，標題寫「還沒有名字」', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard({ name: null })] }))

    expect(dialog.find('h2').text()).toBe('還沒有名字')
  })

  it('分類：所在部落第一（標示）、全島第二、其他部落依人數；長度＝人數÷全島，最短＝段數×6＋間隔', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))

    expect(detailRows(dialog)).toEqual([
      { label: 'iratay 4', current: true, width: '50%', minWidth: '14px' },
      { label: '全島 8', current: false, width: '100%', minWidth: '22px' },
      { label: 'yayo 3', current: false, width: '37.5%', minWidth: '14px' },
      { label: 'ivalino 1', current: false, width: '12.5%', minWidth: '6px' },
    ])
  })

  it('全島列的段是各部落合計', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))
    const island = dialog.findAll('[data-testid="detail-row"]')[1]

    expect(segmentsOf(island).map(({ value, grow }) => [value, grow])).toEqual([
      ['oyod', '5'],
      ['rahet', '2'],
      ['kakanen no rarakeh', '1'],
    ])
  })

  it('圖例只列這一題有人選的選項（全名），不寫每列各選項的人數', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))
    const legend = dialog.findAll('[data-testid="legend-item"]').map((item) => item.text())

    expect(legend).toEqual(['oyod', 'rahet', 'kakanen no rarakeh'])
    expect(dialog.text()).not.toContain('oyod 3')
    expect(dialog.text()).not.toContain('oyod 5')
  })

  it('切到「食用」：含「我不知道」（灰色），沒答這題的部落不列', async () => {
    const wrapper = mountPage({ fish: [resultCard()] })
    const dialog = await openDetail(wrapper)
    await dialog.find('[data-testid="tab-processing_method"]').trigger('click')

    expect(dialog.findAll('[data-testid="legend-item"]').map((item) => item.text())).toEqual([
      '去魚鱗',
      '剝皮',
      '我不知道',
    ])
    expect(detailRows(dialog).map((row) => row.label)).toEqual(['iratay 7', '全島 8', 'yayo 1'])
    const unknown = segmentsOf(dialog.findAll('[data-testid="detail-row"]')[0]).at(-1)
    expect(unknown).toMatchObject({ value: '我不知道', color: rgb(UNKNOWN_GRAY) })
  })

  it('其他部落同人數時依部落清單順序', async () => {
    const fish = fishCard({
      results: {
        food_category: {
          ivalino: [{ value: 'oyod', count: 1 }],
          iratay: [{ value: 'oyod', count: 1 }],
          iraraley: [{ value: 'rahet', count: 1 }],
          imowrod: [{ value: 'oyod', count: 1 }],
        },
        processing_method: { iratay: [{ value: '剝皮', count: 1 }] },
      },
    })
    const dialog = await openDetail(mountPage({ fish: [fish] }))

    expect(detailRows(dialog).map((row) => row.label)).toEqual([
      'iratay 1',
      '全島 4',
      'ivalino 1',
      'imowrod 1',
      'iraraley 1',
    ])
  })

  it('人數懸殊：全島 100、部落 1 人時該列用最短 6px', async () => {
    const fish = fishCard({
      results: {
        food_category: {
          iratay: [{ value: 'oyod', count: 99 }],
          yayo: [{ value: 'rahet', count: 1 }],
        },
        processing_method: { iratay: [{ value: '剝皮', count: 1 }] },
      },
    })
    const dialog = await openDetail(mountPage({ fish: [fish] }))

    expect(detailRows(dialog)[2]).toEqual({
      label: 'yayo 1',
      current: false,
      width: '1%',
      minWidth: '6px',
    })
  })

  it('「我也來說」連到所在部落的作答頁', async () => {
    const dialog = await openDetail(mountPage({ fish: [resultCard()] }))

    expect(dialog.find('[data-testid="answer-link"]').attributes('href')).toBe(
      '/island/tribes/iratay/fish/7'
    )
    expect(dialog.find('[data-testid="answer-link"]').text()).toBe('我也來說')
  })

  it('按關閉或 Esc 關掉視窗，焦點回到原本那張魚卡', async () => {
    const wrapper = mountPage({ fish: [fishCard({ id: 6 }), resultCard()] })
    let dialog = await openDetail(wrapper, 1)
    await dialog.find('[data-testid="close-detail"]').trigger('click')
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await wrapper.vm.$nextTick()
    expect(document.activeElement).toBe(cards(wrapper)[1].find('button').element)

    dialog = await openDetail(wrapper, 1)
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await wrapper.vm.$nextTick()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(cards(wrapper)[1].find('button').element)
    wrapper.unmount()
  })

  it('開啟時焦點在關閉鈕，Tab 在視窗內循環', async () => {
    const wrapper = mountPage({ fish: [resultCard()] })
    const dialog = await openDetail(wrapper)
    await wrapper.vm.$nextTick()
    expect(document.activeElement).toBe(dialog.find('[data-testid="close-detail"]').element)

    dialog.find('[data-testid="answer-link"]').element.focus()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', cancelable: true }))
    expect(document.activeElement).toBe(dialog.find('[data-testid="close-detail"]').element)
    wrapper.unmount()
  })
})
