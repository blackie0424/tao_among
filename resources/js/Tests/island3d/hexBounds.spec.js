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
