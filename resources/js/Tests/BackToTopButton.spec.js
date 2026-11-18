import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import BackToTopButton from '@/Components/UI/BackToTopButton.vue'

const setScroll = (y) => {
  Object.defineProperty(window, 'scrollY', { value: y, configurable: true, writable: true })
  window.dispatchEvent(new Event('scroll'))
}

let wrapper
beforeEach(() => {
  window.innerHeight = 800
  Object.defineProperty(window, 'scrollY', { value: 0, configurable: true, writable: true })
  window.scrollTo = vi.fn()
})
afterEach(() => wrapper?.unmount())

const mountButton = () => (wrapper = mount(BackToTopButton))
const button = (w) => w.find('button[aria-label="回到頂端"]')

describe('BackToTopButton（AC 10）', () => {
  it('在頂部時不顯示', () => {
    expect(button(mountButton()).exists()).toBe(false)
  })

  it('捲動超過一個螢幕高度才出現；剛好一個螢幕高度不出現', async () => {
    const w = mountButton()
    setScroll(800)
    await w.vm.$nextTick()
    expect(button(w).exists()).toBe(false)
    setScroll(801)
    await w.vm.$nextTick()
    expect(button(w).exists()).toBe(true)
  })

  it('捲回頂部後消失', async () => {
    const w = mountButton()
    setScroll(2000)
    await w.vm.$nextTick()
    setScroll(0)
    await w.vm.$nextTick()
    expect(button(w).exists()).toBe(false)
  })

  it('掛載時已捲過一個螢幕就直接顯示', async () => {
    window.scrollY = 1500
    const w = mountButton()
    await w.vm.$nextTick()
    expect(button(w).exists()).toBe(true)
  })

  it('56px 空心品牌色外框圓鈕（定位交給外層容器），點擊平滑捲回頂端', async () => {
    const w = mountButton()
    setScroll(2000)
    await w.vm.$nextTick()
    const btn = button(w)
    expect(btn.classes()).toEqual(
      expect.arrayContaining([
        'h-14',
        'w-14',
        'rounded-full',
        'border-2',
        'border-brand',
        'bg-paper-raised',
        'text-brand',
      ])
    )
    expect(btn.classes()).not.toContain('fixed')
    await btn.trigger('click')
    expect(window.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })
  })

  it('卸載後不再監聽捲動', () => {
    const remove = vi.spyOn(window, 'removeEventListener')
    mountButton().unmount()
    wrapper = null
    expect(remove).toHaveBeenCalledWith('scroll', expect.any(Function))
  })
})
