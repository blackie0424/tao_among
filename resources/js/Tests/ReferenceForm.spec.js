import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ReferenceForm from '@/Components/Reference/ReferenceForm.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
}))

const defaultProps = {
  submitLabel: '建立文獻',
}

describe('ReferenceForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('顯示所有欄位', () => {
    const wrapper = mount(ReferenceForm, { props: defaultProps })

    expect(wrapper.find('#name').exists()).toBe(true)
    expect(wrapper.find('#image_url').exists()).toBe(true)
    expect(wrapper.find('#external_url').exists()).toBe(true)
    expect(wrapper.find('#author').exists()).toBe(true)
    expect(wrapper.find('#status').exists()).toBe(true)
  })

  it('新增模式：空白初始值，送出 emit submit 不含 _method', async () => {
    const wrapper = mount(ReferenceForm, { props: defaultProps })
    await wrapper.find('#name').setValue('台灣魚類圖鑑')
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')[0][0]
    expect(payload.name).toBe('台灣魚類圖鑑')
    expect(payload._method).toBeUndefined()
  })

  it('編輯模式：以 reference 資料預填欄位', async () => {
    const reference = { id: 1, name: '甲書', image_url: '', external_url: 'https://example.com', author: '作者甲', status: 'enabled' }
    const wrapper = mount(ReferenceForm, { props: { ...defaultProps, reference } })
    await flushPromises()

    expect(wrapper.find('#name').element.value).toBe('甲書')
    expect(wrapper.find('#author').element.value).toBe('作者甲')
    expect(wrapper.find('#external_url').element.value).toBe('https://example.com')
  })

  it('編輯模式：送出 emit submit 含 _method PUT', async () => {
    const reference = { id: 1, name: '甲書', image_url: '', external_url: '', author: '', status: 'enabled' }
    const wrapper = mount(ReferenceForm, {
      props: { ...defaultProps, reference, isEditMode: true },
    })

    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toBeTruthy()
    expect(wrapper.emitted('submit')[0][0]).toMatchObject({ _method: 'PUT' })
  })

  it('setErrors 顯示伺服器欄位錯誤', async () => {
    const wrapper = mount(ReferenceForm, { props: defaultProps })

    wrapper.vm.setErrors({ name: '文獻名稱不可重複' })
    await flushPromises()

    expect(wrapper.text()).toContain('文獻名稱不可重複')
  })

  it('processing prop 為 true 時按鈕 disabled', () => {
    const wrapper = mount(ReferenceForm, { props: { ...defaultProps, processing: true } })

    expect(wrapper.find('button[type="submit"]').element.disabled).toBe(true)
  })
})
