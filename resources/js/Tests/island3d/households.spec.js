import { describe, expect, it } from 'vitest'
import {
  HOUSEHOLD_KINDS,
  HOUSEHOLD_KIND_ORDER,
  HOUSEHOLDS,
  householdObstacles,
  householdSigns,
  spotAt,
  spotKey,
} from '@/island3d/households'
import { ISLAND_DECOR, ISLAND_MAP, roadSegments, tribeHexes } from '@/island3d/islandMap'
import { closestOnSegment, hexVertices, isInsideHex } from '@/island3d/hexBounds'
import {
  LANDMARKS,
  footprintCorners,
  footprintGapToPoint,
  footprintGapToSegment,
  shapeParts,
} from '@/island3d/landmarks'
import { createWalkable } from '@/island3d/walkable'
import { createRouter } from '@/island3d/route'

const { hexRadius, roadHalfWidth } = ISLAND_MAP
const ROADS = roadSegments(ISLAND_MAP)
const SCENERY = [...ISLAND_DECOR.paddies, ...LANDMARKS].map(footprintCorners)
const TOLERANCE = 1e-6

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const edgesOf = (polygon) => polygon.map((from, i) => [from, polygon[(i + 1) % polygon.length]])
/** 兩個長方形最近的距離（重疊或交叉是 0） */
const rectGap = (a, b) =>
  Math.min(...edgesOf(b).map(([from, to]) => footprintGapToSegment(a, from, to)))
/** 佔地離線段 from–to 最近的距離；from、to 是同一點時就是離那一點的距離 */
const gapToLine = (corners, from, to) =>
  distance(from, to) === 0
    ? footprintGapToPoint(corners, from)
    : footprintGapToSegment(corners, from, to)
/** 線段 points 裡離 point 最近的那一點 */
const nearestOn = (point, segments) =>
  segments
    .map(({ from, to }) => closestOnSegment(point, from, to))
    .reduce((best, candidate) =>
      distance(candidate, point) < distance(best, point) ? candidate : best
    )
const COAST_SEGMENTS = edgesOf(ISLAND_DECOR.coast).map(([from, to]) => ({ from, to }))
/** 朝向（three.js 的 rotation.y：0 朝 +z）正面的方向 */
const forwardOf = (heading) => ({ x: Math.sin(heading), z: Math.cos(heading) })
const angleBetween = (a, b) =>
  Math.acos(Math.min(1, (a.x * b.x + a.z * b.z) / (Math.hypot(a.x, a.z) * Math.hypot(b.x, b.z))))
/** 圓整個在六角格裡：圓心在裡面，而且離每條邊至少半徑 */
const circleInHex = ({ x, z, radius }, center) =>
  isInsideHex({ x, z }, hexRadius, center) &&
  edgesOf(hexVertices(hexRadius, center)).every(
    ([from, to]) => distance({ x, z }, closestOnSegment({ x, z }, from, to)) >= radius - TOLERANCE
  )

const hexCenterOf = ({ tribe, hex }) =>
  tribeHexes(ISLAND_MAP.tribes.find((candidate) => candidate.code === tribe))[hex]
const cornersOf = (item) => footprintCorners(item)

describe('island3d/households 每個格子一戶人家（#201 驗收條件 1、2）', () => {
  it('18 個格子各一戶，順序和部落、格子的順序一樣', () => {
    const expected = ISLAND_MAP.tribes.flatMap((tribe) =>
      tribeHexes(tribe).map((_, hex) => `${tribe.code}#${hex}`)
    )

    expect(HOUSEHOLDS.map(({ tribe, hex }) => `${tribe}#${hex}`)).toEqual(expected)
    expect(HOUSEHOLDS).toHaveLength(18)
  })

  it('每戶四件依序是 vahay、pananadengan、zazawan、tagakal，大小照種類', () => {
    expect(HOUSEHOLD_KIND_ORDER).toEqual(['vahay', 'pananadengan', 'zazawan', 'tagakal'])
    expect(HOUSEHOLD_KINDS.vahay).toMatchObject({ width: 7, depth: 5 })
    expect(HOUSEHOLD_KINDS.pananadengan).toMatchObject({ width: 3.4, depth: 2.4 })
    expect(HOUSEHOLD_KINDS.zazawan).toMatchObject({ width: 4, depth: 3 })
    expect(HOUSEHOLD_KINDS.tagakal).toMatchObject({ width: 3, depth: 3 })
    HOUSEHOLDS.forEach(({ items }) => {
      expect(items.map((item) => item.kind)).toEqual(HOUSEHOLD_KIND_ORDER)
      items.forEach((item) => {
        const { width, depth } = HOUSEHOLD_KINDS[item.kind]
        expect(item).toMatchObject({ width, depth })
      })
    })
  })

  it('同一戶四件朝同一個方向，和「格子中心 → 最近的海岸點」相差不超過 45 度', () => {
    HOUSEHOLDS.forEach((household) => {
      const center = hexCenterOf(household)
      const coast = nearestOn(center, COAST_SEGMENTS)
      const toSea = { x: coast.x - center.x, z: coast.z - center.z }

      household.items.forEach((item) => expect(item.heading).toBe(household.heading))
      expect(angleBetween(forwardOf(household.heading), toSea)).toBeLessThanOrEqual(
        Math.PI / 4 + TOLERANCE
      )
    })
  })

  it('每一件整個在自己的格子裡、離格子中心至少 2.5', () => {
    HOUSEHOLDS.forEach((household) => {
      const center = hexCenterOf(household)
      household.items.forEach((item) => {
        cornersOf(item).forEach((corner) =>
          expect(isInsideHex(corner, hexRadius, center)).toBe(true)
        )
        expect(footprintGapToPoint(cornersOf(item), center)).toBeGreaterThanOrEqual(2.5 - TOLERANCE)
      })
    })
  })

  it('每一件離公路中線至少「路寬的一半 ＋ 0.5」', () => {
    HOUSEHOLDS.flatMap(({ items }) => items).forEach((item) => {
      ROADS.forEach(({ from, to }) =>
        expect(footprintGapToSegment(cornersOf(item), from, to)).toBeGreaterThanOrEqual(
          roadHalfWidth + 0.5 - TOLERANCE
        )
      )
    })
  })

  it('同一戶的件與件至少隔 1.0；和水田、特色建築至少隔 1.0', () => {
    HOUSEHOLDS.forEach(({ items }) => {
      items.forEach((item, i) => {
        items
          .slice(i + 1)
          .forEach((other) =>
            expect(rectGap(cornersOf(item), cornersOf(other))).toBeGreaterThanOrEqual(1 - TOLERANCE)
          )
        SCENERY.forEach((scenery) =>
          expect(rectGap(cornersOf(item), scenery)).toBeGreaterThanOrEqual(1 - TOLERANCE)
        )
      })
    })
  })

  it('格子中心到公路最近點、到每個範圍中心的直線，離每一件至少 0.5', () => {
    HOUSEHOLDS.forEach((household) => {
      const center = hexCenterOf(household)
      const targets = [nearestOn(center, ROADS), ...household.items.map((item) => item.zone)]
      household.items.forEach((item) =>
        targets.forEach((target) =>
          expect(gapToLine(cornersOf(item), center, target)).toBeGreaterThanOrEqual(0.5 - TOLERANCE)
        )
      )
    })
  })

  it('vahay 最靠山：沿著朝海方向量，其他三件都比 vahay 前面至少 2', () => {
    HOUSEHOLDS.forEach(({ heading, items }) => {
      const forward = forwardOf(heading)
      const [vahay, ...others] = items
      others.forEach((item) =>
        expect(
          (item.x - vahay.x) * forward.x + (item.z - vahay.z) * forward.z
        ).toBeGreaterThanOrEqual(2 - TOLERANCE)
      )
    })
  })
})

describe('island3d/households 每一件的範圍（#201 驗收條件 4）', () => {
  it('半徑照種類：vahay 2.2、pananadengan 2.0、zazawan 2.6、tagakal 2.2', () => {
    const radii = Object.fromEntries(
      HOUSEHOLD_KIND_ORDER.map((kind) => [kind, HOUSEHOLD_KINDS[kind].zoneRadius])
    )

    expect(radii).toEqual({ vahay: 2.2, pananadengan: 2, zazawan: 2.6, tagakal: 2.2 })
    HOUSEHOLDS.flatMap(({ items }) => items).forEach((item) =>
      expect(item.zone.radius).toBe(HOUSEHOLD_KINDS[item.kind].zoneRadius)
    )
  })

  it('範圍整個在格子裡、彼此不重疊', () => {
    HOUSEHOLDS.forEach((household) => {
      const zones = household.items.map((item) => item.zone)
      zones.forEach((zone, i) => {
        expect(circleInHex(zone, hexCenterOf(household))).toBe(true)
        zones
          .slice(i + 1)
          .forEach((other) =>
            expect(distance(zone, other)).toBeGreaterThanOrEqual(
              zone.radius + other.radius - TOLERANCE
            )
          )
      })
    })
  })

  it('範圍不蓋到任何一件、水田、特色建築', () => {
    HOUSEHOLDS.forEach(({ items }) => {
      const blockers = [...items.map(cornersOf), ...SCENERY]
      items.forEach(({ zone }) =>
        blockers.forEach((corners) =>
          expect(footprintGapToPoint(corners, zone)).toBeGreaterThanOrEqual(zone.radius - TOLERANCE)
        )
      )
    })
  })

  it('vahay 的範圍在屋子正面前方', () => {
    HOUSEHOLDS.forEach(({ heading, items: [vahay] }) => {
      const forward = forwardOf(heading)
      const ahead = (vahay.zone.x - vahay.x) * forward.x + (vahay.zone.z - vahay.z) * forward.z

      expect(ahead).toBeGreaterThan(vahay.depth / 2)
    })
  })
})

describe('island3d/households 四件擋路，但每個範圍都走得到（#201 驗收條件 3）', () => {
  const walkable = createWalkable(ISLAND_MAP, householdObstacles())
  const findRoute = createRouter(ISLAND_MAP, walkable)

  it('四件的佔地都是擋路的東西', () => {
    const obstacles = householdObstacles()

    expect(obstacles).toHaveLength(72)
    HOUSEHOLDS.flatMap(({ items }) => items).forEach((item) =>
      expect(walkable.isWalkable({ x: item.x, z: item.z })).toBe(false)
    )
  })

  it('從每個部落的主格中心，找得到路走到全部 72 個範圍的中心', () => {
    ISLAND_MAP.tribes.forEach(({ center }) => {
      HOUSEHOLDS.flatMap(({ items }) => items).forEach(({ zone }) =>
        expect(findRoute(center, { x: zone.x, z: zone.z })).not.toBeNull()
      )
    })
  })
})

describe('island3d/households 角色在哪一件的範圍裡（#201 驗收條件 9）', () => {
  it('站在範圍中心或邊上：回傳部落、第幾格、第幾件；範圍外是 null', () => {
    const household = HOUSEHOLDS[4]
    const { zone } = household.items[2]

    expect(spotAt({ x: zone.x, z: zone.z })).toEqual({
      tribe: household.tribe,
      hex: household.hex,
      slot: 2,
    })
    expect(spotAt({ x: zone.x + zone.radius, z: zone.z })).toEqual({
      tribe: household.tribe,
      hex: household.hex,
      slot: 2,
    })
    expect(spotAt({ x: zone.x + zone.radius + 0.01, z: zone.z })).toBeNull()
    expect(spotAt(hexCenterOf(household))).toBeNull()
  })
})

describe('island3d/households 四件上方的名稱招牌（#201 驗收條件 15）', () => {
  it('72 塊招牌：寫族語名稱，在那一件中心的正上方、比造型最高點高', () => {
    const signs = householdSigns()

    expect(signs).toHaveLength(72)
    HOUSEHOLDS.flatMap(({ items }) => items).forEach((item, i) => {
      const top = Math.max(
        ...shapeParts(item.kind, item.width, item.depth).map((part) => part.y + part.height / 2)
      )
      expect(signs[i]).toMatchObject({ text: item.kind, x: item.x, z: item.z })
      expect(signs[i].height).toBeGreaterThan(top)
    })
  })
})

describe('island3d/households 範圍的代號（#201 驗收條件 11）', () => {
  it('同一戶同一件的代號一樣，不同的就不一樣；沒有範圍是 null', () => {
    expect(spotKey({ tribe: 'ivalino', hex: 2, slot: 1 })).toBe(
      spotKey({ tribe: 'ivalino', hex: 2, slot: 1 })
    )
    expect(spotKey({ tribe: 'ivalino', hex: 2, slot: 1 })).not.toBe(
      spotKey({ tribe: 'ivalino', hex: 1, slot: 1 })
    )
    expect(spotKey(null)).toBeNull()
  })
})
