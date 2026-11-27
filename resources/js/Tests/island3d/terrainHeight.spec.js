import { describe, expect, it } from 'vitest'
import { hexVertices } from '@/island3d/hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, roadSegments, tribeHexes } from '@/island3d/islandMap'
import { LANDMARKS, footprintCorners } from '@/island3d/landmarks'
import {
  COAST_WIDTH,
  FLAT_MARGIN,
  SLOPE_WIDTH,
  TERRAIN_EXAGGERATION,
  flatZones,
  heightAt,
  heightField,
  islandHeightField,
} from '@/island3d/terrainHeight'

// 1 公尺換成世界單位（1 公里＝33.75 單位），再乘上誇大倍數 1.5
const UNITS_PER_METER = 0.03375 * 1.5

/** 手建的原始高度表：從 (x0, z0) 起每 step 一點，meterAt(x, z) 決定每一點的公尺數 */
function rawGrid({ x0 = -40, z0 = -40, step = 2, size = 41, meterAt = () => 100 } = {}) {
  const meters = []
  for (let row = 0; row < size; row += 1) {
    for (let col = 0; col < size; col += 1) {
      meters.push(meterAt(x0 + col * step, z0 + row * step))
    }
  }
  return { x0, z0, step, cols: size, rows: size, meters }
}

// 很大的陸地：海邊係數在中間一帶都是 1
const BIG_LAND = [
  { x: -1000, z: -1000 },
  { x: 1000, z: -1000 },
  { x: 1000, z: 1000 },
  { x: -1000, z: 1000 },
]

const smooth = (t) => (t <= 0 ? 0 : t >= 1 ? 1 : t * t * (3 - 2 * t))

describe('island3d/terrainHeight 常數（#223 驗收條件 1、2）', () => {
  it('高度誇大 1.5 倍；平地外 3 單位內是 0、再 10 單位收坡；海邊 6 單位', () => {
    expect(TERRAIN_EXAGGERATION).toBe(1.5)
    expect(FLAT_MARGIN).toBe(3)
    expect(SLOPE_WIDTH).toBe(10)
    expect(COAST_WIDTH).toBe(6)
  })
})

describe('island3d/terrainHeight 收平地（#223 驗收條件 2）', () => {
  // 一條斜穿網格的公路：從 (-30, -25) 到 (30, 21)，半寬 2
  const diagonalRoad = { from: { x: -30, z: -25 }, to: { x: 30, z: 21 }, halfWidth: 2 }
  const roadZones = [{ segment: diagonalRoad }]
  const field = heightField(rawGrid(), { zones: roadZones, inland: BIG_LAND })

  it('斜穿網格的公路上每一點都是 0（含網格內部與路邊）', () => {
    const length = Math.hypot(60, 46)
    const along = { x: 60 / length, z: 46 / length }
    const across = { x: -along.z, z: along.x }
    for (let s = 2; s < length - 2; s += 0.37) {
      ;[-2, -1.3, 0, 0.9, 2].forEach((offset) => {
        const point = {
          x: -30 + along.x * s + across.x * offset,
          z: -25 + along.z * s + across.z * offset,
        }
        expect(heightAt(field, point)).toBe(0)
      })
    }
  })

  it('離平地 13 單位以外等於原始換算高度：100 公尺 → 100 × 0.03375 × 1.5', () => {
    // (-30, 20) 離公路中線約 34、離路邊約 32
    expect(heightAt(field, { x: -30, z: 20 })).toBeCloseTo(100 * UNITS_PER_METER, 5)
  })

  it('3～13 單位之間用平滑曲線收坡：網格點上的值照公式', () => {
    // 手建一條沿 z 軸的公路（x = 0，半寬 2），網格點 (8, 0) 離路邊 6 → (6 − 3) / 10 = 0.3
    const field2 = heightField(rawGrid(), {
      zones: [{ segment: { from: { x: 0, z: -100 }, to: { x: 0, z: 100 }, halfWidth: 2 } }],
      inland: BIG_LAND,
    })
    expect(heightAt(field2, { x: 8, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER * smooth(0.3), 5)
    // 離路邊 2（網格點 (4, 0)）：在 3 單位內，是 0
    expect(heightAt(field2, { x: 4, z: 0 })).toBe(0)
    // 離路邊 14（網格點 (16, 0)）：滿高
    expect(heightAt(field2, { x: 16, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER, 5)
  })

  it('原始高度遠處比較低時照原始的：不要求離越遠越高', () => {
    // 公路在 x = 0；原始高度在 x = 16 是 100 公尺、x = 24 是 20 公尺
    const field3 = heightField(
      rawGrid({ meterAt: (x) => (x >= 20 ? 20 : 100) }),
      {
        zones: [{ segment: { from: { x: 0, z: -100 }, to: { x: 0, z: 100 }, halfWidth: 2 } }],
        inland: BIG_LAND,
      }
    )
    expect(heightAt(field3, { x: 16, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER, 5)
    expect(heightAt(field3, { x: 24, z: 0 })).toBeCloseTo(20 * UNITS_PER_METER, 5)
  })

  it('兩個平地範圍靠在一起時看最近的那一個', () => {
    // 兩條平行公路 x = 0、x = 20（半寬 2）：網格點 (10, 0) 離兩條路邊都是 8 → (8 − 3) / 10 = 0.5
    // 網格點 (14, 0) 離右邊那條路邊 4 → 0.1
    const field4 = heightField(rawGrid(), {
      zones: [
        { segment: { from: { x: 0, z: -100 }, to: { x: 0, z: 100 }, halfWidth: 2 } },
        { segment: { from: { x: 20, z: -100 }, to: { x: 20, z: 100 }, halfWidth: 2 } },
      ],
      inland: BIG_LAND,
    })
    expect(heightAt(field4, { x: 10, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER * smooth(0.5), 5)
    expect(heightAt(field4, { x: 14, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER * smooth(0.1), 5)
  })

  it('格子（多邊形）裡、邊界上、網格內部的點都是 0', () => {
    // 一個斜放的四邊形（像轉過的佔地），頂點都不在網格點上
    const corners = [
      { x: -7.3, z: -2.1 },
      { x: 3.9, z: -9.4 },
      { x: 9.1, z: 1.7 },
      { x: -1.6, z: 8.8 },
    ]
    const field5 = heightField(rawGrid(), { zones: [{ polygon: corners }], inland: BIG_LAND })
    corners.forEach((corner, i) => {
      const next = corners[(i + 1) % corners.length]
      for (let t = 0; t <= 1; t += 0.05) {
        const onEdge = { x: corner.x + (next.x - corner.x) * t, z: corner.z + (next.z - corner.z) * t }
        expect(heightAt(field5, onEdge)).toBe(0)
      }
    })
    ;[
      { x: 0.3, z: 0.7 },
      { x: -4.1, z: 0.9 },
      { x: 5.5, z: -1.1 },
    ].forEach((inside) => expect(heightAt(field5, inside)).toBe(0))
  })

  it('海邊：離陸地邊 6 單位內收到 0，陸地外是 0', () => {
    // 陸地是 x ≤ 10 的一半（邊在 x = 10）；網格點 (6, 0) 離邊 4 → 4 / 6
    const halfLand = [
      { x: -1000, z: -1000 },
      { x: 10, z: -1000 },
      { x: 10, z: 1000 },
      { x: -1000, z: 1000 },
    ]
    const field6 = heightField(rawGrid(), { zones: [], inland: halfLand })
    expect(heightAt(field6, { x: 6, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER * smooth(4 / 6), 5)
    expect(heightAt(field6, { x: -10, z: 0 })).toBeCloseTo(100 * UNITS_PER_METER, 5)
    expect(heightAt(field6, { x: 14, z: 0 })).toBe(0)
  })

  it('原始高度是負的（海面以下）當成 0；高度表外是 0', () => {
    const field7 = heightField(rawGrid({ meterAt: () => -20 }), { zones: [], inland: BIG_LAND })
    expect(heightAt(field7, { x: 0, z: 0 })).toBe(0)
    expect(heightAt(field, { x: 500, z: 0 })).toBe(0)
  })
})

describe('island3d/terrainHeight 某點的高度＝畫出來的三角形（#223 實作決定）', () => {
  // 只有網格點 (0, 0) 是 100 公尺，其餘 0：每格沿 (行, 列) → (行＋1, 列＋1) 的對角線切成兩個三角形
  const field = heightField(rawGrid({ meterAt: (x, z) => (x === 0 && z === 0 ? 100 : 0) }), {
    zones: [],
    inland: BIG_LAND,
  })
  const peak = 100 * UNITS_PER_METER

  it('網格點上就是那一點的高度', () => {
    expect(heightAt(field, { x: 0, z: 0 })).toBeCloseTo(peak, 5)
    expect(heightAt(field, { x: 2, z: 0 })).toBe(0)
  })

  it('三角形裡照三個頂點內插（不是雙線性）', () => {
    // 格子 (0,0)–(2,2)：點 (1, 0.5) 在上三角形 (0,0)(2,0)(2,2)，(0,0) 的權重是 1 − 1/2 = 0.5
    expect(heightAt(field, { x: 1, z: 0.5 })).toBeCloseTo(peak * 0.5, 5)
    // 點 (0.5, 1) 在下三角形 (0,0)(0,2)(2,2)，(0,0) 的權重是 1 − 1/2 = 0.5
    expect(heightAt(field, { x: 0.5, z: 1 })).toBeCloseTo(peak * 0.5, 5)
    // 格子 (-2,-2)–(0,0)：點 (-1.5, -0.5) 在下三角形 (-2,-2)(-2,0)(0,0)，(0,0) 的權重是 0.5 / 2 = 0.25
    // （雙線性會是 0.25 × 0.75）
    expect(heightAt(field, { x: -1.5, z: -0.5 })).toBeCloseTo(peak * 0.25, 5)
  })
})

describe('island3d/terrainHeight 整座島的實際地形（#223 驗收條件 1、2）', () => {
  const field = islandHeightField()
  const heights = Array.from(field.heights)
  const highest = Math.max(...heights)
  const highestAt = heights.indexOf(highest)
  const top = {
    x: field.x0 + (highestAt % field.cols) * field.step,
    z: field.z0 + Math.floor(highestAt / field.cols) * field.step,
  }

  it('最高點在紅頭山附近：離 OpenStreetMap 紅頭山頂換算的 (-53.8, -30.0) 不超過 10 單位', () => {
    expect(Math.hypot(top.x + 53.8, top.z + 30.0)).toBeLessThanOrEqual(10)
  })

  it('最高點約 27 單位（實際約 535 公尺 × 0.03375 × 1.5）', () => {
    expect(highest).toBeGreaterThanOrEqual(26)
    expect(highest).toBeLessThanOrEqual(28)
  })

  it('高度表蓋住整條海岸線', () => {
    const right = field.x0 + (field.cols - 1) * field.step
    const bottom = field.z0 + (field.rows - 1) * field.step
    ISLAND_DECOR.coast.forEach(({ x, z }) => {
      expect(x).toBeGreaterThan(field.x0)
      expect(x).toBeLessThan(right)
      expect(z).toBeGreaterThan(field.z0)
      expect(z).toBeLessThan(bottom)
    })
  })

  it('18 格的中心、頂點與邊上的點都是 0', () => {
    ISLAND_MAP.tribes.flatMap(tribeHexes).forEach((center) => {
      expect(heightAt(field, center)).toBe(0)
      hexVertices(ISLAND_MAP.hexRadius, center).forEach((corner) => expect(heightAt(field, corner)).toBe(0))
      for (let angle = 0; angle < Math.PI * 2; angle += Math.PI / 12) {
        const r = ISLAND_MAP.hexRadius * Math.cos(Math.PI / 6)
        expect(heightAt(field, { x: center.x + Math.cos(angle) * r, z: center.z + Math.sin(angle) * r })).toBe(0)
      }
    })
  })

  it('兩條公路（含半寬）每隔 1 單位都是 0', () => {
    roadSegments(ISLAND_MAP).forEach(({ from, to }) => {
      const length = Math.hypot(to.x - from.x, to.z - from.z)
      const across = { x: -(to.z - from.z) / length, z: (to.x - from.x) / length }
      for (let s = 0; s <= length; s += 1) {
        ;[-ISLAND_MAP.roadHalfWidth, 0, ISLAND_MAP.roadHalfWidth].forEach((offset) => {
          const point = {
            x: from.x + ((to.x - from.x) * s) / length + across.x * offset,
            z: from.z + ((to.z - from.z) * s) / length + across.z * offset,
          }
          expect(heightAt(field, point)).toBe(0)
        })
      }
    })
  })

  it('14 處水田與 6 個特色建築的佔地（四個角與中心）都是 0', () => {
    ;[...ISLAND_DECOR.paddies, ...LANDMARKS].forEach((item) => {
      ;[item, ...footprintCorners(item)].forEach((point) => expect(heightAt(field, point)).toBe(0))
    })
  })

  it('平地範圍：18 格、每段公路、14 處水田、6 個特色建築', () => {
    const zones = flatZones()
    expect(zones.filter((zone) => zone.polygon)).toHaveLength(18 + 14 + LANDMARKS.length)
    expect(zones.filter((zone) => zone.segment)).toHaveLength(roadSegments(ISLAND_MAP).length)
  })
})
