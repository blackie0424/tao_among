import { describe, expect, it } from 'vitest'
import { ISLAND_MAP, WALK_SPEED } from '@/island3d/islandMap'
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

  it('橫貫公路只接紅頭與野銀，中途不經過別的部落', () => {
    const codes = crossRoad.points.map(tribeOn)

    expect(crossRoad.closed).toBe(false)
    expect([codes[0], codes.at(-1)]).toEqual(['imowrod', 'ivalino'])
    expect(codes.slice(1, -1).filter(Boolean)).toEqual([])
    expect(ISLAND_MAP.roads).toHaveLength(2)
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

  it('從朗島點紅頭：沿環島公路往西走（經過椰油、漁人），比往東近', () => {
    const findRoute = createRouter(ISLAND_MAP, walkable)
    const route = findRoute(center('iraraley'), center('imowrod'))

    expect(route.map(tribeOn)).toEqual(['yayo', 'iratay', 'imowrod'])
  })

  it('朗島格子與環島公路交界的凹角：相距 0.2 的兩點不能直走（審查回報的座標）', () => {
    const inHex = { x: 3.1183114058269643, z: -65.01192686017863 }
    const onRoad = { x: 3.296764011474805, z: -65.11483130724925 }

    expect(walkable.isWalkable(inHex)).toBe(true)
    expect(walkable.isWalkable(onRoad)).toBe(true)
    expect(walkable.isWalkable({ x: 3.1361566663917486, z: -65.0222173048857 })).toBe(false)
    expect(walkable.isStraightWalkable(inHex, onRoad)).toBe(false)
  })
})
