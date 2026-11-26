import { describe, expect, it } from 'vitest'
import {
  clearInput,
  createMovementInput,
  isMoving,
  pressKey,
  releaseKey,
  stepMovement,
  tapGround,
} from '@/island3d/movement'
import { createRouter } from '@/island3d/route'
import { createWalkable } from '@/island3d/walkable'
import { BEND_NORTH, EAST, ONE_HEX_MAP, THREE_TRIBE_MAP } from './testMaps'

/** 把地圖變成走路規則要的「世界」：可走範圍＋找路 */
const worldOf = (map) => {
  const walkable = createWalkable(map)
  return { ...walkable, findRoute: createRouter(map, walkable) }
}

// 一個半徑 10 的六角格（等於 #199 的單一格子）
const WORLD = worldOf(ONE_HEX_MAP)
const OPTIONS = { speed: 2, world: WORLD }
const ORIGIN = { x: 0, z: 0 }

/** 依序走幾步（每步 dt 秒），回傳最後的位置與輸入狀態 */
const walk = (input, position, steps, dt = 0.05, options = OPTIONS) => {
  let current = { input, position }
  for (let i = 0; i < steps; i++) {
    current = stepMovement(current.input, current.position, dt, options)
  }
  return current
}

describe('island3d/movement 輸入與移動規則', () => {
  it('按住右鍵往 +x 走：速度 2、走 1 秒＝2', () => {
    const { position } = walk(pressKey(createMovementInput(), 'ArrowRight'), ORIGIN, 20)

    expect(position.x).toBeCloseTo(2, 5)
    expect(position.z).toBeCloseTo(0, 5)
  })

  it('上鍵往 -z（畫面遠處）、下鍵往 +z、左鍵往 -x', () => {
    expect(walk(pressKey(createMovementInput(), 'ArrowUp'), ORIGIN, 20).position.z).toBeCloseTo(
      -2,
      5
    )
    expect(walk(pressKey(createMovementInput(), 'ArrowDown'), ORIGIN, 20).position.z).toBeCloseTo(
      2,
      5
    )
    expect(walk(pressKey(createMovementInput(), 'ArrowLeft'), ORIGIN, 20).position.x).toBeCloseTo(
      -2,
      5
    )
  })

  it('同時按兩個方向鍵往斜向走，速度不加倍', () => {
    const input = pressKey(pressKey(createMovementInput(), 'ArrowRight'), 'ArrowDown')
    const { position } = walk(input, ORIGIN, 20)

    expect(Math.hypot(position.x, position.z)).toBeCloseTo(2, 5)
    expect(position.x).toBeCloseTo(position.z, 5)
  })

  it('不是方向鍵的按鍵不會讓角色移動', () => {
    const input = pressKey(createMovementInput(), 'a')

    expect(isMoving(input)).toBe(false)
    expect(walk(input, ORIGIN, 20).position).toEqual(ORIGIN)
  })

  it('放開所有方向鍵，角色立刻停下', () => {
    const held = pressKey(createMovementInput(), 'ArrowRight')
    const moved = walk(held, ORIGIN, 10)
    const released = releaseKey(moved.input, 'ArrowRight')
    const after = walk(released, moved.position, 10)

    expect(isMoving(released)).toBe(false)
    expect(after.position).toEqual(moved.position)
  })

  it('點地面：角色走到那一點就停，不會走過頭', () => {
    const input = tapGround(createMovementInput(), { x: 3, z: 0 }, ORIGIN, WORLD)
    const { position, input: after } = walk(input, ORIGIN, 40)

    expect(position).toEqual({ x: 3, z: 0 })
    expect(isMoving(after)).toBe(false)
  })

  it('點格子外（山、海）沒有反應', () => {
    const input = tapGround(createMovementInput(), { x: 0, z: 30 }, ORIGIN, WORLD)

    expect(isMoving(input)).toBe(false)
  })

  it('走向點選位置的途中再點另一處：改走向新的位置', () => {
    const first = tapGround(createMovementInput(), { x: 5, z: 0 }, ORIGIN, WORLD)
    const midway = walk(first, ORIGIN, 10)
    const retargeted = tapGround(midway.input, { x: 1, z: 4 }, midway.position, WORLD)
    const { position } = walk(retargeted, midway.position, 100)

    expect(midway.position.x).toBeCloseTo(1, 5)
    expect(position).toEqual({ x: 1, z: 4 })
  })

  it('走向點選位置的途中按下方向鍵：取消點選的目的地，改由方向鍵控制', () => {
    const tapped = tapGround(createMovementInput(), { x: 5, z: 0 }, ORIGIN, WORLD)
    const midway = walk(tapped, ORIGIN, 10)
    const keyed = pressKey(midway.input, 'ArrowDown')
    const moved = walk(keyed, midway.position, 10)
    const stopped = walk(releaseKey(moved.input, 'ArrowDown'), moved.position, 40)

    expect(moved.position.x).toBeCloseTo(1, 5)
    expect(moved.position.z).toBeCloseTo(1, 5)
    // 放開後不會回頭走向原本點的 (5, 0)
    expect(stopped.position).toEqual(moved.position)
  })

  it('按住方向鍵時點地面：不理會這次點選', () => {
    const held = pressKey(createMovementInput(), 'ArrowRight')
    const tapped = tapGround(held, { x: -5, z: 0 }, ORIGIN, WORLD)
    const released = releaseKey(tapped, 'ArrowRight')

    expect(isMoving(released)).toBe(false)
  })

  it('視窗失去焦點或切到背景：清掉按住的方向鍵與點選的目的地', () => {
    const held = pressKey(createMovementInput(), 'ArrowRight')
    const tapped = tapGround(createMovementInput(), { x: 5, z: 0 }, ORIGIN, WORLD)

    expect(isMoving(clearInput(held))).toBe(false)
    expect(isMoving(clearInput(tapped))).toBe(false)
  })

  it('走到邊緣就停在邊緣，走不出格子', () => {
    const { position } = walk(pressKey(createMovementInput(), 'ArrowDown'), ORIGIN, 400)

    expect(position.x).toBeCloseTo(0, 5)
    expect(position.z).toBeCloseTo(8.66025, 4)
  })

  it('兩張畫面隔很久（分頁從背景回來）時，角色不會瞬間跳一大段', () => {
    const input = pressKey(createMovementInput(), 'ArrowRight')
    const { position } = stepMovement(input, ORIGIN, 30, OPTIONS)

    // 單步最多算 0.1 秒：速度 2 → 最多 0.2
    expect(position.x).toBeCloseTo(0.2, 5)
  })
})

describe('island3d/movement 整座島的走路規則（#200）', () => {
  const ISLAND = worldOf(THREE_TRIBE_MAP)
  const ISLAND_OPTIONS = { speed: 5, world: ISLAND }

  it('鏡頭轉了四分之一圈（從 +x 那一側往 -x 看）：上鍵往 -x（畫面遠處）、左鍵往 +z（畫面左邊）', () => {
    const turned = { ...OPTIONS, heading: Math.PI / 2 }
    const up = walk(pressKey(createMovementInput(), 'ArrowUp'), ORIGIN, 20, 0.05, turned)
    const left = walk(pressKey(createMovementInput(), 'ArrowLeft'), ORIGIN, 20, 0.05, turned)

    expect(up.position.x).toBeCloseTo(-2, 5)
    expect(up.position.z).toBeCloseTo(0, 5)
    expect(left.position.x).toBeCloseTo(0, 5)
    expect(left.position.z).toBeCloseTo(2, 5)
  })

  it('鏡頭轉了半圈：上鍵往 +z、右鍵往 -x', () => {
    const turned = { ...OPTIONS, heading: Math.PI }
    const up = walk(pressKey(createMovementInput(), 'ArrowUp'), ORIGIN, 20, 0.05, turned)
    const right = walk(pressKey(createMovementInput(), 'ArrowRight'), ORIGIN, 20, 0.05, turned)

    expect(up.position.z).toBeCloseTo(2, 5)
    expect(right.position.x).toBeCloseTo(-2, 5)
  })

  it('用方向鍵往公路外走：停在公路邊上，並沿著邊往前滑', () => {
    const input = pressKey(pressKey(createMovementInput(), 'ArrowRight'), 'ArrowDown')
    const { position } = walk(input, { x: 40, z: 0 }, 40, 0.05, ISLAND_OPTIONS)

    expect(position.z).toBeCloseTo(2, 5)
    expect(position.x).toBeGreaterThan(41)
    expect(ISLAND.isWalkable(position)).toBe(true)
  })

  it('用方向鍵沿公路可以走出部落格子、走進下一個部落', () => {
    const { position } = walk(
      pressKey(createMovementInput(), 'ArrowRight'),
      { x: 0, z: 0 },
      400,
      0.05,
      ISLAND_OPTIONS
    )

    // 400 步 × 0.05 秒 × 速度 5 = 100：剛好走到 east 的中心
    expect(position.x).toBeCloseTo(100, 4)
    expect(ISLAND.tribeAt(position)).toBe('east')
  })

  it('點另一個部落：依序經過路線上的每個點，走到目的地就停', () => {
    const target = { x: 160, z: 50 }
    const input = tapGround(createMovementInput(), target, { x: 0, z: 0 }, ISLAND)
    // 路線 (0,0) → east (100,0) → 折點 (160,0) → (160,50)，全長 210；速度 5 要 42 秒
    const before = walk(input, { x: 0, z: 0 }, 41 * 20, 0.05, ISLAND_OPTIONS)
    const after = walk(before.input, before.position, 2 * 20, 0.05, ISLAND_OPTIONS)

    expect(isMoving(before.input)).toBe(true)
    expect(before.position.x).toBeCloseTo(160, 4)
    expect(before.position.z).toBeCloseTo(45, 4)
    expect(after.position).toEqual(target)
    expect(isMoving(after.input)).toBe(false)
  })

  it('沿路線走的途中每一步都在可走範圍內，而且會經過折點', () => {
    let current = {
      input: tapGround(createMovementInput(), { x: 160, z: 50 }, { x: 0, z: 0 }, ISLAND),
      position: { x: 0, z: 0 },
    }
    const visited = []
    while (isMoving(current.input)) {
      current = stepMovement(current.input, current.position, 0.05, ISLAND_OPTIONS)
      visited.push(current.position)
    }

    visited.forEach((position) => expect(ISLAND.isWalkable(position)).toBe(true))
    expect(visited.some((p) => Math.hypot(p.x - EAST.x, p.z - EAST.z) < 0.3)).toBe(true)
    expect(visited.some((p) => Math.hypot(p.x - BEND_NORTH.x, p.z - BEND_NORTH.z) < 0.3)).toBe(true)
  })

  it('一步跨過路線上的點時不會多走也不會少走', () => {
    const input = tapGround(createMovementInput(), { x: 160, z: 50 }, { x: 99.9, z: 0 }, ISLAND)
    // 單步 0.1 秒 × 速度 5 = 0.5：先走 0.1 到 east，剩下 0.4 繼續往折點走
    const { position } = stepMovement(input, { x: 99.9, z: 0 }, 0.1, ISLAND_OPTIONS)

    expect(position.x).toBeCloseTo(100.4, 5)
    expect(position.z).toBeCloseTo(0, 5)
  })

  it('走到一半點山或海：沒有反應，原本的目的地不變', () => {
    const first = tapGround(createMovementInput(), { x: 100, z: 0 }, { x: 0, z: 0 }, ISLAND)
    const midway = walk(first, { x: 0, z: 0 }, 20, 0.05, ISLAND_OPTIONS)
    const tapped = tapGround(midway.input, { x: 50, z: 50 }, midway.position, ISLAND)
    const { position } = walk(tapped, midway.position, 800, 0.05, ISLAND_OPTIONS)

    expect(tapped).toBe(midway.input)
    expect(position).toEqual({ x: 100, z: 0 })
  })
})
