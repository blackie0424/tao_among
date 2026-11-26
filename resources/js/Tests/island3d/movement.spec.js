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

const OPTIONS = { speed: 2, radius: 10 }
const ORIGIN = { x: 0, z: 0 }

/** 依序走幾步（每步 dt 秒），回傳最後的位置與輸入狀態 */
const walk = (input, position, steps, dt = 0.05) => {
  let current = { input, position }
  for (let i = 0; i < steps; i++) {
    current = stepMovement(current.input, current.position, dt, OPTIONS)
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
    const input = tapGround(createMovementInput(), { x: 3, z: 0 }, OPTIONS.radius)
    const { position, input: after } = walk(input, ORIGIN, 40)

    expect(position).toEqual({ x: 3, z: 0 })
    expect(isMoving(after)).toBe(false)
  })

  it('點格子外（山、海）沒有反應', () => {
    const input = tapGround(createMovementInput(), { x: 0, z: 30 }, OPTIONS.radius)

    expect(isMoving(input)).toBe(false)
  })

  it('走向點選位置的途中再點另一處：改走向新的位置', () => {
    const first = tapGround(createMovementInput(), { x: 5, z: 0 }, OPTIONS.radius)
    const midway = walk(first, ORIGIN, 10)
    const retargeted = tapGround(midway.input, { x: 1, z: 4 }, OPTIONS.radius)
    const { position } = walk(retargeted, midway.position, 100)

    expect(midway.position.x).toBeCloseTo(1, 5)
    expect(position).toEqual({ x: 1, z: 4 })
  })

  it('走向點選位置的途中按下方向鍵：取消點選的目的地，改由方向鍵控制', () => {
    const tapped = tapGround(createMovementInput(), { x: 5, z: 0 }, OPTIONS.radius)
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
    const tapped = tapGround(held, { x: -5, z: 0 }, OPTIONS.radius)
    const released = releaseKey(tapped, 'ArrowRight')

    expect(isMoving(released)).toBe(false)
  })

  it('視窗失去焦點或切到背景：清掉按住的方向鍵與點選的目的地', () => {
    const held = pressKey(createMovementInput(), 'ArrowRight')
    const tapped = tapGround(createMovementInput(), { x: 5, z: 0 }, OPTIONS.radius)

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
