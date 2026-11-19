import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'

const post = vi.fn()
vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false, recentlySuccessful: false })
      form.setError = (field, message) => { form.errors[field] = message }
      form.transform = (fn) => { form._transform = fn; return form }
      form.post = (url, options) => post(url, form._transform({ ...form }), options)
      return form
    },
  }
})

import FishResponses from '@/Components/FishResponses/FishResponses.vue'

const ASTRAL = '\u{20BB7}' // 一個 Unicode 字元，UTF-16 佔 2 個單位
const options = { tribes: ['yayo'], food_categories: ['oyod'], name_options: [], name_max_length: 60 }

async function submitCustomName(name) {
  const wrapper = mount(FishResponses, { props: { fishId: 5, options } })
  await wrapper.findAll('button').find((b) => b.text() === 'yayo').trigger('click')
  await wrapper.findAll('button').find((b) => b.text() === '自己打').trigger('click')
  await wrapper.find('input[aria-label="叫法"]').setValue(name)
  await wrapper.find('form').trigger('submit')
  return wrapper
}

describe('FishResponses 叫法字數', () => {
  beforeEach(() => post.mockClear())

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
