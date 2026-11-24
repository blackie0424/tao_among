import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const posted = []
let lastPostOptions
let lastForm

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    Head: { render: () => null },
    useForm: (init) => {
      const form = reactive({ ...init, errors: {}, processing: false })
      lastForm = form
      form.post = (url, options) => {
        posted.push({ url, name: form.name, tribe: form.tribe })
        lastPostOptions = options
      }
      return form
    },
  }
})

import Character from '@/Pages/Island/Character.vue'

const mountPage = () =>
  mount(Character, {
    attachTo: document.body,
    props: { tribes: ['yayo', 'iraraley'], name_max_length: 20 },
  })

beforeEach(() => {
  posted.length = 0
  document.body.innerHTML = ''
})

describe('魚島角色設定頁', () => {
  it('送出時帶名字與部落', async () => {
    const wrapper = mountPage()

    await wrapper.find('#island-name').setValue('阿海')
    await wrapper.find('input[type="radio"][value="yayo"]').setValue()
    await wrapper.find('form').trigger('submit')

    expect(posted).toEqual([{ url: '/island/character', name: '阿海', tribe: 'yayo' }])
  })

  it('送出期間送出鈕停用並顯示「進入中…」，擋掉連按；平常顯示「進入魚島」', async () => {
    const wrapper = mountPage()
    const button = wrapper.find('button[type="submit"]')
    expect(button.text()).toBe('進入魚島')
    expect(button.attributes('disabled')).toBeUndefined()

    lastForm.processing = true
    await wrapper.vm.$nextTick()

    expect(button.text()).toBe('進入中…')
    expect(button.attributes('disabled')).toBeDefined()
  })

  it('部落選項先寫族語、中文用括號附在後面', () => {
    const wrapper = mountPage()

    expect(wrapper.findAll('fieldset label').map((label) => label.text())).toEqual([
      'yayo（椰油）',
      'iraraley（朗島）',
    ])
  })

  it('送出後名字有錯，焦點移到名字欄位', async () => {
    const wrapper = mountPage()
    await wrapper.find('form').trigger('submit')

    lastForm.errors = { name: '請填名字', tribe: '請選部落' }
    await lastPostOptions.onError(lastForm.errors)

    expect(document.activeElement).toBe(wrapper.find('#island-name').element)
  })

  it('送出後只有部落有錯，焦點移到第一個部落選項', async () => {
    const wrapper = mountPage()
    await wrapper.find('form').trigger('submit')

    lastForm.errors = { tribe: '請選部落' }
    await lastPostOptions.onError(lastForm.errors)

    expect(document.activeElement).toBe(wrapper.find('input[type="radio"][value="yayo"]').element)
  })
})
