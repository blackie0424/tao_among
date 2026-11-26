import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import IslandMap from '@/Components/Island/IslandMap.vue'

const TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']

const mountMap = (onArrive = vi.fn()) =>
  mount(IslandMap, {
    props: { tribes: TRIBES, currentTribe: 'yayo', onArrive },
    attachTo: document.body,
  })

const tribePoint = (wrapper, tribe) => wrapper.find(`[data-tribe="${tribe}"]`)

describe('IslandMap 地圖（#184）', () => {
  afterEach(() => vi.useRealTimers())

  it('每個部落點都有鍵盤焦點時才顯示的焦點外框', () => {
    const wrapper = mountMap()

    for (const tribe of TRIBES) {
      const point = tribePoint(wrapper, tribe)
      const ring = point.find('[data-testid="focus-ring"]')
      expect(point.classes()).toContain('group')
      expect(ring.exists()).toBe(true)
      expect(ring.classes()).toContain('opacity-0')
      expect(ring.classes()).toContain('group-focus-visible:opacity-100')
    }
  })

  it('行走中離開地圖頁（元件卸載）後，計時器不會再觸發抵達', () => {
    vi.useFakeTimers()
    const onArrive = vi.fn()
    const wrapper = mountMap(onArrive)

    tribePoint(wrapper, 'iratay').trigger('click')
    wrapper.unmount()
    vi.advanceTimersByTime(5000)

    expect(onArrive).not.toHaveBeenCalled()
  })

  it('沒有離開時，計時器照常在走完後觸發抵達', () => {
    vi.useFakeTimers()
    const onArrive = vi.fn()
    const wrapper = mountMap(onArrive)

    tribePoint(wrapper, 'iratay').trigger('click')
    vi.advanceTimersByTime(5000)

    expect(onArrive).toHaveBeenCalledWith('iratay')
  })
})
