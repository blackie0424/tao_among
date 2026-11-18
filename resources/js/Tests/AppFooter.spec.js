import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppFooter from '@/Components/Global/AppFooter.vue'

describe('AppFooter', () => {
  it('保留版權行並註明資料不授權', () => {
    const text = mount(AppFooter).text()
    expect(text).toContain('Copyright © 2026 Chungyueh')
    expect(text).toContain('網站資料皆為私人所有，不同意任何授權使用。')
  })
})
