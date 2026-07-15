import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ReferenceKnowledgeForm from '@/Components/ReferenceKnowledge/ReferenceKnowledgeForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))

const defaultProps = {
  references: [
    { id: 1, name: '甲書' },
    { id: 2, name: '乙書' },
  ],
  tribes: ['ivalino', 'iraraley'],
  cancelUrl: '/fish/1/reference-knowledge',
}

describe('ReferenceKnowledgeForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('顯示可選的部落欄位', () => {
    const wrapper = mount(ReferenceKnowledgeForm, { props: defaultProps })

    const tribeSelect = wrapper.find('#tribe')
    const options = tribeSelect.findAll('option')

    expect(tribeSelect.exists()).toBe(true)
    expect(options).toHaveLength(3)
    expect(options[0].text()).toBe('不指定部落')
    expect(options[1].text()).toBe('ivalino')
    expect(wrapper.text()).toContain('僅接受單頁或連續頁，例如 12 或 12-15，跳頁請分筆輸入。')
  })

  it('新增模式：送出時 emit submit 含 tribe 欄位，不含 _method', async () => {
    const wrapper = mount(ReferenceKnowledgeForm, { props: defaultProps })

    await wrapper.find('#reference_id').setValue('1')
    await wrapper.find('#tribe').setValue('iraraley')
    await wrapper.find('#pages').setValue('12-15')
    await wrapper.find('#content').setValue('文獻內容')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')[0][0]
    expect(payload.tribe).toBe('iraraley')
    expect(payload._method).toBeUndefined()
  })

  it('編輯模式：送出時 emit submit 含 _method PUT', async () => {
    const knowledge = { id: 5, reference_id: 1, tribe: 'ivalino', content: '既有內容', pages: '10', note: '' }
    const wrapper = mount(ReferenceKnowledgeForm, {
      props: { ...defaultProps, knowledge, isEditMode: true },
    })

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeTruthy()
    expect(wrapper.emitted('submit')[0][0]).toMatchObject({ _method: 'PUT' })
  })

  it('以 knowledge 資料預填欄位', async () => {
    const knowledge = { id: 5, reference_id: 2, tribe: 'iraraley', content: '既有文獻內容', pages: '12', note: '備註' }
    const wrapper = mount(ReferenceKnowledgeForm, {
      props: { ...defaultProps, knowledge },
    })
    await flushPromises()

    expect(wrapper.find('#reference_id').element.value).toBe('2')
    expect(wrapper.find('#tribe').element.value).toBe('iraraley')
    expect(wrapper.find('#content').element.value).toBe('既有文獻內容')
  })

  it('setErrors 顯示伺服器回傳的欄位錯誤', async () => {
    const wrapper = mount(ReferenceKnowledgeForm, { props: defaultProps })

    wrapper.vm.setErrors({ content: '內容不可為空' })
    await flushPromises()

    expect(wrapper.text()).toContain('內容不可為空')
  })
})
