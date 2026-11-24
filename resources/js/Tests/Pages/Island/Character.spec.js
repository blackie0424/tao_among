import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const posted = []
let lastPostOptions
let lastForm
const routerHandlers = {}
const removedHandlers = []

vi.mock('@inertiajs/vue3', async () => {
  const { reactive } = await import('vue')
  return {
    Head: { render: () => null },
    router: {
      on: (name, handler) => {
        routerHandlers[name] = handler
        return () => removedHandlers.push(name)
      },
    },
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
  vi.useFakeTimers()
  posted.length = 0
  removedHandlers.length = 0
  document.body.innerHTML = ''
})

afterEach(() => {
  vi.useRealTimers()
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

  const invalidResponse = (status, headers = {}) => {
    const event = { detail: { response: { status, headers } }, preventDefault: vi.fn() }
    routerHandlers.invalid(event)
    return event
  }
  const visibleLimitMessage = (wrapper) => wrapper.find('[data-testid="rate-limit-message"]')
  const submitButton = (wrapper) => wrapper.find('button[type="submit"]')

  it('被限流（429）時留在表單顯示還要等幾秒、保留已填內容，不讓瀏覽器跳錯誤視窗', async () => {
    const wrapper = mountPage()
    await wrapper.find('#island-name').setValue('阿海')
    await wrapper.find('input[type="radio"][value="yayo"]').setValue()
    expect(visibleLimitMessage(wrapper).exists()).toBe(false)

    const event = invalidResponse(429, { 'retry-after': '45' })
    await wrapper.vm.$nextTick()

    expect(event.preventDefault).toHaveBeenCalled()
    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 45 秒再試')
    expect(wrapper.find('#island-name').element.value).toBe('阿海')
    expect(wrapper.find('input[type="radio"][value="yayo"]').element.checked).toBe(true)
  })

  it('秒數每秒減 1；倒數期間送出鈕停用並顯示幾秒後可以再試', async () => {
    const wrapper = mountPage()
    invalidResponse(429, { 'retry-after': '45' })
    await wrapper.vm.$nextTick()
    expect(submitButton(wrapper).text()).toBe('45 秒後可以再試')
    expect(submitButton(wrapper).attributes('disabled')).toBeDefined()

    await vi.advanceTimersByTimeAsync(3000)

    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 42 秒再試')
    expect(submitButton(wrapper).text()).toBe('42 秒後可以再試')
  })

  it('數到 0 時提示消失、送出鈕恢復，不顯示 0 秒', async () => {
    const wrapper = mountPage()
    invalidResponse(429, { 'retry-after': '2' })
    await wrapper.vm.$nextTick()

    await vi.advanceTimersByTimeAsync(1000)
    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 1 秒再試')

    await vi.advanceTimersByTimeAsync(1000)
    expect(visibleLimitMessage(wrapper).exists()).toBe(false)
    expect(wrapper.text()).not.toContain('0 秒')
    expect(submitButton(wrapper).text()).toBe('進入魚島')
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it.each([
    ['沒有 Retry-After', {}],
    ['不是數字', { 'retry-after': 'soon' }],
    ['是 0', { 'retry-after': '0' }],
    ['是負數', { 'retry-after': '-5' }],
  ])('回應的等待秒數%s時從 60 秒開始', async (_label, headers) => {
    const wrapper = mountPage()

    invalidResponse(429, headers)
    await wrapper.vm.$nextTick()

    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 60 秒再試')
  })

  it('倒數期間按 Enter 送出表單也不會真的送出', async () => {
    const wrapper = mountPage()
    invalidResponse(429, { 'retry-after': '45' })
    await wrapper.vm.$nextTick()

    await wrapper.find('form').trigger('submit')

    expect(posted).toEqual([])
    expect(visibleLimitMessage(wrapper).text()).toBe('送出太多次了，請等 45 秒再試')
  })

  it('倒數結束後可以再送出', async () => {
    const wrapper = mountPage()
    invalidResponse(429, { 'retry-after': '1' })
    await vi.advanceTimersByTimeAsync(1000)

    await wrapper.find('form').trigger('submit')

    expect(posted).toHaveLength(1)
  })

  it('其他非預期的回應（例如 500）不攔截，維持原本的錯誤呈現', async () => {
    const wrapper = mountPage()

    const event = invalidResponse(500)
    await wrapper.vm.$nextTick()

    expect(event.preventDefault).not.toHaveBeenCalled()
    expect(visibleLimitMessage(wrapper).exists()).toBe(false)
  })

  it('離開頁面時移除對錯誤回應的監聽並停止倒數', async () => {
    const wrapper = mountPage()
    invalidResponse(429, { 'retry-after': '45' })

    wrapper.unmount()

    expect(removedHandlers).toEqual(['invalid'])
    expect(vi.getTimerCount()).toBe(0)
  })
})
