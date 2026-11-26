import { describe, expect, it } from 'vitest'
import { clampToHex, closestOnSegment, hexVertices } from '@/island3d/hexBounds'
import {
  ISLAND_DECOR,
  ISLAND_MAP,
  WALK_SPEED,
  insetOutline,
  mountainColor,
} from '@/island3d/islandMap'
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

  it('椰油最西；紅頭與漁人在南岸緊鄰，漁人在紅頭的西邊（實際位置，#207 規格修訂 1）', () => {
    ;['iraraley', 'iranmeilek', 'ivalino', 'imowrod', 'iratay'].forEach((code) =>
      expect(center('yayo').x).toBeLessThan(center(code).x)
    )
    ;['iraraley', 'iranmeilek', 'ivalino', 'yayo'].forEach((code) => {
      expect(center('imowrod').z).toBeGreaterThan(center(code).z)
      expect(center('iratay').z).toBeGreaterThan(center(code).z)
    })
    expect(center('iratay').x).toBeLessThan(center('imowrod').x)
    expect(
      Math.hypot(center('iratay').x - center('imowrod').x, center('iratay').z - center('imowrod').z)
    ).toBeLessThan(40)
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

  it('野銀到紅頭之間繞過島的東南端：中間有折點比兩個部落都更東也更南', () => {
    const codes = ringRoad.points.map(tribeOn)
    const between = ringRoad.points.slice(codes.indexOf('ivalino') + 1, codes.indexOf('imowrod'))
    const farther = between.filter((point) =>
      ['ivalino', 'imowrod'].every((code) => point.x > center(code).x && point.z > center(code).z)
    )

    expect(farther.length).toBeGreaterThan(0)
  })

  it('只有兩條公路；橫貫公路不是一圈，不經過任何部落中心', () => {
    expect(ISLAND_MAP.roads).toHaveLength(2)
    expect(crossRoad.closed).toBe(false)
    expect(crossRoad.points.map(tribeOn).filter(Boolean)).toEqual([])
  })

  it('部落之間沒有捷徑：格子彼此不相連；部落中心之間能直走的，一定是環島公路上相鄰的一對', () => {
    const adjacent = [
      'iranmeilek–iraraley',
      'iranmeilek–ivalino',
      'imowrod–iratay',
      'iratay–yayo',
      'iraraley–yayo',
    ]
    ISLAND_MAP.tribes.forEach((a, i) => {
      ISLAND_MAP.tribes.slice(i + 1).forEach((b) => {
        const hexA = hexVertices(ISLAND_MAP.hexRadius, a.center)
        const hexB = hexVertices(ISLAND_MAP.hexRadius, b.center)
        const gap = Math.min(
          ...edgesOf(hexA).flatMap(([p, q]) =>
            edgesOf(hexB).map(([r, t]) => segmentGap(p, q, r, t))
          )
        )
        expect(gap).toBeGreaterThan(0)
        if (walkable.isStraightWalkable(a.center, b.center)) {
          expect(adjacent).toContain([a.code, b.code].sort().join('–'))
        }
      })
    })
  })

  it('走路速度是 #199（每秒 2.5）的 2 倍', () => {
    expect(WALK_SPEED).toBe(5)
  })

  it('沿環島公路走一圈要 140～180 秒（照實際路線，#207 規格修訂 1）', () => {
    const seconds = length([...ringRoad.points, ringRoad.points[0]]) / 5

    expect(seconds).toBeGreaterThanOrEqual(140)
    expect(seconds).toBeLessThanOrEqual(180)
  })

  it('沿環島公路，相鄰兩個部落（中心到中心）：紅頭—漁人 5～10 秒，其餘 10～40 秒（繞東南端的野銀—紅頭除外）', () => {
    const loop = [...ringRoad.points, ringRoad.points[0]]
    const codes = loop.map(tribeOn)
    // 沿環島公路從 from 走到 to（照公路的順序）要幾秒
    const secondsAlong = (from, to) => {
      const start = codes.indexOf(from)
      const end = codes.indexOf(to, start + 1)
      return length(loop.slice(start, end + 1)) / 5
    }
    const others = [
      ['iraraley', 'iranmeilek'],
      ['iranmeilek', 'ivalino'],
      ['iratay', 'yayo'],
      ['yayo', 'iraraley'],
    ]

    expect(secondsAlong('imowrod', 'iratay')).toBeGreaterThanOrEqual(5)
    expect(secondsAlong('imowrod', 'iratay')).toBeLessThanOrEqual(10)
    others.forEach(([from, to]) => {
      expect(secondsAlong(from, to)).toBeGreaterThanOrEqual(10)
      expect(secondsAlong(from, to)).toBeLessThanOrEqual(40)
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

  it('從紅頭中心點野銀中心：依序經過西端接點、東端接點（#207 驗收條件 2）', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('imowrod'), center('ivalino'))
    // 手寫的接點座標
    const west = route.findIndex((point) => point.x === 22.7 && point.z === 57.5)
    const east = route.findIndex((point) => point.x === 32.7 && point.z === 1.6)

    expect(west).toBeGreaterThanOrEqual(0)
    expect(east).toBeGreaterThan(west)
    expect(route.at(-1)).toEqual(center('ivalino'))
    // 不經過島的東南端：路線上沒有比野銀更東的點
    route.forEach((point) => expect(point.x).toBeLessThanOrEqual(center('ivalino').x))
  })

  it('從朗島點椰油：沿環島公路往西走，不經過別的部落', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('iraraley'), center('yayo'))

    expect(route.map(tribeOn).filter(Boolean)).toEqual(['yayo'])
    route.forEach((point) => expect(point.x).toBeLessThanOrEqual(center('iraraley').x))
  })

  it('朗島格子與環島公路交界的凹角：相距 0.2 的兩點不能直走（#207 照實際地圖重排後重找的座標）', () => {
    const inHex = { x: -46.32314696122948, z: -87.2555570233031 }
    const onRoad = { x: -46.15685303876897, z: -87.14444297669918 }

    expect(walkable.tribeAt(inHex)).toBe('iraraley')
    expect(walkable.tribeAt(onRoad)).toBeNull()
    expect(walkable.isWalkable(onRoad)).toBe(true)
    expect(walkable.isWalkable({ x: -46.24, z: -87.2 })).toBe(false)
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

  it('6 個部落在實際的位置：離由經緯度換算的位置（手寫）不超過 8 單位', () => {
    // 北緯 22.045、東經 121.55 為原點，1 公里＝22.5 單位；OpenStreetMap 的村落位置
    const real = {
      iraraley: [-55.2, -81.9],
      iranmeilek: [33.9, -32.0],
      ivalino: [38.1, 16.8],
      imowrod: [8.5, 48.7],
      iratay: [-16.4, 43.9],
      yayo: [-86.0, -14.2],
    }

    expect(ISLAND_MAP.tribes).toHaveLength(6)
    ISLAND_MAP.tribes.forEach(({ code, center: at }) => {
      expect(Math.hypot(at.x - real[code][0], at.z - real[code][1])).toBeLessThanOrEqual(8)
    })
  })

  it('環島公路穿過每個部落格子的中心', () => {
    ISLAND_MAP.tribes.forEach((tribe) => {
      expect(ringRoad.points.some((point) => samePoint(point, tribe.center))).toBe(true)
    })
  })

  const ringCodes = ringRoad.points.map(tribeOn)
  const ringIndex = (point) => ringRoad.points.findIndex((other) => samePoint(other, point))
  const junctions = [
    // [接點, 所屬部落, 在環島公路上哪兩個部落之間]
    { name: '西端', point: crossRoad.points[0], tribe: 'imowrod', between: ['ivalino', 'imowrod'] },
    {
      name: '東端',
      point: crossRoad.points.at(-1),
      tribe: 'ivalino',
      between: ['iranmeilek', 'ivalino'],
    },
  ]

  junctions.forEach(({ name, point, tribe, between }) => {
    it(`${name}接點是環島公路上的一點：西端在紅頭往野銀（島的東南端）那一段、東端在野銀往東清那一段`, () => {
      const at = ringIndex(point)

      expect(at).toBeGreaterThan(ringCodes.indexOf(between[0]))
      expect(at).toBeLessThan(ringCodes.indexOf(between[1]))
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

  const coastGap = (point) =>
    Math.min(
      ...edgesOf(ISLAND_DECOR.coast).map(([a, b]) =>
        distanceBetween(point, closestOnSegment(point, a, b))
      )
    )

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
    ISLAND_MAP.tribes.forEach((tribe) => {
      hexVertices(ISLAND_MAP.hexRadius, tribe.center).forEach((vertex) =>
        expect(isInside(vertex, ISLAND_DECOR.coast)).toBe(true)
      )
    })
    // 公路貼著海邊：中線上的每個折點都在裡面，而且離海岸線超過路的半寬
    ISLAND_MAP.roads
      .flatMap((road) => road.points)
      .forEach((point) => {
        expect(isInside(point, ISLAND_DECOR.coast)).toBe(true)
        expect(coastGap(point)).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      })
  })

  // ── #207 ──
  it('海岸線是實際的輪廓：50～80 個頂點，大小是實際的 10.4 × 9.5 公里換算再往外推 4 單位（#207 規格修訂 1）', () => {
    const xs = ISLAND_DECOR.coast.map((point) => point.x)
    const zs = ISLAND_DECOR.coast.map((point) => point.z)
    // 1 公里＝22.5 單位，兩邊各往外 4：寬約 242、高約 222
    expect(ISLAND_DECOR.coast.length).toBeGreaterThanOrEqual(50)
    expect(ISLAND_DECOR.coast.length).toBeLessThanOrEqual(80)
    expect(Math.abs(Math.max(...xs) - Math.min(...xs) - 242)).toBeLessThan(10)
    expect(Math.abs(Math.max(...zs) - Math.min(...zs) - 222)).toBeLessThan(10)
  })

  it('海岸線有實際的特徵：西北角突出、東南邊有一段沒有部落的尾巴', () => {
    const { coast } = ISLAND_DECOR
    const tribes = ISLAND_MAP.tribes.map((tribe) => tribe.center)
    // 東南的尾巴：有頂點比野銀、紅頭都更東也更南，而且離所有部落都超過 60
    const tail = coast.filter(
      (point) =>
        point.x > center('ivalino').x + 40 &&
        point.z > center('imowrod').z + 40 &&
        tribes.every((at) => distanceBetween(point, at) > 60)
    )
    // 西北角：有頂點比椰油更西、也比椰油更北
    const northwest = coast.filter(
      (point) => point.x < center('yayo').x - 10 && point.z < center('yayo').z - 10
    )

    expect(tail.length).toBeGreaterThan(0)
    expect(northwest.length).toBeGreaterThan(0)
  })

  it('沙灘有固定的寬度', () => {
    expect(ISLAND_DECOR.beachWidth).toBeGreaterThan(0)
  })

  it('沙灘以內的陸地：海岸線每個頂點往內縮，離海岸線的每一條邊都至少一個沙灘寬', () => {
    const { inland, coast, beachWidth } = ISLAND_DECOR

    expect(inland).toHaveLength(coast.length)
    inland.forEach((point) => {
      expect(isInside(point, coast)).toBe(true)
      expect(coastGap(point)).toBeGreaterThanOrEqual(beachWidth - 1e-9)
      // 也不會縮太多：轉角處最多到 2 倍寬
      expect(coastGap(point)).toBeLessThan(beachWidth * 2)
    })
  })

  it('往內縮的做法：邊長 10 的正方形往內縮 1，得到邊長 8 的正方形（順時針、逆時針都一樣）', () => {
    const square = [
      { x: 0, z: 0 },
      { x: 10, z: 0 },
      { x: 10, z: 10 },
      { x: 0, z: 10 },
    ]
    const expected = [
      [1, 1],
      [9, 1],
      [9, 9],
      [1, 9],
    ]

    insetOutline(square, 1).forEach((point, i) => {
      expect(point.x).toBeCloseTo(expected[i][0], 9)
      expect(point.z).toBeCloseTo(expected[i][1], 9)
    })
    const reversed = insetOutline([...square].reverse(), 1)
    ;[...expected].reverse().forEach(([x, z], i) => {
      expect(reversed[i].x).toBeCloseTo(x, 9)
      expect(reversed[i].z).toBeCloseTo(z, 9)
    })
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

  it('最高的山對應紅頭山：在北半部、朗島與東清之間的內陸', () => {
    const highest = ISLAND_DECOR.mountains.reduce((a, b) => (b.height > a.height ? b : a))

    expect(highest.z).toBeLessThan(0)
    expect(highest.x).toBeGreaterThan(center('iraraley').x)
    expect(highest.x).toBeLessThan(center('iranmeilek').x)
    expect(coastGap(highest)).toBeGreaterThan(20)
  })

  it('山在實際山頭的位置：離由經緯度換算的山頂（手寫）不超過 10 單位，越高的山頭在 3D 裡也越高', () => {
    // [x, z, 實際高度（公尺）]，OpenStreetMap 的山頂位置
    const peaks = [
      [-35.8, -20.0, 548], // 紅頭山
      [-73.4, -33.9, 509], // 相愛山
      [-39.2, -51.9, 495], // 殺蛇山
      [82.9, 86.6, 483], // 大森山
      [15.6, -72.1, 464], // 尖禿山
      [-16.5, -36.9, 439], // 青蛇山
      [16.3, -0.5, 432], // 山田山
      [-25.5, 17.7, 408], // 飯山
      [102.4, 62.9, 378], // 龍門溪山
      [39.5, 39.4, 298], // 奧本嶺
      [-30.4, 34.2, 236], // 台山
    ]
    const nearestPeak = (mountain) =>
      peaks.reduce((a, b) =>
        Math.hypot(b[0] - mountain.x, b[1] - mountain.z) <
        Math.hypot(a[0] - mountain.x, a[1] - mountain.z)
          ? b
          : a
      )
    const matched = ISLAND_DECOR.mountains.map((mountain) => {
      const [x, z, metres] = nearestPeak(mountain)
      expect(Math.hypot(x - mountain.x, z - mountain.z)).toBeLessThanOrEqual(10)
      return { metres, height: mountain.height }
    })

    expect(new Set(matched.map((m) => m.metres)).size).toBe(matched.length)
    const byMetres = [...matched].sort((a, b) => a.metres - b.metres)
    byMetres.slice(1).forEach((m, i) => expect(m.height).toBeGreaterThan(byMetres[i].height))
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
    // 靠山＝離海岸線更遠
    const fartherInland = (from, to) => coastGap(to) > coastGap(from)
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

    it('部落旁的在格子外、靠山的那一側（比格子中心離海岸線更遠）、離格子邊不超過 8', () => {
      beside.forEach((paddy) => {
        const tribeCenter = center(paddy.tribe)
        const edge = clampToHex(paddy, ISLAND_MAP.hexRadius, tribeCenter)

        expect(fartherInland(tribeCenter, paddy)).toBe(true)
        expect(distanceBetween(paddy, edge)).toBeLessThanOrEqual(8)
      })
    })

    it('公路旁的在環島公路靠內陸的那一側（比公路上最近的點離海岸線更遠）、離路邊不超過 8', () => {
      alongRoad.forEach((paddy) => {
        const onRoad = nearestOnRing(paddy)

        expect(fartherInland(onRoad, paddy)).toBe(true)
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
      expect(coastGap(boat)).toBeLessThan(40)
    })
  })
})
