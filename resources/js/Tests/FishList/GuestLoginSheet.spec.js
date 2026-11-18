import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import GuestLoginSheet from '@/Components/FishList/GuestLoginSheet.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

let wrapper
const mountSheet = (props = {}) => {
  wrapper = mount(GuestLoginSheet, {
    attachTo: document.body,
    props: { show: true, fishId: 12, fishName: '飛魚', pendingActivation: false, ...props },
  })
  return wrapper
}
afterEach(() => {
  wrapper?.unmount()
  document.body.style.overflow = ''
})

describe('GuestLoginSheet 訪客（AC 7）', () => {
  it('顯示標題、內文、登入鈕與先逛逛鈕', () => {
    const w = mountSheet()
    expect(w.get('[data-testid="sheet-title"]').text()).toBe('想認識「飛魚」？')
    expect(w.get('[data-testid="sheet-body"]').text()).toBe('登入後可以看這條魚的詳細資料。')
    const login = w.get('[data-testid="sheet-login"]')
    expect(login.text()).toBe('登入，繼續看這條魚')
    expect(login.attributes('href')).toBe('/login?redirect=/fish/12')
    expect(w.get('[data-testid="sheet-browse"]').text()).toBe('先逛逛清單')
  })

  it.each([null, undefined, '', '   '])('魚名為 %j 時標題用「這條魚」', (fishName) => {
    const w = mountSheet({ fishName })
    expect(w.get('[data-testid="sheet-title"]').text()).toBe('想認識「這條魚」？')
  })

  it('show 為 false 時不渲染', () => {
    const w = mountSheet({ show: false })
    expect(w.find('[role="dialog"]').exists()).toBe(false)
  })

  it('關閉鈕、先逛逛鈕、點遮罩、Esc 都會 emit close', async () => {
    const w = mountSheet()
    await w.get('[data-testid="sheet-close"]').trigger('click')
    await w.get('[data-testid="sheet-browse"]').trigger('click')
    await w.get('[data-testid="sheet-overlay"]').trigger('click')
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(w.emitted('close')).toHaveLength(4)
  })

  it('點面板內部不會關閉', async () => {
    const w = mountSheet()
    await w.get('[role="dialog"]').trigger('click')
    expect(w.emitted('close')).toBeUndefined()
  })

  it('開啟時焦點進入面板，並鎖住背景捲動；關閉後還原', async () => {
    const w = mountSheet({ show: false })
    await w.setProps({ show: true })
    await w.vm.$nextTick()
    expect(document.activeElement).toBe(w.get('[role="dialog"]').element)
    expect(document.body.style.overflow).toBe('hidden')
    await w.setProps({ show: false })
    expect(document.body.style.overflow).toBe('')
  })

  it('面板具備 dialog 語意並以標題命名', () => {
    const w = mountSheet()
    const dialog = w.get('[role="dialog"]')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.attributes('aria-labelledby')).toBe(w.get('[data-testid="sheet-title"]').attributes('id'))
  })
})

describe('GuestLoginSheet 帳號未開通（AC 8）', () => {
  it('內文改為等待開通，沒有登入鈕，只有先逛逛', () => {
    const w = mountSheet({ pendingActivation: true })
    expect(w.get('[data-testid="sheet-body"]').text()).toBe('你的帳號還在等待開通')
    expect(w.text()).not.toContain('登入後可以看')
    expect(w.find('[data-testid="sheet-login"]').exists()).toBe(false)
    expect(w.find('a').exists()).toBe(false)
    expect(w.get('[data-testid="sheet-browse"]').text()).toBe('先逛逛清單')
  })
})
