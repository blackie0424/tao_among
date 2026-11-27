import { describe, expect, it } from 'vitest'
import { clampToHex, closestOnSegment, hexVertices, isInsideHex } from '@/island3d/hexBounds'
import {
  ISLAND_DECOR,
  ISLAND_MAP,
  ISLAND_SCALE,
  WALK_SPEED,
  insetOutline,
} from '@/island3d/islandMap'
import {
  LANDMARKS,
  footprintCorners,
  footprintGapToSegment,
  footprintInside,
  footprintsOverlap,
} from '@/island3d/landmarks'
import { PADDY_LAYOUTS, paddySize } from '@/island3d/paddyShape'
import { createRouter } from '@/island3d/route'
import { createWalkable } from '@/island3d/walkable'
import { heightAt, islandHeightField } from '@/island3d/terrainHeight'

const center = (code) => ISLAND_MAP.tribes.find((tribe) => tribe.code === code).center
/** 一個部落的 3 格中心：主格、第 2 格、第 3 格 */
const hexesOf = (code) => ISLAND_MAP.tribes.find((tribe) => tribe.code === code).hexes
/** 18 格：[{ code 部落, index 第幾格（0 是主格）, at 中心, corners 六個頂點 }] */
const ALL_HEXES = ISLAND_MAP.tribes.flatMap(({ code, hexes }) =>
  hexes.map((at, index) => ({
    code,
    index,
    at,
    corners: hexVertices(ISLAND_MAP.hexRadius, at),
  }))
)
const WALK_SPEED_212 = 7.5
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
    ).toBeLessThan(60)
  })

  it('野銀第 3 格在東南邊的環島公路旁 (90.9, 53.6)，原本那塊公路旁水田挪到 (104.5, 63)（#201 規格修訂 1）', () => {
    expect(hexesOf('ivalino')[2]).toEqual({ x: 90.9, z: 53.6 })
    expect(ISLAND_DECOR.paddies.some(({ x, z }) => x === 93.3 && z === 55.8)).toBe(false)
    expect(ISLAND_DECOR.paddies.find(({ x, z }) => x === 104.5 && z === 63)).toMatchObject({
      heading: (60 * Math.PI) / 180,
    })
  })

  it('18 格任兩格不重疊（共用一條邊不算）', () => {
    ALL_HEXES.forEach((a, i) => {
      ALL_HEXES.slice(i + 1).forEach((b) => {
        expect(footprintsOverlap(a.corners, b.corners)).toBe(false)
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

  it('部落之間沒有捷徑：不同部落的格子不相連；格子中心之間能直走的，一定是環島公路上相鄰的一對部落', () => {
    const adjacent = [
      'iranmeilek–iraraley',
      'iranmeilek–ivalino',
      'imowrod–iratay',
      'iratay–yayo',
      'iraraley–yayo',
    ]
    const pairs = ALL_HEXES.flatMap((a, i) =>
      ALL_HEXES.slice(i + 1)
        .filter((b) => b.code !== a.code)
        .map((b) => [a, b])
    )
    pairs.forEach(([a, b]) => {
      const gap = Math.min(
        ...edgesOf(a.corners).flatMap(([p, q]) =>
          edgesOf(b.corners).map(([r, t]) => segmentGap(p, q, r, t))
        )
      )
      expect(gap).toBeGreaterThan(0)
      expect(distanceBetween(a.at, b.at)).toBeGreaterThanOrEqual(25)
      if (walkable.isStraightWalkable(a.at, b.at)) {
        expect(adjacent).toContain([a.code, b.code].sort().join('–'))
      }
    })
  })

  it('走路速度是每秒 7.5：#207（每秒 5）的 1.5 倍，和島放大的倍數一樣（#212 驗收條件 7）', () => {
    expect(WALK_SPEED).toBe(WALK_SPEED_212)
  })

  it('沿環島公路走一圈仍是 140～180 秒（島與速度都放大 1.5 倍，#212 驗收條件 7）', () => {
    const seconds = length([...ringRoad.points, ringRoad.points[0]]) / WALK_SPEED_212

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
      return length(loop.slice(start, end + 1)) / WALK_SPEED_212
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
    // 手寫的接點座標（#207 的 (22.7, 57.5)、(32.7, 1.6) 放大 1.5 倍）
    const near = (x, z) => (point) => Math.hypot(point.x - x, point.z - z) < 1e-6
    const west = route.findIndex(near(34.05, 86.25))
    const east = route.findIndex(near(49.05, 2.4))

    expect(west).toBeGreaterThanOrEqual(0)
    expect(east).toBeGreaterThan(west)
    expect(route.at(-1)).toEqual(center('ivalino'))
    // 不經過島的東南端：路線上沒有比野銀更東的點
    route.forEach((point) => expect(point.x).toBeLessThanOrEqual(center('ivalino').x + 1e-9))
  })

  it('從朗島點椰油：沿環島公路往西走，不經過別的部落', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('iraraley'), center('yayo'))

    expect(route.map(tribeOn).filter(Boolean)).toEqual(['yayo'])
    route.forEach((point) => expect(point.x).toBeLessThanOrEqual(center('iraraley').x))
  })

  it('朗島主格與環島公路交界的凹角：相距 0.2 的兩點不能直走（#212 島放大後重找的座標）', () => {
    const inHex = { x: -73.94, z: -128.2 }
    const onRoad = { x: -73.76679491924115, z: -128.1 }

    expect(walkable.tribeAt(inHex)).toBe('iraraley')
    expect(walkable.tribeAt(onRoad)).toBeNull()
    expect(walkable.isWalkable(onRoad)).toBe(true)
    expect(walkable.isWalkable({ x: -73.8534, z: -128.15 })).toBe(false)
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

  it('6 個部落的主格在實際的位置：離由經緯度換算的位置（手寫）不超過 12 單位', () => {
    // 北緯 22.045、東經 121.55 為原點，1 公里＝33.75 單位（#207 的 22.5 放大 1.5 倍）；
    // OpenStreetMap 的村落位置
    const real = {
      iraraley: [-82.8, -122.85],
      iranmeilek: [50.85, -48.0],
      ivalino: [57.15, 25.2],
      imowrod: [12.75, 73.05],
      iratay: [-24.6, 65.85],
      yayo: [-129.0, -21.3],
    }

    expect(ISLAND_MAP.tribes).toHaveLength(6)
    ISLAND_MAP.tribes.forEach(({ code, center: at }) => {
      expect(Math.hypot(at.x - real[code][0], at.z - real[code][1])).toBeLessThanOrEqual(12)
    })
  })

  it('環島公路穿過每個部落主格的中心', () => {
    ISLAND_MAP.tribes.forEach((tribe) => {
      expect(ringRoad.points.some((point) => samePoint(point, tribe.center))).toBe(true)
    })
  })

  const ringCodes = ringRoad.points.map(tribeOn)
  const ringIndex = (point) => ringRoad.points.findIndex((other) => samePoint(other, point))
  const junctions = [
    // [接點, 所屬部落, 在環島公路上哪兩個部落之間]
    {
      name: '西端',
      point: crossRoad.points[0],
      tribe: 'imowrod',
      hexIndex: 1,
      between: ['ivalino', 'imowrod'],
    },
    {
      name: '東端',
      point: crossRoad.points.at(-1),
      tribe: null,
      hexIndex: null,
      between: ['iranmeilek', 'ivalino'],
    },
  ]

  junctions.forEach(({ name, point, tribe, hexIndex, between }) => {
    it(`${name}接點是環島公路上的一點：西端在紅頭往野銀（島的東南端）那一段、東端在野銀往東清那一段`, () => {
      const at = ringIndex(point)

      expect(at).toBeGreaterThan(ringCodes.indexOf(between[0]))
      expect(at).toBeLessThan(ringCodes.indexOf(between[1]))
    })
  })

  it('西端接點在紅頭第 2 格裡：下山就進部落（#212 驗收條件 3）', () => {
    const [west] = junctions

    expect(isInsideHex(west.point, ISLAND_MAP.hexRadius, hexesOf('imowrod')[1])).toBe(true)
    expect(walkable.tribeAt(west.point)).toBe('imowrod')
  })

  it('東端接點不在任何格子裡：下山先接到環島公路，再走一小段才進野銀（#201 規格修訂 1）', () => {
    const east = junctions.at(-1)

    expect(walkable.tribeAt(east.point)).toBeNull()
  })

  it('橫貫公路的路面只碰紅頭第 2 格，不碰其他 17 格：每一段離格子都超過公路半寬（#201 規格修訂 1）', () => {
    const segments = crossRoad.points.slice(1).map((to, i) => [crossRoad.points[i], to])
    const isJunctionHex = ({ code, index }) =>
      junctions.some(({ tribe, hexIndex }) => tribe === code && hexIndex === index)
    const others = ALL_HEXES.filter((hex) => !isJunctionHex(hex))

    expect(others).toHaveLength(17)
    others.forEach(({ corners }) => {
      segments.forEach(([from, to]) => {
        const gap = Math.min(...edgesOf(corners).map(([a, b]) => segmentGap(from, to, a, b)))
        expect(gap).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      })
    })
  })
})

describe('island3d/islandMap 海岸與船（#200 驗收條件 10；山在 #223 改成地形，見 terrainHeight.spec）', () => {
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

  it('有山在島的中間（原點附近），橫貫公路從山區穿過（#223 改看地形高度）', () => {
    const field = islandHeightField()
    // 山田山頂（OpenStreetMap，換算成 (24.5, -0.8)）離原點 45 以內，地形超過 15 單位
    expect(heightAt(field, { x: 24, z: 0 })).toBeGreaterThan(15)
    // 橫貫公路大致南北走向，中段 (33.75, 45.75) 走在山坳：路是平地，東西兩側 15 單位都是坡（超過 1 單位）
    expect(heightAt(field, { x: 33.75, z: 45.75 })).toBe(0)
    expect(heightAt(field, { x: 18.75, z: 45.75 })).toBeGreaterThan(1)
    expect(heightAt(field, { x: 48.75, z: 45.75 })).toBeGreaterThan(1)
  })

  it('部落格子與公路都在海岸線裡面（不會畫在海上）', () => {
    ALL_HEXES.forEach(({ corners }) => {
      corners.forEach((vertex) => expect(isInside(vertex, ISLAND_DECOR.coast)).toBe(true))
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
  it('海岸線是實際的輪廓：50～80 個頂點，大小是 #207（實際的 10.4 × 9.5 公里換算再往外推 4 單位）的 1.5 倍', () => {
    const xs = ISLAND_DECOR.coast.map((point) => point.x)
    const zs = ISLAND_DECOR.coast.map((point) => point.z)
    // #207：1 公里＝22.5 單位，兩邊各往外 4，寬約 242、高約 222；放大 1.5 倍是寬約 363、高約 333
    expect(ISLAND_DECOR.coast.length).toBeGreaterThanOrEqual(50)
    expect(ISLAND_DECOR.coast.length).toBeLessThanOrEqual(80)
    expect(Math.abs(Math.max(...xs) - Math.min(...xs) - 363)).toBeLessThan(15)
    expect(Math.abs(Math.max(...zs) - Math.min(...zs) - 333)).toBeLessThan(15)
  })

  it('海岸線有實際的特徵：西北角突出、東南邊有一段沒有部落的尾巴', () => {
    const { coast } = ISLAND_DECOR
    const tribes = ISLAND_MAP.tribes.map((tribe) => tribe.center)
    // 東南的尾巴：有頂點比野銀、紅頭都更東也更南，而且離所有部落的主格都超過 90
    const tail = coast.filter(
      (point) =>
        point.x > center('ivalino').x + 60 &&
        point.z > center('imowrod').z + 60 &&
        tribes.every((at) => distanceBetween(point, at) > 90)
    )
    // 西北角：有頂點比椰油更西、也比椰油更北
    const northwest = coast.filter(
      (point) => point.x < center('yayo').x - 15 && point.z < center('yayo').z - 15
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

  describe('水田（#207 驗收條件 5、#212 驗收條件 11）', () => {
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

    it('一共 14 處，大小照拼法（#211 規格修訂 1）', () => {
      expect(paddies).toHaveLength(14)
      paddies.forEach((paddy) => {
        expect(paddy.width).toBeCloseTo(paddySize(paddy.layout).width, 9)
        expect(paddy.depth).toBeCloseTo(paddySize(paddy.layout).depth, 9)
      })
    })

    it('拼法照表：部落旁 2×2 或 2×3、公路旁 3×3 與 1×3 交錯，4 種都有出現（#211 驗收條件 14）', () => {
      const byTribe = Object.fromEntries(beside.map((paddy) => [paddy.tribe, paddy.layout]))
      expect(byTribe).toEqual({
        iraraley: PADDY_LAYOUTS['2x2'],
        iranmeilek: PADDY_LAYOUTS['2x3'],
        ivalino: PADDY_LAYOUTS['2x2'],
        imowrod: PADDY_LAYOUTS['2x3'],
        iratay: PADDY_LAYOUTS['2x2'],
        yayo: PADDY_LAYOUTS['2x3'],
      })
      expect(alongRoad.map((paddy) => paddy.layout)).toEqual(
        [0, 1, 2, 3, 4, 5, 6, 7].map((i) => PADDY_LAYOUTS[i % 2 ? '1x3' : '3x3'])
      )
      expect(new Set(paddies.map((paddy) => paddy.layout.join('x'))).size).toBe(4)
    })

    it('部落旁的在格子外、環島公路靠內陸那一側、佔地離路邊 0～2、離該部落任一格的邊不超過 8（#223 驗收條件 6）', () => {
      // 野銀旁的環島公路沒有空位，放在橫貫公路旁（#223 規格修訂 1），另一條測試
      beside.filter((paddy) => paddy.tribe !== 'ivalino').forEach((paddy) => {
        const corners = footprintCorners(paddy)
        const fromEdge = Math.min(
          ...hexesOf(paddy.tribe).map((at) =>
            Math.min(...corners.map((corner) => distanceBetween(corner, clampToHex(corner, ISLAND_MAP.hexRadius, at))))
          )
        )
        const fromRoad = Math.min(
          ...ringSegments.map(([a, b]) => footprintGapToSegment(corners, a, b))
        )

        expect(walkable.tribeAt(paddy)).toBeNull()
        expect(fartherInland(nearestOnRing(paddy), paddy)).toBe(true)
        expect(fromRoad).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
        expect(fromRoad).toBeLessThanOrEqual(ISLAND_MAP.roadHalfWidth + 2)
        expect(fromEdge).toBeLessThanOrEqual(8)
      })
    })

    it('野銀旁的在橫貫公路旁：佔地離橫貫公路的路邊 0～2、離野銀任一格的邊不超過 8（#223 規格修訂 1）', () => {
      const paddy = beside.find((item) => item.tribe === 'ivalino')
      const corners = footprintCorners(paddy)
      const crossSegments = crossRoad.points.slice(1).map((to, i) => [crossRoad.points[i], to])
      const fromRoad = Math.min(...crossSegments.map(([a, b]) => footprintGapToSegment(corners, a, b)))
      const fromEdge = Math.min(
        ...hexesOf('ivalino').map((at) =>
          Math.min(...corners.map((corner) => distanceBetween(corner, clampToHex(corner, ISLAND_MAP.hexRadius, at))))
        )
      )

      expect(walkable.tribeAt(paddy)).toBeNull()
      expect(fromRoad).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      expect(fromRoad).toBeLessThanOrEqual(ISLAND_MAP.roadHalfWidth + 2)
      expect(fromEdge).toBeLessThanOrEqual(8)
    })

    it('不壓到特色建築（#223 驗收條件 6）', () => {
      paddies.forEach((paddy) => {
        LANDMARKS.forEach((landmark) => {
          expect(footprintsOverlap(footprintCorners(paddy), footprintCorners(landmark))).toBe(false)
        })
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
        ALL_HEXES.forEach((hex) => {
          expect(footprintsOverlap(corners, hex.corners)).toBe(false)
          edgesOf(hex.corners).forEach(([a, b]) => {
            expect(footprintGapToSegment(corners, a, b)).toBeGreaterThan(0)
          })
        })
      })
    })

    it('整塊都在海岸線裡面、不在沙灘上', () => {
      paddies.forEach((paddy) => {
        const corners = footprintCorners(paddy)

        expect(footprintInside(corners, ISLAND_DECOR.coast)).toBe(true)
        edgesOf(ISLAND_DECOR.coast).forEach(([a, b]) => {
          expect(footprintGapToSegment(corners, a, b)).toBeGreaterThan(ISLAND_DECOR.beachWidth)
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
      expect(coastGap(boat)).toBeLessThan(60)
    })
  })
})

describe('island3d/islandMap 島放大 1.5 倍（#212 驗收條件 1）', () => {
  const closeTo = (point, [x, z]) => Math.hypot(point.x - x, point.z - z) < 1e-6

  it('放大倍數是 1.5；格子半徑 12、公路半寬 2 不跟著放大', () => {
    expect(ISLAND_SCALE).toBe(1.5)
    expect(ISLAND_MAP.hexRadius).toBe(12)
    expect(ISLAND_MAP.roadHalfWidth).toBe(2)
  })

  it('公路的點是 #207 數值的 1.5 倍（手寫的抽樣）', () => {
    // #207：環島公路第 2 點 (-38.2, -87.8)、島的東南端 (115.0, 63.2)；橫貫公路中段 (22.5, 30.5)
    expect(ringRoad.points).toHaveLength(47)
    expect(closeTo(ringRoad.points[1], [-57.3, -131.7])).toBe(true)
    expect(ringRoad.points.some((point) => closeTo(point, [172.5, 94.8]))).toBe(true)
    expect(crossRoad.points).toHaveLength(5)
    expect(closeTo(crossRoad.points[2], [33.75, 45.75])).toBe(true)
  })

  it('船的位置是 #207 數值的 1.5 倍', () => {
    // #207：(-103, 2)、(131, 74)、(-45, -104)
    const expected = [
      [-154.5, 3],
      [196.5, 111],
      [-67.5, -156],
    ]

    expect(ISLAND_DECOR.boats).toHaveLength(3)
    expected.forEach((at) => {
      expect(ISLAND_DECOR.boats.some((boat) => closeTo(boat, at))).toBe(true)
    })
  })

  it('海岸線是 #207 數值的 1.5 倍；只有機場旁（椰油往漁人那一段）3 個頂點再往外推，最多 11 單位', () => {
    // #207 的頂點放大 1.5 倍（手寫的抽樣）：西北角、東南端、東清外海
    const { coast } = ISLAND_DECOR
    ;[
      [-150.75, -44.7],
      [183.15, 103.65],
      [78.9, -136.05],
    ].forEach((at) => expect(coast.some((point) => closeTo(point, at))).toBe(true))
    // #207 的 (-71.6, 18.2)、(-64.1, 17.5)、(-60.1, 20.8) 放大後的位置；現在這 3 個頂點在它們的外側
    const before = [
      [-107.4, 27.3],
      [-96.15, 26.25],
      [-90.15, 31.2],
    ]
    const moved = [6, 7, 8].map((i) => coast[i])

    expect(coast).toHaveLength(55)
    moved.forEach((point, i) => {
      const shift = Math.hypot(point.x - before[i][0], point.z - before[i][1])
      expect(shift).toBeGreaterThan(0)
      expect(shift).toBeLessThanOrEqual(11)
      // 往外推：比原本的位置更南（這一段海岸在公路的南邊）
      expect(point.z).toBeGreaterThan(before[i][1])
    })
    expect(coast.filter((point) => !before.some((at) => closeTo(point, at)))).toHaveLength(55)
  })
})

describe('island3d/islandMap 每個部落 3 格（#212 驗收條件 2、3）', () => {
  // 手寫的位置：[主格, 第 2 格, 第 3 格]
  const expected = {
    iraraley: [
      [-82.8, -122.9],
      [-100.8, -112.5],
      [-58.6, -129.7],
    ],
    iranmeilek: [
      [42.9, -53.7],
      [60.9, -64.1],
      [41.9, -24.7],
    ],
    ivalino: [
      [54.0, 29.0],
      [72.8, 38.0],
      // #201 規格修訂 1：從橫貫公路的接點 (54.0, 8.2) 移到東南邊的環島公路旁
      [90.9, 53.6],
    ],
    imowrod: [
      [18.1, 64.5],
      [39.7, 86.9],
      [56.7, 105.4],
    ],
    iratay: [
      [-27.5, 58.8],
      [-52.5, 63.3],
      [-71.4, 36.3],
    ],
    yayo: [
      [-122.3, -21.3],
      [-136.9, -58.0],
      [-109.5, 0.2],
    ],
  }
  // 貼著主格的 3 格：[部落, 第幾格, 沿著相貼的邊錯開多遠]（野銀第 3 格在 #201 移走，不再貼著主格）
  const attached = [
    ['iraraley', 1, 0],
    ['iranmeilek', 1, 0],
    ['ivalino', 1, 1.5],
  ]
  const neighborDistance = 12 * Math.sqrt(3)
  const allRoadSegments = ISLAND_MAP.roads.flatMap(({ points, closed }) => {
    const ends = closed ? [...points, points[0]] : points
    return ends.slice(1).map((to, i) => [ends[i], to])
  })
  const roadGap = (point) =>
    Math.min(
      ...allRoadSegments.map(([a, b]) => distanceBetween(point, closestOnSegment(point, a, b)))
    )

  it('每個部落 3 格、共 18 格；主格排第一，就是 center', () => {
    expect(ALL_HEXES).toHaveLength(18)
    ISLAND_MAP.tribes.forEach((tribe) => {
      expect(tribe.hexes).toHaveLength(3)
      expect(tribe.hexes[0]).toBe(tribe.center)
    })
  })

  it('格子中心離手寫的位置不超過 1', () => {
    Object.entries(expected).forEach(([code, positions]) => {
      positions.forEach(([x, z], i) => {
        const at = hexesOf(code)[i]
        expect(Math.hypot(at.x - x, at.z - z)).toBeLessThanOrEqual(1)
      })
    })
  })

  it('除了朗島第 2 格，每一格都有公路穿過：環島或橫貫公路的中線離格子中心不超過 9', () => {
    ALL_HEXES.filter(({ code, index }) => !(code === 'iraraley' && index === 1)).forEach(({ at }) =>
      expect(roadGap(at)).toBeLessThanOrEqual(9)
    )
  })

  it('貼著主格的 3 格由主格算出：中心距離是半徑的 √3 倍再加上沿邊錯開的量，不是四捨五入的座標', () => {
    attached.forEach(([code, index, slide]) => {
      const apart = distanceBetween(center(code), hexesOf(code)[index])
      expect(apart).toBeCloseTo(Math.hypot(neighborDistance, slide), 9)
    })
  })

  it('整條邊對齊的 2 格：和主格各有兩個頂點重合，誤差在可走範圍的容許值（1e-9）內', () => {
    attached
      .filter(([, , slide]) => slide === 0)
      .forEach(([code, index]) => {
        const main = hexVertices(ISLAND_MAP.hexRadius, center(code))
        const other = hexVertices(ISLAND_MAP.hexRadius, hexesOf(code)[index])
        const shared = main.filter((p) => other.some((q) => distanceBetween(p, q) < 1e-9))
        expect(shared).toHaveLength(2)
      })
  })

  it('貼著的兩格中間沒有縫：從主格中心到那一格的中心可以直走', () => {
    attached.forEach(([code, index]) => {
      expect(walkable.isStraightWalkable(center(code), hexesOf(code)[index])).toBe(true)
      expect(walkable.isStraightWalkable(hexesOf(code)[index], center(code))).toBe(true)
    })
  })
})

describe('island3d/islandMap 18 格都走得到、所在部落（#212 驗收條件 4、5）', () => {
  const findRoute = createRouter(ISLAND_MAP, walkable)

  it('從任何一格的中心點任何另一格的中心都走得到；路線上每一段都整段可走', () => {
    const trips = ALL_HEXES.flatMap((from) =>
      ALL_HEXES.filter((to) => to !== from).map((to) => [from.at, to.at])
    )
    /** 從 from 照 route 走，每一段是否都整段可走 */
    const everyLegWalkable = (from, route) =>
      route.every((point, i) => walkable.isStraightWalkable([from, ...route][i], point))

    expect(trips).toHaveLength(18 * 17)
    trips.forEach(([from, to]) => {
      const route = findRoute(from, to)

      expect(route).not.toBeNull()
      expect(route.at(-1)).toEqual({ x: to.x, z: to.z })
      expect(everyLegWalkable(from, route)).toBe(true)
    })
  })

  it('從朗島第 2 格的中心到朗島主格的中心：直走，穿過共用的邊', () => {
    const [main, second] = hexesOf('iraraley')

    expect(findRoute(second, main)).toEqual([{ x: main.x, z: main.z }])
  })

  it('從朗島第 2 格的中心到椰油主格：先經過朗島主格再上公路', () => {
    const [main, second] = hexesOf('iraraley')
    const route = findRoute(second, center('yayo'))

    expect(route[0]).toEqual({ x: main.x, z: main.z })
    expect(route.at(-1)).toEqual(center('yayo'))
  })

  it('紅頭第 3 格的中心不在公路的折點上（離公路 6）：仍然走得到主格', () => {
    const [main, , third] = hexesOf('imowrod')

    expect(findRoute(third, main)).not.toBeNull()
    expect(findRoute(main, third)).not.toBeNull()
  })

  it('站在某部落 3 格的任一格裡，所在部落都是那個部落', () => {
    ALL_HEXES.forEach(({ code, at }) => {
      expect(walkable.tribeAt(at)).toBe(code)
      // 格子裡偏離中心的地方也是
      expect(walkable.tribeAt({ x: at.x + 5, z: at.z - 4 })).toBe(code)
    })
  })

  it('同部落兩格之間的公路上不在任何格子裡（畫面維持上一個部落）：漁人主格與第 2 格之間', () => {
    // 手寫的位置：漁人往椰油的環島公路上，主格西緣與第 2 格東緣之間
    const onRoad = { x: -41.0, z: 58.0 }

    expect(walkable.isWalkable(onRoad)).toBe(true)
    expect(walkable.tribeAt(onRoad)).toBeNull()
  })
})
