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
  food_categories: ['oyod', 'rahet', '不分類', '不食用'],
  name_options: ['mamalan'],
  name_max_length: 60,
}
const none = [
  { tribe: 'ivalino', has_responses: false, names: [], has_variants: false, categories: [] },
  { tribe: 'yayo', has_responses: false, names: [], has_variants: false, categories: [] },
]
const responded = [
  none[0],
  { tribe: 'yayo', has_responses: true, names: [{ name: 'dalakan', count: 2 }, { name: 'B', count: 1 }], has_variants: true, categories: [{ value: 'oyod', count: 2 }] },
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

  it('✔ → 選部落，才把目前的魚名存成這個部落的叫法，接著問分類', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    expect(post).not.toHaveBeenCalled()
    await click(wrapper, 'yayo')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
  })

  it('選了分類會連同叫法一起再送出，然後顯示謝謝', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'oyod' }, expect.anything())
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
  })

  it('略過分類不會再送出', async () => {
    const wrapper = mountFlow()
    await yes(wrapper)
    await click(wrapper, 'yayo')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
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
    expect(wrapper.text()).toContain('這條魚，你們叫什麼？')
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
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
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
    const rows = wrapper.findAll('[data-testid="response-tribe"]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('dalakan 2、B 1')
    expect(rows[0].text()).toContain('oyod 2')
    expect(wrapper.text()).not.toContain('ivalino')
    expect(wrapper.find('[data-testid="responders"]').text()).toBe('3 人回應')
    expect(post).not.toHaveBeenCalled()
  })

  it('還沒有人回答時沒有看大家怎麼說的入口', () => {
    expect(mountFlow().findAll('button').some((b) => b.text().includes('看大家怎麼說'))).toBe(false)
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
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
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
