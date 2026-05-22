import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ReferenceKnowledgeForm from '@/Components/ReferenceKnowledge/ReferenceKnowledgeForm.vue'

const post = vi.fn()
const put = vi.fn()
let formState

vi.mock('@inertiajs/vue3', async () => {
  const vue = await vi.importActual('vue')

  return {
    Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
    useForm: (initialValues) => {
      formState = vue.reactive({
        ...initialValues,
        errors: {},
        processing: false,
        post,
        put,
      })

      return formState
    },
  }
})

const defaultProps = {
  references: [
    { id: 1, name: '甲書' },
    { id: 2, name: '乙書' },
  ],
  tribes: ['ivalino', 'iraraley'],
  submitUrl: '/fish/1/reference-knowledge',
  cancelUrl: '/fish/1/reference-knowledge',
}

describe('ReferenceKnowledgeForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('顯示可選的部落欄位', () => {
    const wrapper = mount(ReferenceKnowledgeForm, {
      props: defaultProps,
    })

    const tribeSelect = wrapper.find('#tribe')
    const options = tribeSelect.findAll('option')

    expect(tribeSelect.exists()).toBe(true)
    expect(options).toHaveLength(3)
    expect(options[0].text()).toBe('不指定部落')
    expect(options[1].text()).toBe('ivalino')
  })

  it('送出時會帶入 tribe 欄位', async () => {
    const wrapper = mount(ReferenceKnowledgeForm, {
      props: defaultProps,
    })

    await wrapper.find('#reference_id').setValue('1')
    await wrapper.find('#tribe').setValue('iraraley')
    await wrapper.find('#pages').setValue('12-15')
    await wrapper.find('#content').setValue('文獻內容')
    await wrapper.find('form').trigger('submit.prevent')

    expect(post).toHaveBeenCalledWith('/fish/1/reference-knowledge')
    expect(formState.tribe).toBe('iraraley')
  })
})
