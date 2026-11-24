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
        posted.push({ url, name: form.name, tribe: form.tribe, form_token: form.form_token })
        lastPostOptions = options
      }
      return form
    },
  }
})

import Character from '@/Pages/Island/Character.vue'

const mountPage = (formToken) =>
  mount(Character, {
    attachTo: document.body,
    props: { tribes: ['yayo', 'iraraley'], name_max_length: 20, form_token: formToken },
  })

beforeEach(() => {
  posted.length = 0
  document.body.innerHTML = ''
})

describe('魚島角色設定頁', () => {
  it('送出時帶名字、部落與頁面給的表單代碼', async () => {
    const wrapper = mountPage('token-1')

    await wrapper.find('#island-name').setValue('阿海')
    await wrapper.find('input[type="radio"][value="yayo"]').setValue()
    await wrapper.find('form').trigger('submit')

    expect(posted).toEqual([
      { url: '/island/character', name: '阿海', tribe: 'yayo', form_token: 'token-1' },
    ])
  })

  it('送出失敗後伺服器換了新的表單代碼，不重新整理直接重送會帶新代碼，已填的內容保留', async () => {
    const wrapper = mountPage('token-old')
    await wrapper.find('#island-name').setValue('阿海')
    await wrapper.find('input[type="radio"][value="yayo"]').setValue()
    await wrapper.find('form').trigger('submit')

    await wrapper.setProps({ form_token: 'token-new' })
    await wrapper.find('form').trigger('submit')

    expect(posted[1]).toEqual({
      url: '/island/character',
      name: '阿海',
      tribe: 'yayo',
      form_token: 'token-new',
    })
  })

  it('部落選項先寫族語、中文用括號附在後面', () => {
    const wrapper = mountPage('token-1')

    expect(wrapper.findAll('fieldset label').map((label) => label.text())).toEqual([
      'yayo（椰油）',
      'iraraley（朗島）',
    ])
  })

  it('送出後名字有錯，焦點移到名字欄位', async () => {
    const wrapper = mountPage('token-1')
    await wrapper.find('form').trigger('submit')

    lastForm.errors = { name: '請填名字', tribe: '請選部落' }
    await lastPostOptions.onError(lastForm.errors)

    expect(document.activeElement).toBe(wrapper.find('#island-name').element)
  })

  it('送出後只有部落有錯，焦點移到第一個部落選項', async () => {
    const wrapper = mountPage('token-1')
    await wrapper.find('form').trigger('submit')

    lastForm.errors = { tribe: '請選部落' }
    await lastPostOptions.onError(lastForm.errors)

    expect(document.activeElement).toBe(wrapper.find('input[type="radio"][value="yayo"]').element)
  })
})
