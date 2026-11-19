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
const noResponses = [
  { tribe: 'ivalino', has_responses: false, names: [], has_variants: false, categories: [] },
  { tribe: 'yayo', has_responses: false, names: [], has_variants: false, categories: [] },
]

const mountFlow = (props = {}) => mount(FishResponses, { props: { fishId: 5, options, summary: noResponses, ...props } })
const click = (wrapper, text) => wrapper.findAll('button').find((b) => b.text().includes(text)).trigger('click')

beforeEach(() => post.mockClear())

describe('FishResponses 逐步回答', () => {
  it('沒有人回答過時，第一個畫面就是部落的問題，不顯示 0 或空表', () => {
    const wrapper = mountFlow()
    expect(wrapper.find('[data-testid="step-tribe"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('哪個部落？')
    expect(wrapper.text()).not.toContain('還沒有人')
    expect(wrapper.text()).not.toMatch(/\b0\b/)
  })

  it('選部落 → ✔ 就把目前的魚名存成這個部落的叫法，接著問分類', async () => {
    const wrapper = mountFlow()
    await click(wrapper, 'yayo')
    expect(wrapper.text()).toContain('叫它 mamalan 嗎？')
    await wrapper.find('button[aria-label="是"]').trigger('click')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: '' }, expect.anything())
    expect(wrapper.find('[data-testid="step-category"]').exists()).toBe(true)
  })

  it('選了分類會連同叫法一起再送出，然後顯示謝謝', async () => {
    const wrapper = mountFlow()
    await click(wrapper, 'yayo')
    await wrapper.find('button[aria-label="是"]').trigger('click')
    await click(wrapper, 'oyod')
    expect(post).toHaveBeenLastCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'mamalan', food_category: 'oyod' }, expect.anything())
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
  })

  it('略過分類不會再送出', async () => {
    const wrapper = mountFlow()
    await click(wrapper, 'yayo')
    await wrapper.find('button[aria-label="是"]').trigger('click')
    await click(wrapper, '略過')
    expect(post).toHaveBeenCalledTimes(1)
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
  })

  it('按 ✖ 後保留文字輸入，送出的是去空白後的叫法', async () => {
    const wrapper = mountFlow()
    await click(wrapper, 'yayo')
    await wrapper.find('button[aria-label="不是"]').trigger('click')
    await wrapper.find('input[aria-label="叫法"]').setValue('  dalakan  ')
    await click(wrapper, 'OK')
    expect(post).toHaveBeenCalledWith('/fish/5/responses', { tribe: 'yayo', name: 'dalakan', food_category: '' }, expect.anything())
  })

  it('魚名是「我不知道」時（沒有名字選項），選完部落直接問叫什麼', async () => {
    const wrapper = mountFlow({ options: { ...options, name_options: [] } })
    await click(wrapper, 'yayo')
    expect(wrapper.find('[data-testid="step-name"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="step-confirm"]').exists()).toBe(false)
  })

  it('已經回答過的部落帶入先前的叫法與分類', async () => {
    const wrapper = mountFlow({ mine: [{ tribe: 'yayo', name: 'dalakan', food_category: 'rahet' }] })
    expect(wrapper.find('[data-testid="step-done"]').exists()).toBe(true)
    await click(wrapper, '再說一個部落')
    expect(wrapper.text()).toContain('yayo ✓')
    await click(wrapper, 'yayo')
    await wrapper.find('button[aria-label="不是"]').trigger('click')
    expect(wrapper.find('input[aria-label="叫法"]').element.value).toBe('dalakan')
  })

  it('大家怎麼說只列有回答的部落，沒有人的部落不出現', async () => {
    const summary = [
      { tribe: 'ivalino', has_responses: false, names: [], has_variants: false, categories: [] },
      { tribe: 'yayo', has_responses: true, names: [{ name: 'dalakan', count: 2 }, { name: 'B', count: 1 }], has_variants: true, categories: [{ value: 'oyod', count: 2 }] },
    ]
    const wrapper = mountFlow({ summary, mine: [{ tribe: 'yayo', name: 'dalakan', food_category: null }], progress: { answered: 1, total: 3 } })
    await click(wrapper, '看大家怎麼說')
    const rows = wrapper.findAll('[data-testid="response-tribe"]')
    expect(rows).toHaveLength(1)
    expect(rows[0].text()).toContain('yayo')
    expect(rows[0].text()).toContain('dalakan 2、B 1')
    expect(rows[0].text()).toContain('oyod 2')
    expect(wrapper.text()).not.toContain('ivalino')
    expect(wrapper.find('[data-testid="response-progress"]').text()).toBe('1／3')
  })
})

describe('FishResponses 叫法字數', () => {
  async function submitCustomName(name) {
    const wrapper = mountFlow()
    await click(wrapper, 'yayo')
    await wrapper.find('button[aria-label="不是"]').trigger('click')
    await wrapper.find('input[aria-label="叫法"]').setValue(name)
    await click(wrapper, 'OK')
    return wrapper
  }

  it('60 個非 BMP 字元加前後空白可以送出，送出的是去空白後的叫法', async () => {
    await submitCustomName(`  ${ASTRAL.repeat(60)}　`)
    expect(post).toHaveBeenCalledWith('/fish/5/responses', expect.objectContaining({ name: ASTRAL.repeat(60) }), expect.anything())
  })

  it('61 個非 BMP 字元不送出並顯示錯誤', async () => {
    const wrapper = await submitCustomName(ASTRAL.repeat(61))
    expect(post).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('叫法最多 60 字')
  })
})
