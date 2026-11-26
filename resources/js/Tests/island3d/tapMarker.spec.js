import { describe, expect, it } from 'vitest'
import {
  createMovementInput,
  destinationOf,
  pressKey,
  clearInput,
  stepMovement,
  tapGround,
} from '@/island3d/movement'
import { createRouter } from '@/island3d/route'
import {
  createTapMarker,
  markerScale,
  rejectTap,
  rejectedPointAt,
  tapOutcome,
} from '@/island3d/tapMarker'
import { createWalkable } from '@/island3d/walkable'
import { THREE_TRIBE_MAP } from './testMaps'

const walkable = createWalkable(THREE_TRIBE_MAP)
const WORLD = { ...walkable, findRoute: createRouter(THREE_TRIBE_MAP, walkable) }
const START = { x: 0, z: 0 }
const OPTIONS = { speed: 5, world: WORLD }

/** 依序走幾步（每步 0.05 秒） */
const walk = (input, position, steps) => {
  let current = { input, position }
  for (let i = 0; i < steps; i++) {
    current = stepMovement(current.input, current.position, 0.05, OPTIONS)
  }
  return current
}

describe('island3d 點地面的提示：目的地的圓圈（#200 驗收條件 4，規格修訂 3）', () => {
  it('還沒點地面時沒有目的地', () => {
    expect(destinationOf(createMovementInput())).toBeNull()
  })

  it('點可走的位置：目的地就是點的那一點（不是路線中途的折點）', () => {
    const input = tapGround(createMovementInput(), { x: 160, z: 50 }, START, WORLD)

    expect(destinationOf(input)).toEqual({ x: 160, z: 50 })
  })

  it('走到一半目的地不變，走到就沒有目的地', () => {
    const tapped = tapGround(createMovementInput(), { x: 20, z: 0 }, START, WORLD)
    const midway = walk(tapped, START, 20)
    const arrived = walk(midway.input, midway.position, 80)

    expect(destinationOf(midway.input)).toEqual({ x: 20, z: 0 })
    expect(arrived.position).toEqual({ x: 20, z: 0 })
    expect(destinationOf(arrived.input)).toBeNull()
  })

  it('途中改點別處：目的地換成新的那一點', () => {
    const first = tapGround(createMovementInput(), { x: 100, z: 0 }, START, WORLD)
    const midway = walk(first, START, 20)
    const second = tapGround(midway.input, { x: 3, z: 4 }, midway.position, WORLD)

    expect(destinationOf(second)).toEqual({ x: 3, z: 4 })
  })

  it('改用方向鍵走、或視窗失焦：沒有目的地', () => {
    const tapped = tapGround(createMovementInput(), { x: 100, z: 0 }, START, WORLD)

    expect(destinationOf(pressKey(tapped, 'ArrowLeft'))).toBeNull()
    expect(destinationOf(clearInput(tapped))).toBeNull()
  })

  it('點到不能走的地方：原本的目的地不變', () => {
    const tapped = tapGround(createMovementInput(), { x: 100, z: 0 }, START, WORLD)
    const again = tapGround(tapped, { x: 50, z: 50 }, START, WORLD)

    expect(destinationOf(again)).toEqual({ x: 100, z: 0 })
  })
})

describe('island3d/tapMarker 這次輕點算什麼', () => {
  const idle = createMovementInput()

  it('點可走的位置：接受', () => {
    const after = tapGround(idle, { x: 20, z: 0 }, START, WORLD)

    expect(tapOutcome(idle, after)).toBe('accepted')
  })

  it('已經有目的地時再點同一個位置：仍然是接受', () => {
    const first = tapGround(idle, { x: 20, z: 0 }, START, WORLD)
    const again = tapGround(first, { x: 20, z: 0 }, START, WORLD)

    expect(tapOutcome(first, again)).toBe('accepted')
  })

  it('點到不能走的地方（輸入沒有變）：拒絕', () => {
    const after = tapGround(idle, { x: 50, z: 50 }, START, WORLD)

    expect(tapOutcome(idle, after)).toBe('rejected')
  })

  it('被山或船擋住時呼叫端不會改輸入：拒絕', () => {
    expect(tapOutcome(idle, idle)).toBe('rejected')
  })

  it('按住方向鍵時點地面：不理會，不算拒絕', () => {
    const held = pressKey(idle, 'ArrowRight')
    const after = tapGround(held, { x: 20, z: 0 }, START, WORLD)

    expect(tapOutcome(held, after)).toBe('ignored')
  })
})

describe('island3d/tapMarker 點到不能走的地方的灰色圓圈', () => {
  it('一開始沒有', () => {
    expect(rejectedPointAt(createTapMarker(), 0)).toBeNull()
  })

  it('出現在點的位置，0.5 秒內看得到，滿 0.5 秒就消失', () => {
    const marker = rejectTap(createTapMarker(), { x: 50, z: 50 }, 10)

    expect(rejectedPointAt(marker, 10)).toEqual({ x: 50, z: 50 })
    expect(rejectedPointAt(marker, 10.49)).toEqual({ x: 50, z: 50 })
    expect(rejectedPointAt(marker, 10.5)).toBeNull()
    expect(rejectedPointAt(marker, 60)).toBeNull()
  })

  it('還沒消失又點了另一個不能走的地方：換到新的位置，重新算 0.5 秒', () => {
    const first = rejectTap(createTapMarker(), { x: 50, z: 50 }, 10)
    const second = rejectTap(first, { x: 60, z: 40 }, 10.3)

    expect(rejectedPointAt(second, 10.4)).toEqual({ x: 60, z: 40 })
    expect(rejectedPointAt(second, 10.79)).toEqual({ x: 60, z: 40 })
    expect(rejectedPointAt(second, 10.8)).toBeNull()
  })
})

describe('island3d/tapMarker 圓圈大小跟著鏡頭遠近', () => {
  it('鏡頭在最近的距離時是原本大小', () => {
    expect(markerScale(17, 17)).toBe(1)
  })

  it('距離變 4 倍時放大 2 倍、變 9 倍時放大 3 倍', () => {
    expect(markerScale(68, 17)).toBeCloseTo(2, 5)
    expect(markerScale(153, 17)).toBeCloseTo(3, 5)
  })
})
