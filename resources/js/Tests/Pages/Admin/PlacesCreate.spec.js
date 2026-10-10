import { beforeEach, describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Create from '@/Pages/Admin/Places/Create.vue'

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  useForm: vi.fn(),
}))
vi.mock('@/Layouts/AdminLayout.vue', () => ({ default: { template: '<main><slot /></main>', props: ['title'] } }))
let form
beforeEach(() => {
  useForm.mockImplementation(values => (form = reactive({ ...values, errors: {}, processing: false, post: vi.fn() })))
})
const mountForm = () => mount(Create, { props: { tribes: ['iraraley', 'yayo'] } })

describe('Admin/Places/Create', () => {
  it('shows exactly the four specified fields with length and required constraints', () => {
    const wrapper = mountForm()
    expect(wrapper.findAll('label').map(x => x.text())).toEqual(['部落', '名稱', '族語名稱', '備註'])
    expect(wrapper.findAll('input,select,textarea')).toHaveLength(4)
    expect(wrapper.get('#tribe').attributes('required')).toBeDefined()
    expect(wrapper.get('#name').attributes('required')).toBeDefined()
    expect(wrapper.get('#name').attributes('maxlength')).toBe('191')
    expect(wrapper.get('#tao-name').attributes('maxlength')).toBe('255')
  })
  it('starts at the disabled tribe prompt without a shared option', () => {
    const wrapper = mountForm()
    expect(wrapper.get('select').element.value).toBe('')
    expect(wrapper.get('option').element.disabled).toBe(true)
    expect(wrapper.findAll('option').map(x => x.text())).toEqual(['請選擇部落', 'iraraley', 'yayo'])
    expect(wrapper.text()).not.toContain('共用')
  })
  it('has no coordinate fields and offers cancellation to the place list', () => {
    const wrapper = mountForm()
    expect(wrapper.findAll('input').map(x => x.attributes('id'))).toEqual(['name', 'tao-name'])
    expect(wrapper.text()).not.toMatch(/座標|經度|緯度|地理位置/)
    expect(wrapper.get('a').attributes('href')).toBe('/admin/places')
    expect(wrapper.get('a').text()).toBe('取消')
  })
  it('gives all controls an explicit border width', () => {
    for (const control of mountForm().findAll('input,textarea,select')) expect(control.classes()).toContain('border')
  })
  it('posts entered values to the admin route and presents the first validation error', async () => {
    const wrapper = mountForm()
    await wrapper.get('#tribe').setValue('iraraley')
    await wrapper.get('#name').setValue('ZZPLACEMARK')
    await wrapper.get('#tao-name').setValue('ZZTAOMARK')
    await wrapper.get('#notes').setValue('Test note')
    await wrapper.get('form').trigger('submit')
    expect(form.post).toHaveBeenCalledWith('/admin/places')
    expect([form.tribe, form.name, form.tao_name, form.notes]).toEqual(['iraraley', 'ZZPLACEMARK', 'ZZTAOMARK', 'Test note'])
    form.errors = { name: '此部落已有同名地名' }
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('此部落已有同名地名')
  })
})
