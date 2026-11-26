import { describe, expect, it } from 'vitest'
import { isInsideHex } from '@/island3d/hexBounds'
import { wanderPosition, wanderRoute } from '@/island3d/wander'

const RADIUS = 10
// 兩端相距 6、速度 1.5 → 單程 4 秒；每端停 2 秒 → 來回一趟 12 秒
const ROUTE = { from: { x: -3, z: 2 }, to: { x: 3, z: 2 }, speed: 1.5, pauseSeconds: 2 }

describe('island3d/wander NPC 走動', () => {
  it('一開始站在起點，先停一下再出發', () => {
    expect(wanderPosition(0, ROUTE)).toEqual({ x: -3, z: 2 })
    expect(wanderPosition(2, ROUTE)).toEqual({ x: -3, z: 2 })
  })

  it('走到一半在中間，走完單程到終點並停一下', () => {
    expect(wanderPosition(4, ROUTE).x).toBeCloseTo(0, 5)
    expect(wanderPosition(6, ROUTE).x).toBeCloseTo(3, 5)
    expect(wanderPosition(7.9, ROUTE).x).toBeCloseTo(3, 5)
  })

  it('再走回起點，之後重複', () => {
    expect(wanderPosition(10, ROUTE).x).toBeCloseTo(0, 5)
    expect(wanderPosition(12, ROUTE).x).toBeCloseTo(-3, 5)
    expect(wanderPosition(12 + 4, ROUTE).x).toBeCloseTo(0, 5)
  })

  it('「減少動態效果」時站在起點不動', () => {
    expect(wanderPosition(4, ROUTE, { reducedMotion: true })).toEqual({ x: -3, z: 2 })
    expect(wanderPosition(9, ROUTE, { reducedMotion: true })).toEqual({ x: -3, z: 2 })
  })

  it('預設路線的兩端都在格子裡，整趟不會走出格子', () => {
    const route = wanderRoute(RADIUS)

    for (let seconds = 0; seconds <= 60; seconds += 0.25) {
      expect(isInsideHex(wanderPosition(seconds, route), RADIUS)).toBe(true)
    }
    expect(route.from).not.toEqual(route.to)
  })
})
