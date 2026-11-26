import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
  Head: { render: () => null },
  Link: {
    props: ['href'],
    template: '<a :href="href"><slot /></a>',
  },
}))

// 3D 繪圖模組換成假的：測試環境（jsdom）畫不出 3D，畫面由實機驗證
const scene = vi.hoisted(() => ({ createIslandScene: vi.fn(), dispose: vi.fn() }))
vi.mock('@/island3d/scene', () => ({ createIslandScene: scene.createIslandScene }))

import Home from '@/Pages/Island/Home.vue'

const mountPage = async () => {
  const wrapper = mount(Home, {
    props: {
      character: { name: '阿海', tribe: 'yayo' },
      tribes: ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'],
      current_tribe: 'iratay',
    },
  })
  await flushPromises()
  return wrapper
}

const valueOf = (wrapper, label) =>
  wrapper
    .findAll('dt')
    .find((dt) => dt.text() === label)
    .element.nextElementSibling.textContent.trim()

const sceneOptions = () => scene.createIslandScene.mock.calls[0][1]

describe('Island/Home 地圖頁', () => {
  beforeEach(() => {
    scene.createIslandScene.mockReset().mockReturnValue({ dispose: scene.dispose })
    scene.dispose.mockReset()
    window.history.replaceState({}, '', '/island')
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('「你現在在」是目前所在部落、「你的部落」是角色自己的部落，兩者分開', async () => {
    const wrapper = await mountPage()

    expect(valueOf(wrapper, '你現在在')).toBe('iratay（漁人）')
    expect(valueOf(wrapper, '你的部落')).toBe('yayo（椰油）')
  })

  it('3D 格子上標示的是目前所在部落，不是角色自己的部落', async () => {
    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="world-tribe"]').text()).toBe('iratay（漁人）')
  })

  it('3D 畫在頁面的畫布區塊裡，畫得出來時不顯示「無法顯示」', async () => {
    const wrapper = await mountPage()

    expect(scene.createIslandScene).toHaveBeenCalledTimes(1)
    expect(scene.createIslandScene.mock.calls[0][0]).toBe(
      wrapper.get('[data-testid="world-stage"]').element
    )
    expect(wrapper.find('[data-testid="world-unsupported"]').exists()).toBe(false)
  })

  it('瀏覽器無法建立 3D 畫面時，顯示「這台裝置無法顯示魚島」', async () => {
    scene.createIslandScene.mockImplementation(() => {
      throw new Error('Error creating WebGL context.')
    })

    const wrapper = await mountPage()

    expect(wrapper.get('[data-testid="world-unsupported"]').text()).toBe('這台裝置無法顯示魚島')
  })

  it('系統設定「減少動態效果」會傳給 3D 畫面', async () => {
    vi.stubGlobal('matchMedia', (query) => ({
      matches: query === '(prefers-reduced-motion: reduce)',
    }))

    await mountPage()

    expect(sceneOptions().reducedMotion).toBe(true)
  })

  it('網址帶 ?debug=1 時，角落顯示目前畫質級別與每秒張數', async () => {
    window.history.replaceState({}, '', '/island?debug=1')
    const wrapper = await mountPage()

    sceneOptions().onStats({ level: 'medium', framesPerSecond: 42 })
    await flushPromises()

    expect(wrapper.get('[data-testid="world-debug"]').text()).toBe('畫質：中　每秒 42 張')
  })

  it('網址沒有 ?debug=1 時，不顯示畫質與每秒張數', async () => {
    const wrapper = await mountPage()

    sceneOptions().onStats({ level: 'low', framesPerSecond: 12 })
    await flushPromises()

    expect(wrapper.find('[data-testid="world-debug"]').exists()).toBe(false)
  })

  it('離開頁面時停掉 3D 畫面', async () => {
    const wrapper = await mountPage()

    wrapper.unmount()

    expect(scene.dispose).toHaveBeenCalledTimes(1)
  })

  it('不再有平面地圖的「看這裡的魚」入口', async () => {
    const wrapper = await mountPage()

    expect(wrapper.findAll('a').some((a) => a.text() === '看這裡的魚')).toBe(false)
    expect(wrapper.text()).not.toContain('點部落，角色就會走過去')
  })
})
