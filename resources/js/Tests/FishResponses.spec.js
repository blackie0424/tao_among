import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'

const post = vi.fn()
vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      form.setError = (field, message) => { form.errors[field] = message }
      form.clearErrors = () => { form.errors = {} }
      form.reset = () => Object.assign(form, init)
      form.transform = (fn) => { form._transform = fn; return form }
      form.post = (url, options) => {
        post(url, form._transform({ tribe: form.tribe, name: form.name, food_category: form.food_category }), options)
        options?.onSuccess?.()
      }
      return form
    },
  }
})

import FishResponses from '@/Components/FishResponses/FishResponses.vue'

const ASTRAL = '\u{20BB7}' // 一個 Unicode 字元，UTF-16 佔 2 個單位
const options = {
  tribes: ['ivalino', 'yayo'],
  food_categories: ['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh'],
  name_options: ['mamalan'],
  name_max_length: 60,
}
const none = [
  { tribe: 'ivalino', has_responses: false, names: [], has_variants: false, categories: [] },
  { tribe: 'yayo', has_responses: false, names: [], has_variants: false, categories: [] },
]
const responded = [
  none[0],
  { tribe: 'yayo', has_responses: true, names: [{ name: 'dalakan', count: 2 }, { name: 'B', count: 1 }], has_variants: true, categories: [{ value: 'oyod', count: 4 }, { value: 'rahet', count: 1 }, { value: '不分類', count: 0 }] },
  { tribe: 'iratay', has_responses: true, names: [{ name: 'mamalan', count: 1 }], has_variants: false, categories: [{ value: 'oyod', count: 0 }] },
]

const mountFlow = (props = {}) => mount(FishResponses, { props: { fishId: 5, options, summary: none, responders: 0, ...props } })
const click = (wrapper, text) => wrapper.findAll('button').find((b) => b.text().includes(text)).trigger('click')
const yes = (wrapper) => wrapper.find('button[aria-label="是"]').trigger('click')
const no = (wrapper) => wrapper.find('button[aria-label="不是"]').trigger('click')
const type = (wrapper, text) => wrapper.find('input[aria-label="叫法"]').setValue(text)

beforeEach(() => post.mockClear())

describe('FishResponses 問答順序', () => {
  it('第一個畫面直接問「你們也叫它 mamalan 嗎？」，不顯示 0 或空表', () => {
    const wrapper = mountFlow()
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('你們也叫它 mamalan 嗎？')
    expect(wrapper.text()).not.toContain('還沒有人')
  })

  it('問題的文字：選部落問「你要提供哪個部落的說法？」、叫法問「你們會怎麼稱呼呢？」', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    expect(wrapper.text()).toContain('你們會怎麼稱呼呢？')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    expect(wrapper.text()).toContain('你要提供哪個部落的說法？')
  })

  it('✔ → 選部落，才把目前的魚名存成這個部落的叫法，接著問分類', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
  })

  it('選了分類會連同叫法一起再送出，然後直接看大家怎麼說（不再多一個謝謝畫面）', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'oyod' }, expect.anything())
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('謝謝')
  })

  it('略過分類不會再送出', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('✖ 後保留文字輸入，選好部落後送出的是去空白後的叫法', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await type(wrapper, '  dalakan  ')
    await click(wrapper, 'OK')
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })

  it('魚名是「我不知道」時（沒有名字選項），第一題直接問這條魚叫什麼', async () => {
    const wrapper = mountFlow({ options: { ...options, name_options: [] } })
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('這條魚，你們會怎麼稱呼呢？')
    await type(wrapper, 'dalakan')
    await click(wrapper, 'OK')
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })
})

describe('FishResponses 做到一半離開，下次繼續', () => {
  it('已有叫法但還沒分類的部落，第一個畫面提示繼續，按下去直接問分類', async () => {
    const wrapper = mountFlow({ mine: [{ tribe: 'yayo', name: 'dalakan', food_category: null }] })
    expect(wrapper.find('[data-testid="step-resume"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('yayo')
    await click(wrapper, '繼續')
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: 'oyod' }, expect.anything())
  })

  it('回答都完整時不提示繼續', () => {
    const wrapper = mountFlow({ mine: [{ tribe: 'yayo', name: 'dalakan', food_category: 'rahet' }] })
    expect(wrapper.find('[data-testid="step-resume"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('還沒答過時第一個畫面是第一題，不是繼續', () => {
    expect(mountFlow().find('[data-testid="step-resume"]').exists()).toBe(false)
  })
})

describe('FishResponses 回應人數與直接查看', () => {
  it('每個畫面下方顯示這條魚的回應人數，0 人也顯示', () => {
    expect(mountFlow({ responders: 0 }).find('[data-testid="responders"]').text()).toBe('0 人回應')
    expect(mountFlow({ responders: 3 }).find('[data-testid="responders"]').text()).toBe('3 人回應')
  })

  it('別人已經有回答時，沒回答過的人在第一個畫面就有入口，可以直接看', async () => {
    const wrapper = mountFlow({ summary: responded, responders: 3 })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.findAll('[data-testid="response-tribe"]')).toHaveLength(2)
    expect(wrapper.text()).not.toContain('ivalino')
    expect(wrapper.find('[data-testid="responders"]').text()).toBe('3 人回應')
    expect(post).not.toHaveBeenCalled()
  })

  it('每個部落一張卡：叫法只列名稱（不含人數）', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const [yayo, iratay] = wrapper.findAll('[data-testid="response-tribe"]')
    expect(yayo.find('[data-testid="tribe-names"]').text()).toBe('dalakan、B')
    expect(iratay.find('[data-testid="tribe-names"]').text()).toBe('mamalan')
  })

  it('分類用長條圖：最多人的填滿、其餘依比例、沒人選的不畫，並標出人數', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const bars = wrapper.findAll('[data-testid="response-tribe"]')[0].findAll('[data-testid="tribe-categories"] li')
    expect(bars.map((b) => b.text())).toEqual(['oyod4', 'rahet1'])
    expect(bars[0].find('[data-testid="bar-fill"]').attributes('style')).toContain('width: 100%')
    expect(bars[1].find('[data-testid="bar-fill"]').attributes('style')).toContain('width: 25%')
  })

  it('沒有人選分類的部落不畫長條，顯示「還沒有人選分類」', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    const iratay = wrapper.findAll('[data-testid="response-tribe"]')[1]
    expect(iratay.find('[data-testid="tribe-categories"]').exists()).toBe(false)
    expect(iratay.text()).toContain('還沒有人選分類')
  })

  it('每一步最上方都有標題「大家怎麼說」', async () => {
    const wrapper = mountFlow({ summary: responded })
    expect(wrapper.find('h3').text()).toBe('大家怎麼說')
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('h3').text()).toBe('大家怎麼說')
  })

  it('還沒有人回答時沒有看大家怎麼說的入口', () => {
    expect(mountFlow().findAll('button').some((b) => b.text().includes('看大家怎麼說'))).toBe(false)
  })
})

describe('FishResponses 答完直接看結果，自己的選擇特別標出', () => {
  const mine = [{ tribe: 'yayo', name: 'Dalakan', food_category: 'oyod' }]

  it('答過的人進來直接看大家怎麼說，自己的部落、叫法、分類都有標記', () => {
    const wrapper = mountFlow({ summary: responded, mine, responders: 3 })
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="intro"]').text()).toBe('以下是不同部落的說法，提供參考。')
    expect(wrapper.text()).not.toContain('謝謝')
    const [yayo, iratay] = wrapper.findAll('[data-testid="response-tribe"]')
    expect(yayo.attributes('data-mine')).toBe('true')
    expect(iratay.attributes('data-mine')).toBeUndefined()
    const marked = yayo.findAll('[data-testid="tribe-names"] [data-mine]')
    expect(marked).toHaveLength(1)
    expect(marked[0].text()).toContain('dalakan')
    const [oyod, rahet] = yayo.findAll('[data-testid="tribe-categories"] li')
    expect(oyod.attributes('data-mine')).toBe('true')
    expect(oyod.find('[data-testid="bar-fill"]').classes()).toContain('bg-amber-600')
    expect(rahet.attributes('data-mine')).toBeUndefined()
    expect(rahet.find('[data-testid="bar-fill"]').classes()).toContain('bg-brand')
  })

  it('下方有「再說一個部落」，沒有先出現謝謝畫面再多按一次', async () => {
    const wrapper = mountFlow({ summary: responded, mine })
    await click(wrapper, '再說一個部落')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })

  it('完成最後一步後直接顯示結果，說明句與剛填完、之後再進來時一樣，再說一個部落的按鈕在下方', async () => {
    const wrapper = mountFlow({ summary: responded, mine })
    await click(wrapper, '再說一個部落')
    await yes(wrapper)
    await click(wrapper, 'ivalino')
    await click(wrapper, 'oyod')
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="intro"]').text()).toBe('以下是不同部落的說法，提供參考。')
    expect(wrapper.text()).not.toContain('謝謝')
    expect(wrapper.findAll('button').some((b) => b.text().includes('再說一個部落'))).toBe(true)
  })

  it('還沒答過、從問題畫面點進來看的人有返回，沒有「再說一個部落」', async () => {
    const wrapper = mountFlow({ summary: responded })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('button[aria-label="返回"]').exists()).toBe(true)
    expect(wrapper.findAll('button').some((b) => b.text().includes('再說一個部落'))).toBe(false)
    await wrapper.find('button[aria-label="返回"]').trigger('click')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })
})

describe('FishResponses 自己的叫法標記與後端分組一致', () => {
  const summaryWith = (names) => [
    { tribe: 'yayo', has_responses: true, names: names.map((name) => ({ name, count: 1 })), has_variants: names.length > 1, categories: [] },
  ]
  const marked = (wrapper) => wrapper.findAll('[data-testid="tribe-names"] [data-mine]').map((el) => el.element.textContent.replace(/ ✔$/, ''))
  const mineNamed = (name) => [{ tribe: 'yayo', name, food_category: 'oyod' }]

  it('ASCII 大小寫與前後空白視為同組：標出自己的叫法', () => {
    const wrapper = mountFlow({ summary: summaryWith(['dalakan', 'B']), mine: mineNamed(' DALAKAN\t') })
    expect(marked(wrapper)).toEqual(['dalakan'])
  })

  it('後端分成不同組的叫法（開頭多一個 U+FEFF）不會同時標成自己的', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\uFEFFDalakan', 'Dalakan']), mine: mineNamed('Dalakan') })
    expect(marked(wrapper)).toEqual(['Dalakan'])
  })

  it('不換行空格 U+00A0 後端不去掉，所以和一般寫法是不同組，不會誤標', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\u00A0Dalakan', 'Dalakan']), mine: mineNamed('\u00A0Dalakan') })
    expect(marked(wrapper)).toEqual(['\u00A0Dalakan'])
  })

  it('非英文字母不折大小寫：希臘文 Α 與 α 是不同組', () => {
    const wrapper = mountFlow({ summary: summaryWith(['\u0391', '\u03B1']), mine: mineNamed('\u03B1') })
    expect(marked(wrapper)).toEqual(['\u03B1'])
  })
})

describe('FishResponses 分類選項', () => {
  it('分類步驟列出 oyod、rahet、不分類、不食用與 kakanen no rarakeh，選了就送出', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    const labels = wrapper.findAll('[data-testid="step-category"] button').map((b) => b.text())
    expect(labels).toEqual(expect.arrayContaining(['oyod', 'rahet', '不分類', '不食用', 'kakanen no rarakeh']))
    await click(wrapper, 'kakanen no rarakeh')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'kakanen no rarakeh' }, expect.anything())
  })

  it('長條圖也會畫出 kakanen no rarakeh', async () => {
    const summary = [{ tribe: 'yayo', has_responses: true, names: [{ name: 'a', count: 1 }], has_variants: false, categories: [{ value: 'kakanen no rarakeh', count: 2 }] }]
    const wrapper = mountFlow({ summary })
    await click(wrapper, '看大家怎麼說')
    expect(wrapper.find('[data-testid="tribe-categories"]').text()).toContain('kakanen no rarakeh')
  })
})

describe('FishResponses 返回鍵', () => {
  it('返回鍵是圖示按鈕（svg），不是文字符號 ‹', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    const back = wrapper.find('button[aria-label="返回"]')
    expect(back.find('svg').exists()).toBe(true)
    expect(back.text()).toBe('')
    await back.trigger('click')
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(true)
  })
})

describe('FishResponses 只填一項與修改', () => {
  const previous = [{ tribe: 'yayo', name: 'dalakan', food_category: 'rahet' }]

  it('可以只填分類：略過叫法、選部落、選分類，送出的叫法是空的', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: 'oyod' }, expect.anything())
  })

  it('叫法與分類都略過時不送出，並提示至少填一項', async () => {
    const wrapper = mountFlow()
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('至少要填一項')
  })

  it('修改已有分類的回答時，送出叫法先沿用原本的分類，中途離開不會遺失', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '再說一個部落')
    await yes(wrapper)
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'rahet' }, expect.anything())
    expect(wrapper.find('[data-testid="previous-category"]').text()).toContain('rahet')
  })

  it('修改已有分類的回答時，按略過分類才會清掉分類', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '再說一個部落')
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(2)
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-others"]').exists()).toBe(true)
  })

  it('修改時可以清空叫法、保留分類：略過叫法再重選原本的分類', async () => {
    const wrapper = mountFlow({ mine: previous })
    await click(wrapper, '再說一個部落')
    await no(wrapper)
    await click(wrapper, '略過')
    await click(wrapper, 'yayo')
    await click(wrapper, 'rahet')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: '', food_category: 'rahet' }, expect.anything())
  })
})

describe('FishResponses 叫法字數', () => {
  async function typeName(name) {
    const wrapper = mountFlow()
    await no(wrapper)
    await type(wrapper, name)
    await click(wrapper, 'OK')
    return wrapper
  }

  it('60 個非 BMP 字元加前後空白可以通過，送出的是去空白後的叫法', async () => {
    const wrapper = await typeName(`  ${ASTRAL.repeat(60)}　`)
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', expect.objectContaining({ name: ASTRAL.repeat(60) }), expect.anything())
  })

  it('61 個非 BMP 字元不往下走、不送出並顯示錯誤', async () => {
    const wrapper = await typeName(ASTRAL.repeat(61))
    expect(wrapper.find('[data-testid="step-tribe"]').exists()).toBe(false)
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('叫法最多 60 字')
  })
})
