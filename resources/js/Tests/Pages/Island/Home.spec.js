import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
  router: { visit: vi.fn() },
}))

import Home from '@/Pages/Island/Home.vue'

const mountPage = () =>
  mount(Home, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
      current_tribe: 'iratay',
    },
  })

const valueOf = (wrapper, label) =>
  wrapper
    .findAll('dt')
    .find((dt) => dt.text() === label)
    .element.nextElementSibling.textContent.trim()

describe('Island/Home 地圖頁', () => {
  it('「你現在在」是目前所在部落、「你的部落」是角色自己的部落，兩者分開', () => {
    const wrapper = mountPage()

    expect(valueOf(wrapper, '你現在在')).toBe('iratay（漁人）')
    expect(valueOf(wrapper, '你的部落')).toBe('yayo（椰油）')
  })

  it('「看這裡的魚」連到目前所在部落', () => {
    const link = mountPage()
      .findAll('a')
      .find((a) => a.text() === '看這裡的魚')

    expect(link.attributes('href')).toBe('/island/tribes/iratay')
  })
})
