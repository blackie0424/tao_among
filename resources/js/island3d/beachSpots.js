// #232 每個部落的「海邊」：走過去就能找魚（之後由 #233 港灣釣點取代）。
// 位置照規則算：環島公路上、離這個部落主格不超過 BEACH_SEARCH_RADIUS、在所有格子外面，離海岸線最近的一點。
// 算好的結果寫成 BEACH_SPOTS 常數表（同 #212 格子座標的做法），測試鎖住兩者一致
import { closestOnSegment } from './hexBounds'
import { roadSegments, tribeHexes } from './islandMap'

/** 海邊離主格最遠多少（世界單位）；30 時有部落在格子外找不到公路點 */
export const BEACH_SEARCH_RADIUS = 40
/** 走到海邊多近算到了（同曬魚架的範圍） */
export const BEACH_REACH_RADIUS = 2.6
/** 沿公路每隔多少取一個候選點 */
const SAMPLE_STEP = 0.5

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const round1 = (value) => Math.round(value * 10) / 10
const edgesOf = (polygon) =>
  polygon.map((from, i) => ({ from, to: polygon[(i + 1) % polygon.length] }))

/** 一段路上每隔 SAMPLE_STEP 的點（含兩端） */
function samplesAlong({ from, to }) {
  const steps = Math.max(1, Math.ceil(distance(from, to) / SAMPLE_STEP))
  return Array.from({ length: steps + 1 }, (_, i) => ({
    x: from.x + ((to.x - from.x) * i) / steps,
    z: from.z + ((to.z - from.z) * i) / steps,
  }))
}

/** 照規則算每個部落的海邊：[{ tribe, x, z }]，依地圖的部落順序 */
export function computeBeachSpots(map, coast) {
  const ring = map.roads.filter((road) => road.closed)
  const samples = roadSegments({ roads: ring }).flatMap(samplesAlong)
  const hexes = map.tribes.flatMap(tribeHexes)
  const coastEdges = edgesOf(coast)
  const coastGap = (point) =>
    Math.min(
      ...coastEdges.map(({ from, to }) => distance(point, closestOnSegment(point, from, to)))
    )
  const outsideHexes = (point) =>
    hexes.every((hex) => distance(point, hex) > map.hexRadius + BEACH_REACH_RADIUS)

  return map.tribes.map((tribe) => {
    const best = samples
      .filter(
        (point) => distance(point, tribe.center) <= BEACH_SEARCH_RADIUS && outsideHexes(point)
      )
      .reduce(
        (found, point) => (found === null || coastGap(point) < coastGap(found) ? point : found),
        null
      )
    return { tribe: tribe.code, x: round1(best.x), z: round1(best.z) }
  })
}

/** 算好的海邊（computeBeachSpots(ISLAND_MAP, ISLAND_DECOR.coast) 的結果） */
export const BEACH_SPOTS = [
  { tribe: 'iraraley', x: -44.5, z: -133.5 },
  { tribe: 'iranmeilek', x: 60.3, z: -78.8 },
  { tribe: 'ivalino', x: 46.7, z: -10.2 },
  { tribe: 'imowrod', x: 27.7, z: 77.9 },
  { tribe: 'iratay', x: -62.9, z: 52.9 },
  { tribe: 'yayo', x: -139.6, z: -43.4 },
]

/** 這一點在哪個部落的海邊範圍裡；不在任何範圍裡是 null */
export function beachAt(point, spots = BEACH_SPOTS) {
  return spots.find((spot) => distance(point, spot) <= BEACH_REACH_RADIUS)?.tribe ?? null
}
