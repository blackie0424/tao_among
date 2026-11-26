import { describe, expect, it } from 'vitest'
import { clampToHex, isInsideHex } from '@/island3d/hexBounds'

// 平頂六角格，半徑 10：頂點在 (±10, 0)、(±5, ±8.66)；上下兩條邊在 z = ±8.66
const RADIUS = 10

describe('island3d/hexBounds 六角格邊界', () => {
  it('中心與格子內的點算在格子裡', () => {
    expect(isInsideHex({ x: 0, z: 0 }, RADIUS)).toBe(true)
    expect(isInsideHex({ x: 4, z: 8 }, RADIUS)).toBe(true)
    expect(isInsideHex({ x: 9.9, z: 0 }, RADIUS)).toBe(true)
  })

  it('格子外的點不算在格子裡（含外接圓內、六角形外的角落）', () => {
    expect(isInsideHex({ x: 0, z: 9 }, RADIUS)).toBe(false)
    expect(isInsideHex({ x: 10.5, z: 0 }, RADIUS)).toBe(false)
    expect(isInsideHex({ x: 8, z: 5 }, RADIUS)).toBe(false)
  })

  it('格子內的點不會被移動', () => {
    expect(clampToHex({ x: 3, z: -2 }, RADIUS)).toEqual({ x: 3, z: -2 })
  })

  it('走出上方的邊，會停在那條邊上', () => {
    const clamped = clampToHex({ x: 1, z: 20 }, RADIUS)

    expect(clamped.x).toBeCloseTo(1, 5)
    expect(clamped.z).toBeCloseTo(8.66025, 4)
  })

  it('走出頂點外，會停在頂點', () => {
    const clamped = clampToHex({ x: 30, z: 0 }, RADIUS)

    expect(clamped.x).toBeCloseTo(10, 5)
    expect(clamped.z).toBeCloseTo(0, 5)
  })
})

describe('island3d/hexBounds 不在原點的六角格（#200）', () => {
  const CENTER = { x: 100, z: -50 }

  it('以指定的中心判斷點在不在格子裡', () => {
    expect(isInsideHex({ x: 104, z: -42 }, RADIUS, CENTER)).toBe(true)
    expect(isInsideHex({ x: 100, z: -41 }, RADIUS, CENTER)).toBe(false)
    expect(isInsideHex({ x: 4, z: 8 }, RADIUS, CENTER)).toBe(false)
  })

  it('走出指定中心的格子，會停在那個格子的邊上', () => {
    const clamped = clampToHex({ x: 101, z: -20 }, RADIUS, CENTER)

    expect(clamped.x).toBeCloseTo(101, 5)
    expect(clamped.z).toBeCloseTo(-41.33975, 4)
  })

  it('拉回邊上的點本身算在格子裡', () => {
    const outside = [
      { x: 101, z: -20 },
      { x: 120, z: -47 },
      { x: 93, z: -70 },
    ]

    outside.forEach((point) => {
      expect(isInsideHex(clampToHex(point, RADIUS, CENTER), RADIUS, CENTER)).toBe(true)
    })
  })
})
