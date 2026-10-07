import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Form from '@/Pages/CaptureSessions/Form.vue'

describe('CaptureSessions/Form styles', () => {
  it('gives every rendered input, select, and textarea an explicit border width', () => {
    const form = {
      capture_date: '2026-10-01',
      tribe: '',
      capture_method: '',
      place_id: null,
      notes: '',
      errors: {},
      processing: false,
    }
    const wrapper = mount(Form, {
      props: { form, tribes: ['ivalino'], captureMethods: { 釣魚: '釣魚' } },
    })

    const controls = wrapper.findAll('input, select, textarea')
    expect(controls.length).toBeGreaterThan(0)
    for (const control of controls) {
      expect(control.classes()).toContain('border')
    }
    for (const label of wrapper.findAll('label')) {
      expect(label.classes()).toContain('mb-1')
    }
  })
})
