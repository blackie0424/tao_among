import { describe, expect, it } from 'vitest'
import { clampToHex, closestOnSegment, hexVertices } from '@/island3d/hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, WALK_SPEED, mountainColor } from '@/island3d/islandMap'
import {
  footprintCorners,
  footprintGapToPoint,
  footprintGapToSegment,
  footprintInside,
} from '@/island3d/landmarks'
import { createRouter } from '@/island3d/route'
import { createWalkable } from '@/island3d/walkable'

const center = (code) => ISLAND_MAP.tribes.find((tribe) => tribe.code === code).center
const samePoint = (a, b) => a.x === b.x && a.z === b.z
const tribeOn = (point) =>
  ISLAND_MAP.tribes.find((tribe) => samePoint(tribe.center, point))?.code ?? null
const length = (points) =>
  points
    .slice(1)
    .reduce((sum, to, i) => sum + Math.hypot(to.x - points[i].x, to.z - points[i].z), 0)

const [ringRoad, crossRoad] = ISLAND_MAP.roads
const walkable = createWalkable(ISLAND_MAP)

// 方向：x 越大越東、z 越大越南
describe('island3d/islandMap 魚島的部落與公路（#200 驗收條件 1、5）', () => {
  it('有 6 個部落，代碼和 config/fish_options.php 的部落一致', () => {
    expect(ISLAND_MAP.tribes.map((tribe) => tribe.code).sort()).toEqual(
      ['imowrod', 'iranmeilek', 'iraraley', 'iratay', 'ivalino', 'yayo'].sort()
    )
  })

  it('朗島在最北', () => {
    const others = ['iranmeilek', 'ivalino', 'imowrod', 'iratay', 'yayo']

    others.forEach((code) => expect(center('iraraley').z).toBeLessThan(center(code).z))
  })

  it('東清、野銀在東半邊，野銀比東清南', () => {
    expect(center('iranmeilek').x).toBeGreaterThan(0)
    expect(center('ivalino').x).toBeGreaterThan(0)
    expect(center('ivalino').z).toBeGreaterThan(center('iranmeilek').z)
  })

  it('紅頭、漁人、椰油在西半邊，由南到北是紅頭、漁人、椰油', () => {
    ;['imowrod', 'iratay', 'yayo'].forEach((code) => expect(center(code).x).toBeLessThan(0))
    expect(center('imowrod').z).toBeGreaterThan(center('iratay').z)
    expect(center('iratay').z).toBeGreaterThan(center('yayo').z)
  })

  it('部落格子彼此不重疊', () => {
    ISLAND_MAP.tribes.forEach((a, i) => {
      ISLAND_MAP.tribes.slice(i + 1).forEach((b) => {
        const apart = Math.hypot(a.center.x - b.center.x, a.center.z - b.center.z)
        expect(apart).toBeGreaterThan(ISLAND_MAP.hexRadius * 2)
      })
    })
  })

  it('環島公路是一圈，依序經過朗島、東清、野銀、紅頭、漁人、椰油', () => {
    expect(ringRoad.closed).toBe(true)
    expect(ringRoad.points.map(tribeOn).filter(Boolean)).toEqual([
      'iraraley',
      'iranmeilek',
      'ivalino',
      'imowrod',
      'iratay',
      'yayo',
    ])
  })

  it('野銀到紅頭之間繞過島的南端：中間的折點都比兩個部落南', () => {
    const codes = ringRoad.points.map(tribeOn)
    const between = ringRoad.points.slice(codes.indexOf('ivalino') + 1, codes.indexOf('imowrod'))

    expect(between.length).toBeGreaterThan(0)
    between.forEach((point) => {
      expect(point.z).toBeGreaterThan(center('ivalino').z)
      expect(point.z).toBeGreaterThan(center('imowrod').z)
    })
  })

  it('只有兩條公路；橫貫公路不是一圈，不經過任何部落中心', () => {
    expect(ISLAND_MAP.roads).toHaveLength(2)
    expect(crossRoad.closed).toBe(false)
    expect(crossRoad.points.map(tribeOn).filter(Boolean)).toEqual([])
  })

  it('部落之間沒有捷徑：部落中心兩兩之間，只有環島公路上相鄰的五對可以直走', () => {
    const straight = []
    ISLAND_MAP.tribes.forEach((a, i) => {
      ISLAND_MAP.tribes.slice(i + 1).forEach((b) => {
        if (walkable.isStraightWalkable(a.center, b.center)) {
          straight.push([a.code, b.code].sort().join('–'))
        }
      })
    })

    expect(straight.sort()).toEqual(
      [
        'iranmeilek–iraraley',
        'iranmeilek–ivalino',
        'imowrod–iratay',
        'iratay–yayo',
        'iraraley–yayo',
      ].sort()
    )
  })

  it('走路速度是 #199（每秒 2.5）的 2 倍', () => {
    expect(WALK_SPEED).toBe(5)
  })

  it('沿環島公路走一圈要 80～100 秒', () => {
    const seconds = length([...ringRoad.points, ringRoad.points[0]]) / 5

    expect(seconds).toBeGreaterThanOrEqual(80)
    expect(seconds).toBeLessThanOrEqual(100)
  })

  it('環島公路上相鄰兩個部落（中心到中心）要 7～13 秒，繞南端的野銀—紅頭除外', () => {
    const pairs = [
      ['iraraley', 'iranmeilek'],
      ['iranmeilek', 'ivalino'],
      ['imowrod', 'iratay'],
      ['iratay', 'yayo'],
      ['yayo', 'iraraley'],
    ]

    pairs.forEach(([from, to]) => {
      const seconds = length([center(from), center(to)]) / 5
      expect(seconds).toBeGreaterThanOrEqual(7)
      expect(seconds).toBeLessThanOrEqual(13)
    })
  })

  it('從紅頭點野銀：走橫貫公路，不繞環島、不經過別的部落', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('imowrod'), center('ivalino'))
    const viaRing = ringRoad.points.slice(
      ringRoad.points.map(tribeOn).indexOf('ivalino'),
      ringRoad.points.map(tribeOn).indexOf('imowrod') + 1
    )

    expect(route.at(-1)).toEqual(center('ivalino'))
    expect(route.slice(0, -1).map(tribeOn).filter(Boolean)).toEqual([])
    expect(length([center('imowrod'), ...route])).toBeLessThan(length(viaRing))
  })

  it('從紅頭中心點野銀中心：剛好經過西端接點、橫貫公路的折點、東端接點（#207 驗收條件 2）', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('imowrod'), center('ivalino'))

    // 手寫的預期路線：西端接點 → 兩個折點 → 東端接點 → 野銀中心
    const expected = [
      [-32, 60],
      [-10, 52],
      [22, 10],
      [47.2, 8.6],
      [46, 26],
    ]
    expect(route).toHaveLength(expected.length)
    route.forEach((point, i) => {
      expect(point.x).toBeCloseTo(expected[i][0], 9)
      expect(point.z).toBeCloseTo(expected[i][1], 9)
    })
  })

  it('從朗島點紅頭：沿環島公路往西走（經過椰油、漁人），比往東近', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('iraraley'), center('imowrod'))

    expect(route.map(tribeOn)).toEqual(['yayo', 'iratay', 'imowrod'])
  })

  it('朗島格子與環島公路交界的凹角：相距 0.2 的兩點不能直走（#207 格子放大後重找的座標）', () => {
    const inHex = { x: 7.229289321881275, z: -61.7692893218818 }
    const onRoad = { x: 7.370710678118584, z: -61.910710678119116 }

    expect(walkable.tribeAt(inHex)).toBe('iraraley')
    expect(walkable.tribeAt(onRoad)).toBeNull()
    expect(walkable.isWalkable(onRoad)).toBe(true)
    expect(walkable.isWalkable({ x: 7.3, z: -61.84 })).toBe(false)
    expect(walkable.isStraightWalkable(inHex, onRoad)).toBe(false)
  })
})

const distanceBetween = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
/** 兩條線段有沒有相交（端點碰到也算） */
const segmentsCross = (a, b, c, d) => {
  const side = (p, q, r) => Math.sign((q.x - p.x) * (r.z - p.z) - (q.z - p.z) * (r.x - p.x))
  return side(a, b, c) !== side(a, b, d) && side(c, d, a) !== side(c, d, b)
}
/** 兩條線段最近的距離 */
const segmentGap = (a, b, c, d) =>
  segmentsCross(a, b, c, d)
    ? 0
    : Math.min(
        distanceBetween(a, closestOnSegment(a, c, d)),
        distanceBetween(b, closestOnSegment(b, c, d)),
        distanceBetween(c, closestOnSegment(c, a, b)),
        distanceBetween(d, closestOnSegment(d, a, b))
      )
const edgesOf = (polygon) => polygon.map((from, i) => [from, polygon[(i + 1) % polygon.length]])

describe('island3d/islandMap 格子放大與橫貫公路的接點（#207 驗收條件 1、2）', () => {
  it('部落格子的半徑是 #200（6）的 2 倍', () => {
    expect(ISLAND_MAP.hexRadius).toBe(12)
  })

  it('6 個部落的中心和 #200 一樣（手寫的座標）', () => {
    expect(
      Object.fromEntries(ISLAND_MAP.tribes.map((t) => [t.code, [t.center.x, t.center.z]]))
    ).toEqual({
      iraraley: [0, -70],
      iranmeilek: [50, -32],
      ivalino: [46, 26],
      imowrod: [-34, 44],
      iratay: [-54, 0],
      yayo: [-50, -46],
    })
  })

  const junctions = [
    // [接點, 所屬部落, 環島公路上那一段的另一端]
    { name: '西端', point: crossRoad.points[0], tribe: 'imowrod', toward: { x: -30, z: 76 } },
    {
      name: '東端',
      point: crossRoad.points.at(-1),
      tribe: 'ivalino',
      toward: center('iranmeilek'),
    },
  ]

  junctions.forEach(({ name, point, tribe, toward }) => {
    it(`${name}接點在環島公路的中線上：紅頭往島南端、野銀往東清的那一段`, () => {
      const onRoad = closestOnSegment(point, center(tribe), toward)

      expect(distanceBetween(point, onRoad)).toBeLessThan(1e-9)
      expect(ringRoad.points.some((p) => samePoint(p, toward))).toBe(true)
    })

    it(`${name}接點在部落格子外，離格子邊 4～10 單位`, () => {
      const edge = clampToHex(point, ISLAND_MAP.hexRadius, center(tribe))
      const fromEdge = distanceBetween(point, edge)

      expect(walkable.tribeAt(point)).toBeNull()
      expect(fromEdge).toBeGreaterThanOrEqual(4)
      expect(fromEdge).toBeLessThanOrEqual(10)
    })
  })

  it('橫貫公路的路面不碰任何部落格子：每一段離每個格子都超過公路半寬', () => {
    const segments = crossRoad.points.slice(1).map((to, i) => [crossRoad.points[i], to])

    ISLAND_MAP.tribes.forEach((tribe) => {
      const hexEdges = edgesOf(hexVertices(ISLAND_MAP.hexRadius, tribe.center))
      segments.forEach(([from, to]) => {
        const gap = Math.min(...hexEdges.map(([a, b]) => segmentGap(from, to, a, b)))
        expect(gap).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      })
    })
  })
})

describe('island3d/islandMap 山、海岸與船（#200 驗收條件 10）', () => {
  const awayFromWalkable = (point) => {
    const nearest = walkable.clampToWalkable(point)
    return Math.hypot(nearest.x - point.x, nearest.z - point.z)
  }
  /** 點是否在多邊形裡（射線法） */
  const isInside = (point, polygon) =>
    polygon.reduce((inside, a, i) => {
      const b = polygon[(i + 1) % polygon.length]
      const crosses =
        a.z > point.z !== b.z > point.z &&
        point.x < ((b.x - a.x) * (point.z - a.z)) / (b.z - a.z) + a.x
      return crosses ? !inside : inside
    }, false)

  it('山不壓到部落格子與公路：每座山的山腳離可走範圍至少 2', () => {
    expect(ISLAND_DECOR.mountains.length).toBeGreaterThan(0)
    ISLAND_DECOR.mountains.forEach((mountain) => {
      expect(awayFromWalkable(mountain)).toBeGreaterThanOrEqual(mountain.radius + 2)
    })
  })

  it('山在島上：每座山的中心都在海岸線裡面', () => {
    ISLAND_DECOR.mountains.forEach((mountain) => {
      expect(isInside(mountain, ISLAND_DECOR.coast)).toBe(true)
    })
  })

  it('有山在島的中間（原點附近），橫貫公路從山區穿過', () => {
    expect(ISLAND_DECOR.mountains.some((m) => Math.hypot(m.x, m.z) < 30)).toBe(true)
    // 橫貫公路的折點南北兩側都有山
    expect(ISLAND_DECOR.mountains.some((m) => m.z < 30)).toBe(true)
    expect(ISLAND_DECOR.mountains.some((m) => m.z > 30)).toBe(true)
  })

  it('部落格子與公路都在海岸線裡面（不會畫在海上）', () => {
    const stops = [
      ...ISLAND_MAP.tribes.map((tribe) => tribe.center),
      ...ISLAND_MAP.roads.flatMap((road) => road.points),
    ]
    const r = ISLAND_MAP.hexRadius

    stops.forEach(({ x, z }) => {
      ;[
        { x: x + r, z },
        { x: x - r, z },
        { x, z: z + r },
        { x, z: z - r },
      ].forEach((point) => expect(isInside(point, ISLAND_DECOR.coast)).toBe(true))
    })
  })

  // ── #207 ──
  const coastGap = (point) =>
    Math.min(
      ...edgesOf(ISLAND_DECOR.coast).map(([a, b]) =>
        distanceBetween(point, closestOnSegment(point, a, b))
      )
    )
  const widthAt = (z) => {
    const xs = edgesOf(ISLAND_DECOR.coast)
      .filter(([a, b]) => a.z > z !== b.z > z)
      .map(([a, b]) => a.x + ((b.x - a.x) * (z - a.z)) / (b.z - a.z))
    return Math.max(...xs) - Math.min(...xs)
  }

  it('海岸線北邊寬、往南收窄（#207 驗收條件 3）', () => {
    expect(widthAt(-40)).toBeGreaterThan(widthAt(40))
    expect(widthAt(40)).toBeGreaterThan(widthAt(90))
  })

  it('沙灘有固定的寬度', () => {
    expect(ISLAND_DECOR.beachWidth).toBeGreaterThan(0)
  })

  it('船整艘在海上：離新海岸線至少 6（#207 驗收條件 3）', () => {
    ISLAND_DECOR.boats.forEach((boat) => {
      expect(isInside(boat, ISLAND_DECOR.coast)).toBe(false)
      expect(coastGap(boat)).toBeGreaterThanOrEqual(6)
    })
  })

  it('至少 8 座山、至少 4 種高度（#207 驗收條件 4）', () => {
    expect(ISLAND_DECOR.mountains.length).toBeGreaterThanOrEqual(8)
    expect(new Set(ISLAND_DECOR.mountains.map((m) => m.height)).size).toBeGreaterThanOrEqual(4)
  })

  it('最高的山在島的北半部中間（對應紅頭山）', () => {
    const highest = ISLAND_DECOR.mountains.reduce((a, b) => (b.height > a.height ? b : a))

    expect(highest.z).toBeLessThan(0)
    expect(Math.abs(highest.x)).toBeLessThan(30)
  })

  it('山的顏色：越高越深、同高同色', () => {
    // 亮度用 RGB 加總比較（顏色是 #rrggbb）
    const brightness = (hex) =>
      [1, 3, 5].reduce((sum, i) => sum + parseInt(hex.slice(i, i + 2), 16), 0)

    expect(mountainColor(10)).toMatch(/^#[0-9a-f]{6}$/)
    expect(mountainColor(15)).toBe(mountainColor(15))
    expect(brightness(mountainColor(22))).toBeLessThan(brightness(mountainColor(16)))
    expect(brightness(mountainColor(16))).toBeLessThan(brightness(mountainColor(10)))
    const byHeight = [...ISLAND_DECOR.mountains].sort((a, b) => a.height - b.height)
    byHeight.slice(1).forEach((m, i) => {
      expect(brightness(mountainColor(m.height))).toBeLessThanOrEqual(
        brightness(mountainColor(byHeight[i].height))
      )
    })
  })

  it('山不在沙灘上：中心離海岸線超過沙灘寬度', () => {
    ISLAND_DECOR.mountains.forEach((mountain) => {
      expect(coastGap(mountain)).toBeGreaterThan(ISLAND_DECOR.beachWidth)
    })
  })

  describe('水田（#207 驗收條件 5）', () => {
    const paddies = ISLAND_DECOR.paddies
    const beside = paddies.filter((paddy) => paddy.tribe)
    const alongRoad = paddies.filter((paddy) => !paddy.tribe)
    const towardCenter = (from, to) => Math.hypot(to.x, to.z) < Math.hypot(from.x, from.z)
    const ringSegments = [...ringRoad.points, ringRoad.points[0]]
      .slice(1)
      .map((to, i) => [ringRoad.points[i], to])
    const nearestOnRing = (point) =>
      ringSegments
        .map(([a, b]) => closestOnSegment(point, a, b))
        .reduce((best, p) => (distanceBetween(p, point) < distanceBetween(best, point) ? p : best))

    it('每個部落旁各 1 塊，另外公路旁 6～10 塊', () => {
      expect(beside.map((paddy) => paddy.tribe).sort()).toEqual(
        ['imowrod', 'iranmeilek', 'iraraley', 'iratay', 'ivalino', 'yayo'].sort()
      )
      expect(alongRoad.length).toBeGreaterThanOrEqual(6)
      expect(alongRoad.length).toBeLessThanOrEqual(10)
    })

    it('部落旁的在格子外、靠島中心的那一側、離格子邊不超過 8', () => {
      beside.forEach((paddy) => {
        const tribeCenter = center(paddy.tribe)
        const edge = clampToHex(paddy, ISLAND_MAP.hexRadius, tribeCenter)

        expect(towardCenter(tribeCenter, paddy)).toBe(true)
        expect(distanceBetween(paddy, edge)).toBeLessThanOrEqual(8)
      })
    })

    it('公路旁的在環島公路靠島中心的那一側、離路邊不超過 8', () => {
      alongRoad.forEach((paddy) => {
        const onRoad = nearestOnRing(paddy)

        expect(towardCenter(onRoad, paddy)).toBe(true)
        expect(distanceBetween(paddy, onRoad) - ISLAND_MAP.roadHalfWidth).toBeLessThanOrEqual(8)
      })
    })

    it('整塊都不壓到可走範圍：不碰格子、離每段公路超過半寬', () => {
      const roadSegmentsAll = ISLAND_MAP.roads.flatMap(({ points, closed }) => {
        const ends = closed ? [...points, points[0]] : points
        return ends.slice(1).map((to, i) => [ends[i], to])
      })
      paddies.forEach((paddy) => {
        const corners = footprintCorners(paddy)

        corners.forEach((corner) => expect(walkable.isWalkable(corner)).toBe(false))
        roadSegmentsAll.forEach(([a, b]) => {
          expect(footprintGapToSegment(corners, a, b)).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
        })
        ISLAND_MAP.tribes.forEach((tribe) => {
          edgesOf(hexVertices(ISLAND_MAP.hexRadius, tribe.center)).forEach(([a, b]) => {
            expect(footprintGapToSegment(corners, a, b)).toBeGreaterThan(0)
          })
        })
      })
    })

    it('整塊都在海岸線裡面、不在沙灘上、離每座山的山腳至少 2', () => {
      paddies.forEach((paddy) => {
        const corners = footprintCorners(paddy)

        expect(footprintInside(corners, ISLAND_DECOR.coast)).toBe(true)
        edgesOf(ISLAND_DECOR.coast).forEach(([a, b]) => {
          expect(footprintGapToSegment(corners, a, b)).toBeGreaterThan(ISLAND_DECOR.beachWidth)
        })
        ISLAND_DECOR.mountains.forEach((mountain) => {
          expect(footprintGapToPoint(corners, mountain)).toBeGreaterThanOrEqual(mountain.radius + 2)
        })
      })
    })

    it('水田彼此不重疊', () => {
      paddies.forEach((a, i) => {
        paddies.slice(i + 1).forEach((b) => {
          expect(distanceBetween(a, b)).toBeGreaterThan(
            Math.hypot(a.width, a.depth) / 2 + Math.hypot(b.width, b.depth) / 2
          )
        })
      })
    })
  })

  it('船在海上：最多 3 艘，都在海岸線外面，離岸不超過看得到的範圍', () => {
    expect(ISLAND_DECOR.boats.length).toBeGreaterThan(0)
    expect(ISLAND_DECOR.boats.length).toBeLessThanOrEqual(3)
    ISLAND_DECOR.boats.forEach((boat) => {
      expect(isInside(boat, ISLAND_DECOR.coast)).toBe(false)
      expect(awayFromWalkable(boat)).toBeGreaterThan(10)
      expect(Math.hypot(boat.x, boat.z)).toBeLessThan(150)
    })
  })
})
