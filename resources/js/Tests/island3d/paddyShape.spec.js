import { describe, expect, it } from 'vitest'
import { PADDY_COLORS, paddyShape } from '@/island3d/paddyShape'

// 島上水田的大小（islandMap.js 的 PADDY）
const WIDTH = 6
const DEPTH = 4
const EPSILON = 1e-9

/** 點是否在長方形 { x, z, width, depth }（中心與大小）裡 */
const inside = (point, rect) =>
  Math.abs(point.x - rect.x) <= rect.width / 2 + EPSILON &&
  Math.abs(point.z - rect.z) <= rect.depth / 2 + EPSILON

describe('island3d/paddyShape 梯田（#211 驗收條件 11）', () => {
  const shape = paddyShape(WIDTH, DEPTH)

  it('分 3 層，一層比一層高；高的在 -z 那一邊（後面），低的在 +z', () => {
    const { terraces } = shape

    expect(terraces).toHaveLength(3)
    terraces.slice(1).forEach((terrace, i) => {
      expect(terrace.level).toBeGreaterThan(terraces[i].level)
      expect(terrace.z).toBeLessThan(terraces[i].z)
    })
  })

  it('3 層剛好排滿整塊田：每層和田一樣寬，深度加起來是田的深度，都在田裡', () => {
    const { terraces } = shape
    const total = terraces.reduce((sum, terrace) => sum + terrace.depth, 0)

    expect(total).toBeCloseTo(DEPTH, 9)
    terraces.forEach((terrace) => {
      expect(terrace.width).toBeCloseTo(WIDTH, 9)
      expect(Math.abs(terrace.z) + terrace.depth / 2).toBeLessThanOrEqual(DEPTH / 2 + EPSILON)
    })
  })

  it('每層有水面，水面在那一層裡、比那一層的土高', () => {
    shape.terraces.forEach((terrace) => {
      const { water } = terrace
      expect(water.width).toBeGreaterThan(0)
      expect(water.depth).toBeGreaterThan(0)
      expect(inside({ x: water.x - water.width / 2, z: water.z - water.depth / 2 }, terrace)).toBe(
        true
      )
      expect(inside({ x: water.x + water.width / 2, z: water.z + water.depth / 2 }, terrace)).toBe(
        true
      )
      expect(water.level).toBeGreaterThan(terrace.level)
    })
  })

  it('每層靠低處那一邊（+z）有一排灰色石頭：在那一層裡、在水面的 +z 外側、從左排到右', () => {
    shape.terraces.forEach((terrace, i) => {
      const stones = shape.stones.filter((stone) => stone.terrace === i)
      expect(stones.length).toBeGreaterThanOrEqual(10)
      stones.forEach((stone) => {
        expect(PADDY_COLORS.stones).toContain(stone.color)
        expect(stone.z - stone.depth / 2).toBeGreaterThanOrEqual(
          terrace.water.z + terrace.water.depth / 2 - EPSILON
        )
        expect(stone.z + stone.depth / 2).toBeLessThanOrEqual(
          terrace.z + terrace.depth / 2 + EPSILON
        )
        expect(Math.abs(stone.x) + stone.width / 2).toBeLessThanOrEqual(WIDTH / 2 + EPSILON)
      })
      const xs = stones.map((stone) => stone.x)
      expect(Math.min(...xs)).toBeLessThan(-WIDTH / 2 + 0.5)
      expect(Math.max(...xs)).toBeGreaterThan(WIDTH / 2 - 0.5)
    })
  })

  it('顏色照規格：水 #6fb7c9、土 #6b5a45、石頭 #9aa0a6 與 #7d848b', () => {
    expect(PADDY_COLORS.water).toBe('#6fb7c9')
    expect(PADDY_COLORS.soil).toBe('#6b5a45')
    expect([...PADDY_COLORS.stones].sort()).toEqual(['#7d848b', '#9aa0a6'])
    expect(new Set(shape.stones.map((stone) => stone.color))).toEqual(new Set(PADDY_COLORS.stones))
  })
})

describe('island3d/paddyShape 芋頭（#211 驗收條件 12）', () => {
  const shape = paddyShape(WIDTH, DEPTH)

  it('每塊田至少 20 株', () => {
    expect(shape.taro.length).toBeGreaterThanOrEqual(20)
  })

  it('每株都在它那一層的水面範圍裡，站在水面上', () => {
    shape.taro.forEach((plant) => {
      const { water } = shape.terraces[plant.terrace]
      expect(inside(plant, water)).toBe(true)
      expect(plant.level).toBeCloseTo(water.level, 9)
    })
  })

  it('3 層都有種', () => {
    expect(new Set(shape.taro.map((plant) => plant.terrace))).toEqual(new Set([0, 1, 2]))
  })

  it('每株的大小與朝向有一點不同，但每次算出來都一樣（固定的亂數種子）', () => {
    const again = paddyShape(WIDTH, DEPTH)

    expect(again).toEqual(shape)
    expect(new Set(shape.taro.map((plant) => plant.scale.toFixed(6))).size).toBeGreaterThan(1)
    shape.taro.forEach((plant) => {
      expect(plant.scale).toBeGreaterThan(0.5)
      expect(plant.scale).toBeLessThanOrEqual(1)
    })
  })

  it('葉子 4 片、顏色兩種交錯，莖一種顏色', () => {
    expect(PADDY_COLORS.leaves).toEqual(['#3f9a3f', '#57b04a'])
    expect(PADDY_COLORS.stem).toBe('#4f8a3a')
  })
})
