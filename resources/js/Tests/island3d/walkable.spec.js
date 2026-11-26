import { describe, expect, it } from 'vitest'
import { createWalkable } from '@/island3d/walkable'
import { NOTCH_IN_HEX, NOTCH_ON_ROAD, THREE_TRIBE_MAP } from './testMaps'

const walkable = createWalkable(THREE_TRIBE_MAP)

describe('island3d/walkable 可走範圍（#200 驗收條件 3）', () => {
  it('部落格子裡與公路上可以走', () => {
    expect(walkable.isWalkable({ x: 3, z: 4 })).toBe(true)
    expect(walkable.isWalkable({ x: 100, z: 108 })).toBe(true)
    expect(walkable.isWalkable({ x: 50, z: 1.5 })).toBe(true)
    expect(walkable.isWalkable({ x: 160, z: 50 })).toBe(true)
  })

  it('公路外側、格子外、兩條路中間的山都不能走', () => {
    expect(walkable.isWalkable({ x: 50, z: 2.5 })).toBe(false)
    expect(walkable.isWalkable({ x: 0, z: 9 })).toBe(false)
    expect(walkable.isWalkable({ x: 50, z: 50 })).toBe(false)
    expect(walkable.isWalkable({ x: 130, z: 50 })).toBe(false)
  })

  it('可走的點不會被移動', () => {
    expect(walkable.clampToWalkable({ x: 50, z: 1 })).toEqual({ x: 50, z: 1 })
  })

  it('走出公路的點被拉回公路邊上', () => {
    const clamped = walkable.clampToWalkable({ x: 50, z: 10 })

    expect(clamped.x).toBeCloseTo(50, 5)
    expect(clamped.z).toBeCloseTo(2, 5)
  })

  it('走出格子的點被拉回格子邊上', () => {
    const clamped = walkable.clampToWalkable({ x: 0, z: -20 })

    expect(clamped.x).toBeCloseTo(0, 5)
    expect(clamped.z).toBeCloseTo(-8.66025, 4)
  })

  it('被拉回來的點本身算可走（之後還能從那裡出發）', () => {
    const points = [
      { x: 50, z: 10 },
      { x: 0, z: -20 },
      { x: 9, z: 7 },
      { x: 170, z: 50 },
      { x: 200, z: -30 },
    ]

    points.forEach((point) => {
      expect(walkable.isWalkable(walkable.clampToWalkable(point))).toBe(true)
    })
  })

  it('點在哪個部落格子裡：格子裡回傳部落代碼，公路上與外面是 null', () => {
    expect(walkable.tribeAt({ x: 1, z: 1 })).toBe('west')
    expect(walkable.tribeAt({ x: 100, z: 100 })).toBe('south')
    expect(walkable.tribeAt({ x: 50, z: 0 })).toBeNull()
    expect(walkable.tribeAt({ x: 50, z: 50 })).toBeNull()
  })

  it('格子邊上的點算在那個部落裡', () => {
    expect(walkable.tribeAt({ x: 10, z: 0 })).toBe('west')
  })

  it('兩點之間的直線整段可走才算可以直走', () => {
    expect(walkable.isStraightWalkable({ x: 30, z: 1 }, { x: 70, z: -1 })).toBe(true)
    expect(walkable.isStraightWalkable({ x: 2, z: 3 }, { x: -4, z: -5 })).toBe(true)
    // 過轉角：直線會切過公路內側的山
    expect(walkable.isStraightWalkable({ x: 130, z: 0 }, { x: 160, z: 50 })).toBe(false)
    // 兩端都可走，中間不行
    expect(walkable.isStraightWalkable({ x: 0, z: 0 }, { x: 100, z: 100 })).toBe(false)
  })

  it('兩端都可走、相距很近，但連線穿過格子與公路交界的凹角：不能直走', () => {
    expect(walkable.isWalkable(NOTCH_IN_HEX)).toBe(true)
    expect(walkable.isWalkable(NOTCH_ON_ROAD)).toBe(true)
    expect(walkable.isWalkable({ x: 8.875, z: 2.025 })).toBe(false)

    expect(walkable.isStraightWalkable(NOTCH_IN_HEX, NOTCH_ON_ROAD)).toBe(false)
    expect(walkable.isStraightWalkable(NOTCH_ON_ROAD, NOTCH_IN_HEX)).toBe(false)
  })

  it('連線從格子走進公路、中間一直有地方可走：可以直走', () => {
    expect(walkable.isStraightWalkable({ x: 3, z: 1 }, { x: 40, z: -1 })).toBe(true)
  })

  it('起點和終點是同一點：可走的點算可以直走，不可走的點不算', () => {
    expect(walkable.isStraightWalkable({ x: 50, z: 1 }, { x: 50, z: 1 })).toBe(true)
    expect(walkable.isStraightWalkable({ x: 50, z: 50 }, { x: 50, z: 50 })).toBe(false)
  })

  it('往不可走的地方直走，最遠只能走到可走範圍的邊上', () => {
    // 從公路中線往正南走：公路半寬 2，所以走得到的是 (50, 2)
    const reached = walkable.reachToward({ x: 50, z: 0 }, { x: 50, z: 10 })

    expect(reached.x).toBeCloseTo(50, 5)
    expect(reached.z).toBeCloseTo(2, 5)
  })

  it('往可以直走的地方走：走得到終點', () => {
    expect(walkable.reachToward({ x: 30, z: 1 }, { x: 70, z: -1 })).toEqual({ x: 70, z: -1 })
  })

  it('往凹角對面走：停在凹角這一側的邊上，不會跳到對面', () => {
    const reached = walkable.reachToward(NOTCH_IN_HEX, NOTCH_ON_ROAD)

    expect(walkable.isWalkable(reached)).toBe(true)
    expect(walkable.tribeAt(reached)).toBe('west')
    expect(Math.hypot(reached.x - NOTCH_IN_HEX.x, reached.z - NOTCH_IN_HEX.z)).toBeLessThan(0.08)
  })
})
