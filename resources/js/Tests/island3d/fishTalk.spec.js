import { describe, expect, it } from 'vitest'
import { comparisonRows, familyAnswer, NOT_RECORDED, NOBODY_SAID } from '@/island3d/fishTalk'

// #232 AC 9、10：拿著魚問家人——家人回答哪一種、「你記的／這裡多數說」並排與「改成…」

const TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']
const UNKNOWN = '我不知道'

/** fish_speech：只列有說法的部落為 true，其餘 false */
const speechWith = (fishId, withDetail) =>
  Object.fromEntries(
    TRIBES.map((tribe) => [
      tribe,
      { [fishId]: { speech: `${tribe} 的話`, has_detail: withDetail.includes(tribe) } },
    ])
  )

describe('familyAnswer（家人回哪一種）', () => {
  it('這個部落有說法：講這個部落的話', () => {
    expect(
      familyAnswer({
        fishId: 1,
        tribe: 'iratay',
        tribes: TRIBES,
        fishSpeech: speechWith(1, ['iratay']),
      })
    ).toEqual({ kind: 'known', line: 'iratay 的話' })
  })

  it('這個部落沒有、別的部落有：說不知道，列出所有有說法的部落（族語（中文），依部落設定順序）', () => {
    expect(
      familyAnswer({
        fishId: 1,
        tribe: 'yayo',
        tribes: TRIBES,
        fishSpeech: speechWith(1, ['iraraley', 'imowrod']),
      })
    ).toEqual({
      kind: 'elsewhere',
      line: '我不知道這條魚。你去問 imowrod（紅頭）、iraraley（朗島）的人看看。',
    })
  })

  it('全島都沒有：說全島還沒有人說過它是哪一類、怎麼處理', () => {
    expect(
      familyAnswer({ fishId: 1, tribe: 'yayo', tribes: TRIBES, fishSpeech: speechWith(1, []) })
    ).toEqual({ kind: 'nowhere', line: '這條魚，全島還沒有人說過它是哪一類、怎麼處理。' })
  })

  it('fish_speech 沒有這條魚（資料還沒更新）：當成全島都沒有', () => {
    expect(
      familyAnswer({
        fishId: 2,
        tribe: 'yayo',
        tribes: TRIBES,
        fishSpeech: speechWith(1, ['yayo']),
      }).kind
    ).toBe('nowhere')
  })
})

describe('comparisonRows（你記的／這裡多數說）', () => {
  const results = {
    food_category: {
      yayo: [
        { value: 'oyod', count: 3 },
        { value: 'rahet', count: 1 },
      ],
    },
    processing_method: {
      yayo: [
        { value: '剝皮', count: 2 },
        { value: '去魚鱗', count: 2 },
      ],
    },
  }

  it('分類一列、處理方式一列；多數＝筆數最多，平手全部列出', () => {
    const rows = comparisonRows({
      my: { food_category: 'oyod', processing_method: '剝皮' },
      results,
      tribe: 'yayo',
      unknown: UNKNOWN,
    })

    expect(rows).toEqual([
      { question: 'food_category', mine: 'oyod', leaders: ['oyod'], differs: false },
      { question: 'processing_method', mine: '剝皮', leaders: ['剝皮', '去魚鱗'], differs: false },
    ])
  })

  it('自己的選擇不在多數裡才算不一樣（出現「改成…」）', () => {
    const rows = comparisonRows({
      my: { food_category: 'rahet' },
      results,
      tribe: 'yayo',
      unknown: UNKNOWN,
    })

    expect(rows[0]).toEqual({
      question: 'food_category',
      mine: 'rahet',
      leaders: ['oyod'],
      differs: true,
    })
  })

  it('自己沒記的那一題寫「還沒記」，不算不一樣', () => {
    const rows = comparisonRows({ my: {}, results, tribe: 'yayo', unknown: UNKNOWN })

    expect(rows[1]).toMatchObject({ mine: null, differs: false })
    expect(NOT_RECORDED).toBe('還沒記')
  })

  it('這個部落這一題沒有有效回答（沒人答、只有「我不知道」）：多數為空（畫面寫「還沒有人說」），不算不一樣', () => {
    const rows = comparisonRows({
      my: { food_category: 'oyod', processing_method: '剝皮' },
      results: { food_category: {}, processing_method: { yayo: [{ value: UNKNOWN, count: 5 }] } },
      tribe: 'yayo',
      unknown: UNKNOWN,
    })

    expect(rows).toEqual([
      { question: 'food_category', mine: 'oyod', leaders: [], differs: false },
      { question: 'processing_method', mine: '剝皮', leaders: [], differs: false },
    ])
    expect(NOBODY_SAID).toBe('還沒有人說')
  })

  it('處理方式的「我不知道」不算進多數：其他選項較少也是多數', () => {
    const rows = comparisonRows({
      my: { processing_method: '去魚鱗' },
      results: {
        processing_method: {
          yayo: [
            { value: UNKNOWN, count: 5 },
            { value: '剝皮', count: 1 },
          ],
        },
      },
      tribe: 'yayo',
      unknown: UNKNOWN,
    })

    expect(rows[1]).toEqual({
      question: 'processing_method',
      mine: '去魚鱗',
      leaders: ['剝皮'],
      differs: true,
    })
  })

  it('自己的處理方式是「我不知道」、這裡有多數：算不一樣', () => {
    const rows = comparisonRows({
      my: { processing_method: UNKNOWN },
      results,
      tribe: 'yayo',
      unknown: UNKNOWN,
    })

    expect(rows[1].differs).toBe(true)
  })

  it('沒有結果資料：兩題都沒有多數', () => {
    const rows = comparisonRows({ my: {}, results: undefined, tribe: 'yayo', unknown: UNKNOWN })

    expect(rows.map((row) => row.leaders)).toEqual([[], []])
  })
})
