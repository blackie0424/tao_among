import { describe, expect, it } from 'vitest'
import { createRouter } from '@/island3d/route'
import { createWalkable } from '@/island3d/walkable'
import { BEND_NORTH, EAST, SOUTH, THREE_TRIBE_MAP } from './testMaps'

const walkable = createWalkable(THREE_TRIBE_MAP)
const findRoute = createRouter(THREE_TRIBE_MAP, walkable)

describe('island3d/route 點地面走過去的路線（#200 驗收條件 4）', () => {
  it('同一個格子裡的兩點：直接走直線，不繞格子中心', () => {
    expect(findRoute({ x: 2, z: 3 }, { x: -4, z: -5 })).toEqual([{ x: -4, z: -5 }])
  })

  it('同一段直的公路上的兩點：直接走直線', () => {
    expect(findRoute({ x: 30, z: 1 }, { x: 70, z: -1 })).toEqual([{ x: 70, z: -1 }])
  })

  it('過公路轉角：經過折點，不切過內側', () => {
    expect(findRoute({ x: 130, z: 0 }, { x: 160, z: 50 })).toEqual([BEND_NORTH, { x: 160, z: 50 }])
  })

  it('跨部落：走最短的那條公路（east → south 直走，不繞兩個折點）', () => {
    expect(findRoute({ x: 0, z: 0 }, { x: 100, z: 100 })).toEqual([EAST, SOUTH])
  })

  it('起點不在路線點上也找得到：先走到最近可直走的路線點', () => {
    expect(findRoute({ x: 30, z: 1 }, { x: 100, z: 95 })).toEqual([EAST, { x: 100, z: 95 }])
  })

  it('目的地在山或海（不可走）：沒有路線', () => {
    expect(findRoute({ x: 0, z: 0 }, { x: 50, z: 50 })).toBeNull()
    expect(findRoute({ x: 0, z: 0 }, { x: -40, z: 0 })).toBeNull()
  })

  it('路線上每一段都整段可走', () => {
    const from = { x: -3, z: 2 }
    const route = findRoute(from, { x: 160, z: 70 })
    const stops = [from, ...route]

    stops.slice(1).forEach((to, i) => {
      expect(walkable.isStraightWalkable(stops[i], to)).toBe(true)
    })
  })

  it('回傳的是新的點，改它不會動到地圖資料', () => {
    const route = findRoute({ x: 0, z: 0 }, { x: 100, z: 100 })
    route[0].x = 999

    expect(EAST.x).toBe(100)
  })
})
