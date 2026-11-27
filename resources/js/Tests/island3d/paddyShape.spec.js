import { describe, expect, it } from 'vitest'
import { PADDY_COLORS, PADDY_LAYOUTS, paddyShape, paddySize } from '@/island3d/paddyShape'

const EPSILON = 1e-9
// 每小塊 4 × 2.6、小塊之間留 0.6、外圍留 0.3、後面一排高 0.15（#211 驗收條件 11，規格修訂 1）
const FIELD = { width: 4, depth: 2.6 }
const GAP = 0.6
const EDGE = 0.3
const ROW_STEP = 0.15

/** 點是否在長方形 { x, z, width, depth }（中心與大小）裡 */
const inside = (point, rect) =>
  Math.abs(point.x - rect.x) <= rect.width / 2 + EPSILON &&
  Math.abs(point.z - rect.z) <= rect.depth / 2 + EPSILON

describe('island3d/paddyShape 拼法與佔地（#211 驗收條件 11，規格修訂 1）', () => {
  it('4 種拼法：2×2、3×3、1×3（三塊一串）、2×3（六塊一串），寫成 [寬的塊數, 深的塊數]', () => {
    expect(PADDY_LAYOUTS).toEqual({
      '2x2': [2, 2],
      '3x3': [3, 3],
      '1x3': [1, 3],
      '2x3': [2, 3],
    })
  })

  it('整片的佔地：小塊加田埂加外圍（手算的數字）', () => {
    expect(paddySize([2, 2]).width).toBeCloseTo(8.6 + 0.6, 9)
    expect(paddySize([2, 2]).depth).toBeCloseTo(5.8 + 0.6, 9)
    expect(paddySize([3, 3]).width).toBeCloseTo(13.2 + 0.6, 9)
    expect(paddySize([3, 3]).depth).toBeCloseTo(9.0 + 0.6, 9)
    // 1×3：寬 1 塊、深 3 排；2×3：寬 2 塊、深 3 排（審查：寬 × 深的塊數）
    expect(paddySize([1, 3]).width).toBeCloseTo(4 + 0.6, 9)
    expect(paddySize([1, 3]).depth).toBeCloseTo(9.0 + 0.6, 9)
    expect(paddySize([2, 3]).width).toBeCloseTo(8.6 + 0.6, 9)
    expect(paddySize([2, 3]).depth).toBeCloseTo(9.0 + 0.6, 9)
  })
})

it('每種拼法的小塊數與排數照寬 × 深：1×3 有 3 排、2×3 每排 2 塊共 3 排', () => {
  const rowsOf = (layout) => new Set(paddyShape(layout).fields.map((field) => field.row)).size
  const columnsOf = (layout) => new Set(paddyShape(layout).fields.map((field) => field.column)).size

  expect([columnsOf(PADDY_LAYOUTS['1x3']), rowsOf(PADDY_LAYOUTS['1x3'])]).toEqual([1, 3])
  expect([columnsOf(PADDY_LAYOUTS['2x3']), rowsOf(PADDY_LAYOUTS['2x3'])]).toEqual([2, 3])
})

describe.each(Object.entries(PADDY_LAYOUTS))(
  'island3d/paddyShape %s 的小塊田（#211 驗收條件 11）',
  (_, layout) => {
    const [columns, rows] = layout
    const shape = paddyShape(layout)
    const size = paddySize(layout)

    it('小塊數是 寬 × 深，每小塊 4 × 2.6，全部在佔地裡且離邊至少 0.3', () => {
      expect(shape.fields).toHaveLength(columns * rows)
      shape.fields.forEach((field) => {
        expect(field.width).toBeCloseTo(FIELD.width, 9)
        expect(field.depth).toBeCloseTo(FIELD.depth, 9)
        expect(Math.abs(field.x) + field.width / 2).toBeLessThanOrEqual(
          size.width / 2 - EDGE + EPSILON
        )
        expect(Math.abs(field.z) + field.depth / 2).toBeLessThanOrEqual(
          size.depth / 2 - EDGE + EPSILON
        )
      })
    })

    it('相鄰的小塊之間剛好留 0.6 的田埂，不重疊', () => {
      shape.fields.forEach((a, i) => {
        shape.fields.slice(i + 1).forEach((b) => {
          const gapX = Math.abs(a.x - b.x) - FIELD.width
          const gapZ = Math.abs(a.z - b.z) - FIELD.depth
          expect(Math.max(gapX, gapZ)).toBeGreaterThanOrEqual(GAP - EPSILON)
          if (a.row === b.row && Math.abs(a.column - b.column) === 1) {
            expect(gapX).toBeCloseTo(GAP, 9)
          }
        })
      })
    })

    it('越後面（-z）的一排越高：每往後一排高 0.15', () => {
      shape.fields.forEach((field) => {
        expect(field.level).toBeCloseTo(field.row * ROW_STEP, 9)
        const front = shape.fields.find(
          (other) => other.column === field.column && other.row === field.row - 1
        )
        if (front) {
          expect(field.z).toBeLessThan(front.z)
        }
      })
    })

    it('每小塊的水面在那一塊裡、高過那一塊的土頂', () => {
      shape.fields.forEach((field) => {
        const { water } = field
        expect(inside({ x: water.x - water.width / 2, z: water.z - water.depth / 2 }, field)).toBe(
          true
        )
        expect(inside({ x: water.x + water.width / 2, z: water.z + water.depth / 2 }, field)).toBe(
          true
        )
        expect(field.soilTop).toBeGreaterThan(field.level)
        expect(water.level).toBeGreaterThan(field.soilTop)
      })
    })

    it('每小塊靠低處（+z）有一排灰色石頭：在那一塊裡、在水面的 +z 外側、從左排到右', () => {
      shape.fields.forEach((field, i) => {
        const stones = shape.stones.filter((stone) => stone.field === i)
        expect(stones.length).toBeGreaterThanOrEqual(8)
        stones.forEach((stone) => {
          expect(PADDY_COLORS.stones).toContain(stone.color)
          expect(stone.z - stone.depth / 2).toBeGreaterThanOrEqual(
            field.water.z + field.water.depth / 2 - EPSILON
          )
          expect(stone.z + stone.depth / 2).toBeLessThanOrEqual(field.z + field.depth / 2 + EPSILON)
          expect(Math.abs(stone.x - field.x) + stone.width / 2).toBeLessThanOrEqual(
            field.width / 2 + EPSILON
          )
        })
      })
    })

    it('每小塊至少 6 株芋頭，都在那一塊的水面裡、站在水面上（驗收條件 12）', () => {
      shape.fields.forEach((field, i) => {
        const plants = shape.taro.filter((plant) => plant.field === i)
        expect(plants.length).toBeGreaterThanOrEqual(6)
        plants.forEach((plant) => {
          expect(inside(plant, field.water)).toBe(true)
          expect(plant.level).toBeCloseTo(field.water.level, 9)
        })
      })
    })
  }
)

describe('island3d/paddyShape 顏色與亂數（#211 驗收條件 11、12）', () => {
  const shape = paddyShape(PADDY_LAYOUTS['3x3'])

  it('顏色照規格：水 #6fb7c9、土 #6b5a45、石頭 #9aa0a6 與 #7d848b、葉子兩種交錯、莖一種', () => {
    expect(PADDY_COLORS.water).toBe('#6fb7c9')
    expect(PADDY_COLORS.soil).toBe('#6b5a45')
    expect([...PADDY_COLORS.stones].sort()).toEqual(['#7d848b', '#9aa0a6'])
    expect(new Set(shape.stones.map((stone) => stone.color))).toEqual(new Set(PADDY_COLORS.stones))
    expect(PADDY_COLORS.leaves).toEqual(['#3f9a3f', '#57b04a'])
    expect(PADDY_COLORS.stem).toBe('#4f8a3a')
  })

  it('每株的大小與朝向有一點不同，但每次算出來都一樣（固定的亂數種子）', () => {
    expect(paddyShape(PADDY_LAYOUTS['3x3'])).toEqual(shape)
    expect(new Set(shape.taro.map((plant) => plant.scale.toFixed(6))).size).toBeGreaterThan(1)
    shape.taro.forEach((plant) => {
      expect(plant.scale).toBeGreaterThan(0.5)
      expect(plant.scale).toBeLessThanOrEqual(1)
    })
  })
})
