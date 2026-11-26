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

/** 假的寫入點：每次呼叫回傳一個由測試決定何時成功或失敗的請求 */
const stubLocationRequests = () => {
  const requests = []
  const post = vi.fn(
    () =>
      new Promise((resolve, reject) => {
        requests.push({ resolve: () => resolve({ status: 204 }), reject })
      })
  )
  vi.stubGlobal('axios', { post })
  return { post, requests }
}

/** 角色走進某個部落的格子（3D 畫面回報） */
const walkInto = async (tribe) => {
  sceneOptions().onTribeChange(tribe)
  await flushPromises()
}

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

  it('#207 3D 畫面下方標示地圖資料的來源（OpenStreetMap 的授權要求）', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-testid="map-credit"]').text()).toBe(
      '地圖資料 © OpenStreetMap contributors'
    )
  })

  it('#200 角色的起點是目前所在部落', async () => {
    await mountPage()

    expect(sceneOptions().startTribe).toBe('iratay')
  })

  it('#200 走進另一個部落：左上角標示與「你現在在」立刻換成那個部落，「你的部落」不變', async () => {
    stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')

    expect(wrapper.get('[data-testid="world-tribe"]').text()).toBe('ivalino（野銀）')
    expect(valueOf(wrapper, '你現在在')).toBe('ivalino（野銀）')
    expect(valueOf(wrapper, '你的部落')).toBe('yayo（椰油）')
  })

  it('#200 走進另一個部落：把那個部落記到伺服器，不換頁', async () => {
    const { post } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')

    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/island/location', { tribe: 'ivalino' })
    expect(scene.createIslandScene).toHaveBeenCalledTimes(1)
  })

  it('#200 記到伺服器失敗：標示照常換、不顯示錯誤', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { requests } = stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()

    expect(valueOf(wrapper, '你現在在')).toBe('ivalino（野銀）')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('#200 前一筆還沒回來時又走進兩個部落：只留最新的，前一筆回來後才送，中間那個不送', async () => {
    const { post, requests } = stubLocationRequests()
    const wrapper = await mountPage()

    await walkInto('ivalino')
    await walkInto('iranmeilek')
    await walkInto('iraraley')

    expect(post).toHaveBeenCalledTimes(1)
    expect(valueOf(wrapper, '你現在在')).toBe('iraraley（朗島）')

    requests[0].resolve()
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iraraley'])
  })

  it('#200 前一筆失敗：不重送那一筆，排隊中的最新部落照樣送', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    await walkInto('iraraley')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iraraley'])
  })

  it('#200 前一筆失敗、沒有排隊中的部落：不自動重送，等下一次走進別的部落再記', async () => {
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    requests[0].reject(new Error('Network Error'))
    await flushPromises()
    expect(post).toHaveBeenCalledTimes(1)

    await walkInto('iranmeilek')

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'iranmeilek'])
  })

  it('#200 一筆一筆送：每一筆回來後再走進別的部落，照順序各送一次', async () => {
    const { post, requests } = stubLocationRequests()
    await mountPage()

    await walkInto('ivalino')
    requests[0].resolve()
    await flushPromises()
    await walkInto('imowrod')
    requests[1].resolve()
    await flushPromises()

    expect(post.mock.calls.map(([, body]) => body.tribe)).toEqual(['ivalino', 'imowrod'])
  })

  it('#200 瀏覽器無法建立 3D 畫面時不呼叫寫入點', async () => {
    const { post } = stubLocationRequests()
    scene.createIslandScene.mockImplementation(() => {
      throw new Error('Error creating WebGL context.')
    })

    await mountPage()

    expect(post).not.toHaveBeenCalled()
  })

  it('#200 操作說明提到走路、轉鏡頭與拉遠拉近', async () => {
    const wrapper = await mountPage()

    expect(wrapper.text()).toContain('用方向鍵走路，或點地面走過去')
    expect(wrapper.text()).toContain('拖曳畫面轉鏡頭')
    expect(wrapper.text()).toContain('滾輪或雙指拉遠拉近')
  })
})
