import { describe, expect, it } from 'vitest'
import { closestOnSegment } from '@/island3d/hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, roadSegments, tribeHexes } from '@/island3d/islandMap'
import {
  BEACH_REACH_RADIUS,
  BEACH_SEARCH_RADIUS,
  BEACH_SPOTS,
  beachAt,
  computeBeachSpots,
} from '@/island3d/beachSpots'

// #232 AC 1：每個部落一個「海邊」，在環島公路上、離主格不遠、離海岸線最近

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const gapToSegments = (point, segments) =>
  Math.min(...segments.map(({ from, to }) => distance(point, closestOnSegment(point, from, to))))
const edges = (polygon) =>
  polygon.map((from, i) => ({ from, to: polygon[(i + 1) % polygon.length] }))
const ringRoad = ISLAND_MAP.roads.find((road) => road.closed)
const ringSegments = roadSegments({ roads: [ringRoad] })

describe('BEACH_SPOTS（固定的座標表）', () => {
  it('6 個部落各一個，依地圖的部落順序', () => {
    expect(BEACH_SPOTS.map((spot) => spot.tribe)).toEqual(
      ISLAND_MAP.tribes.map((tribe) => tribe.code)
    )
  })

  it('和照規則算出來的位置相同（小數一位）', () => {
    expect(BEACH_SPOTS).toEqual(computeBeachSpots(ISLAND_MAP, ISLAND_DECOR.coast))
  })

  it('每個都在環島公路的中線上（誤差 0.1 以內）', () => {
    for (const spot of BEACH_SPOTS) {
      expect(gapToSegments(spot, ringSegments)).toBeLessThanOrEqual(0.1)
    }
  })

  it('每個都在所有部落格子的外面、離自己的主格不超過搜尋距離', () => {
    const hexes = ISLAND_MAP.tribes.flatMap(tribeHexes)
    for (const spot of BEACH_SPOTS) {
      const main = ISLAND_MAP.tribes.find((tribe) => tribe.code === spot.tribe).center
      expect(distance(spot, main)).toBeLessThanOrEqual(BEACH_SEARCH_RADIUS)
      for (const hex of hexes) {
        expect(distance(spot, hex)).toBeGreaterThan(ISLAND_MAP.hexRadius + BEACH_REACH_RADIUS)
      }
    }
  })
})

describe('computeBeachSpots（規則）', () => {
  // 小地圖：一個部落、主格在原點，環島公路是一條往 +x 的直線，海岸線在 z = 10 那一側往 +x 越來越近
  const hexRadius = 4
  const map = {
    hexRadius,
    roadHalfWidth: 2,
    tribes: [{ code: 'a', center: { x: 0, z: 0 } }],
    roads: [
      {
        points: [
          { x: -30, z: 0 },
          { x: 30, z: 0 },
          { x: 30, z: -1 },
          { x: -30, z: -1 },
        ],
        closed: true,
      },
    ],
  }
  const coast = [
    { x: -40, z: 20 },
    { x: 40, z: 5 },
    { x: 40, z: -40 },
    { x: -40, z: -40 },
  ]

  it('在搜尋距離內、格子外，挑離海岸線最近的公路點', () => {
    const [spot] = computeBeachSpots(map, coast)

    expect(spot.tribe).toBe('a')
    // 海岸線往 +x 越來越近，所以挑搜尋距離邊緣、+x 那一端
    expect(spot.x).toBeGreaterThan(0)
    expect(distance(spot, map.tribes[0].center)).toBeLessThanOrEqual(BEACH_SEARCH_RADIUS)
    expect(distance(spot, map.tribes[0].center)).toBeGreaterThan(hexRadius + BEACH_REACH_RADIUS)
  })

  it('座標四捨五入到小數一位', () => {
    const [spot] = computeBeachSpots(map, coast)

    expect(Math.round(spot.x * 10) / 10).toBe(spot.x)
    expect(Math.round(spot.z * 10) / 10).toBe(spot.z)
  })

  it('只看環島公路，不看橫貫公路', () => {
    const withCross = {
      ...map,
      roads: [
        ...map.roads,
        {
          points: [
            { x: 0, z: 8 },
            { x: 0, z: 9.9 },
          ],
          closed: false,
        },
      ],
    }

    expect(computeBeachSpots(withCross, coast)).toEqual(computeBeachSpots(map, coast))
  })
})

describe('beachAt（走到海邊的範圍裡）', () => {
  const spot = BEACH_SPOTS[0]

  it('在範圍裡回傳那個部落', () => {
    expect(beachAt({ x: spot.x, z: spot.z })).toBe(spot.tribe)
    expect(beachAt({ x: spot.x + BEACH_REACH_RADIUS - 0.01, z: spot.z })).toBe(spot.tribe)
  })

  it('在範圍外是 null', () => {
    expect(beachAt({ x: spot.x + BEACH_REACH_RADIUS + 0.01, z: spot.z })).toBeNull()
  })

  it('可以傳入別的座標表（測試用）', () => {
    expect(beachAt({ x: 1, z: 1 }, [{ tribe: 'b', x: 0, z: 0 }])).toBe('b')
  })
})
