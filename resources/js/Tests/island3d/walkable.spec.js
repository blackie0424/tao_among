import { describe, expect, it } from 'vitest'
import { createWalkable } from '@/island3d/walkable'
import { footprintCorners } from '@/island3d/landmarks'
import {
  NOTCH_IN_HEX,
  NOTCH_ON_ROAD,
  ONE_HEX_MAP,
  THREE_TRIBE_MAP,
  TWIN_HEX_MAP,
  TWIN_SECOND,
} from './testMaps'

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

describe('island3d/walkable 一個部落有好幾格（#212 驗收條件 4、5）', () => {
  const twin = createWalkable(TWIN_HEX_MAP)

  it('第 2 格裡也可以走，所在部落和主格一樣', () => {
    expect(twin.isWalkable(TWIN_SECOND)).toBe(true)
    expect(twin.tribeAt(TWIN_SECOND)).toBe('twin')
    expect(twin.tribeAt({ x: 0, z: 0 })).toBe('twin')
    // 第 2 格的最右邊（主格到不了的地方）
    expect(twin.tribeAt({ x: 24, z: 8.66 })).toBe('twin')
  })

  it('兩格以外的地方不能走、不在任何部落', () => {
    expect(twin.isWalkable({ x: 30, z: 8.66 })).toBe(false)
    expect(twin.tribeAt({ x: 30, z: 8.66 })).toBeNull()
  })

  it('從主格中心到第 2 格中心：直走，穿過共用的邊，中間沒有縫', () => {
    expect(twin.isStraightWalkable({ x: 0, z: 0 }, TWIN_SECOND)).toBe(true)
    expect(twin.reachToward({ x: 0, z: 0 }, TWIN_SECOND)).toEqual(TWIN_SECOND)
  })

  it('走出第 2 格的點被拉回第 2 格的邊上，不是拉回主格', () => {
    // 第 2 格的右頂點在 (25, 8.66)
    const clamped = twin.clampToWalkable({ x: 40, z: 8.66 })

    expect(clamped.x).toBeCloseTo(25, 2)
    expect(clamped.z).toBeCloseTo(8.66, 2)
  })
})

describe('island3d/walkable 擋路的東西（#201 驗收條件 3）', () => {
  // 只有一個半徑 10 的格子；格子裡一個 3 × 2 的東西，x 從 2 到 5、z 從 -1 到 1
  const BLOCK = footprintCorners({ x: 3.5, z: 0, width: 3, depth: 2, heading: 0 })
  const blocked = createWalkable(ONE_HEX_MAP, [BLOCK])

  it('東西裡面不能走，旁邊和邊上可以走', () => {
    expect(blocked.isWalkable({ x: 3.5, z: 0 })).toBe(false)
    expect(blocked.isWalkable({ x: 1, z: 0 })).toBe(true)
    expect(blocked.isWalkable({ x: 2, z: 0 })).toBe(true)
  })

  it('直直走向東西：停在東西的邊上', () => {
    const reached = blocked.reachToward({ x: 0, z: 0 }, { x: 8, z: 0 })

    expect(reached.x).toBeCloseTo(2, 5)
    expect(reached.z).toBeCloseTo(0, 5)
    expect(blocked.isStraightWalkable({ x: 0, z: 0 }, { x: 8, z: 0 })).toBe(false)
  })

  it('從東西旁邊經過、從邊上往外走都可以', () => {
    expect(blocked.isStraightWalkable({ x: 0, z: 3 }, { x: 8, z: 3 })).toBe(true)
    expect(blocked.isStraightWalkable({ x: 2, z: 0 }, { x: -3, z: 0 })).toBe(true)
  })

  it('落在東西裡的點被推到最近的邊上', () => {
    const clamped = blocked.clampToWalkable({ x: 2.3, z: 0 })

    expect(clamped.x).toBeCloseTo(2, 5)
    expect(clamped.z).toBeCloseTo(0, 5)
    expect(blocked.isWalkable(clamped)).toBe(true)
  })

  it('沒有給擋路的東西時和原本一樣', () => {
    expect(createWalkable(ONE_HEX_MAP).isWalkable({ x: 3.5, z: 0 })).toBe(true)
  })
})
