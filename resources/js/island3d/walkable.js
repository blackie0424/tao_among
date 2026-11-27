// 魚島 3D：哪裡可以走（#200 驗收條件 3）。可走範圍＝所有部落格子 ∪ 所有公路（中線加路寬）。
// 不依賴 three.js。地圖資料的格式見 islandMap.js。
//
// 「直線整段可走」不用取樣：每個格子、每段公路都是凸的形狀，一條直線和它重疊的部分
// 是連續的一段。把每個形狀重疊的那一段算出來，看它們接起來有沒有蓋滿整條直線。
// 直線上的位置用 0～1 表示（0 是起點、1 是終點），一段寫成 [開始, 結束]。
import { clampToHex, closestOnSegment, hexVertices, isInsideHex } from './hexBounds'
import { roadSegments, tribeHexes } from './islandMap'

// 剛好在邊上的點因為小數誤差會差一點點，差在這個範圍內都算在邊上
const EDGE_TOLERANCE = 1e-9
// 算直線和形狀重疊的那一段時用一半的容許值：算出來的端點一定被 isWalkable 認定可走
const SPAN_TOLERANCE = EDGE_TOLERANCE / 2
// 直線和邊幾乎平行時不算交點
const PARALLEL_LIMIT = 1e-12

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const copy = (point) => ({ x: point.x, z: point.z })
const pointAt = (from, to, t) => ({
  x: from.x + (to.x - from.x) * t,
  z: from.z + (to.z - from.z) * t,
})

/** 直線 from–to 和凸多邊形重疊的那一段；沒有重疊是 null。vertices 的順序和 hexVertices 相同 */
function spanInPolygon(from, to, vertices) {
  let start = 0
  let end = 1
  for (let i = 0; i < vertices.length; i++) {
    const a = vertices[i]
    const b = vertices[(i + 1) % vertices.length]
    const edgeX = b.x - a.x
    const edgeZ = b.z - a.z
    // 點在這條邊的內側 ⇔ side + t × slope >= 0
    const side = edgeX * (from.z - a.z) - edgeZ * (from.x - a.x)
    const slope = edgeX * (to.z - from.z) - edgeZ * (to.x - from.x)
    if (Math.abs(slope) < PARALLEL_LIMIT) {
      if (side < -SPAN_TOLERANCE) {
        return null
      }
      continue
    }
    const crossing = (-SPAN_TOLERANCE - side) / slope
    if (slope > 0) {
      start = Math.max(start, crossing)
    } else {
      end = Math.min(end, crossing)
    }
  }
  return start <= end ? [start, end] : null
}

/** 直線 from–to 和圓重疊的那一段；沒有重疊是 null */
function spanInDisc(from, to, center, radius) {
  const dx = to.x - from.x
  const dz = to.z - from.z
  const a = dx * dx + dz * dz
  const b = 2 * (dx * (from.x - center.x) + dz * (from.z - center.z))
  const c = (from.x - center.x) ** 2 + (from.z - center.z) ** 2 - (radius + SPAN_TOLERANCE) ** 2
  const discriminant = b * b - 4 * a * c
  if (discriminant < 0) {
    return null
  }
  const root = Math.sqrt(discriminant)
  const start = Math.max(0, (-b - root) / (2 * a))
  const end = Math.min(1, (-b + root) / (2 * a))
  return start <= end ? [start, end] : null
}

/** 一段公路（中線 from–to 加路寬）的長方形部分的四個角，順序和 hexVertices 相同 */
function roadCorners({ from, to }, halfWidth) {
  const length = distance(from, to)
  const sideX = (-(to.z - from.z) / length) * halfWidth
  const sideZ = ((to.x - from.x) / length) * halfWidth
  return [
    { x: from.x - sideX, z: from.z - sideZ },
    { x: to.x - sideX, z: to.z - sideZ },
    { x: to.x + sideX, z: to.z + sideZ },
    { x: from.x + sideX, z: from.z + sideZ },
  ]
}

/**
 * 直線 from–to 和一段公路重疊的那一段。公路＝長方形加兩端的半圓，整個是凸的，
 * 所以重疊的部分是三塊各自重疊的那一段合起來的最外圍。
 */
function spanInRoad(from, to, road, halfWidth) {
  const spans = [
    spanInPolygon(from, to, road.corners),
    spanInDisc(from, to, road.from, halfWidth),
    spanInDisc(from, to, road.to, halfWidth),
  ].filter(Boolean)
  return spans.length === 0
    ? null
    : [Math.min(...spans.map(([start]) => start)), Math.max(...spans.map(([, end]) => end))]
}

/** 公路外的點拉回這一段公路最近的路邊 */
function clampToRoad(point, { from, to }, halfWidth) {
  const onCenterLine = closestOnSegment(point, from, to)
  const ratio = halfWidth / distance(point, onCenterLine)
  return {
    x: onCenterLine.x + (point.x - onCenterLine.x) * ratio,
    z: onCenterLine.z + (point.z - onCenterLine.z) * ratio,
  }
}

/** 從直線的起點出發，沿著一段接一段可走的部分，最遠能到哪（0～1）；起點就不可走是 null */
function reachAlong(spans, slack) {
  let reach = null
  for (const [start, end] of [...spans].sort((a, b) => a[0] - b[0])) {
    if (start > (reach ?? 0) + slack) {
      break
    }
    reach = Math.max(reach ?? 0, end)
  }
  return reach
}

/** 不可走的點拉回最近的可走位置：每個格子、每段公路各自最近的邊上那一點裡，取最近的 */
function nearestEdgePoint(point, { hexRadius, roadHalfWidth, tribes }, roads) {
  const candidates = [
    ...tribes.flatMap(tribeHexes).map((center) => clampToHex(point, hexRadius, center)),
    ...roads.map((road) => clampToRoad(point, road, roadHalfWidth)),
  ]
  return candidates.reduce((best, candidate) =>
    distance(candidate, point) < distance(best, point) ? candidate : best
  )
}

/** 直線 from–to 和每個格子、每段公路重疊的那一段（沒有重疊的不列） */
function walkableSpans(from, to, hexes, roads, roadHalfWidth) {
  return [
    ...hexes.map((vertices) => spanInPolygon(from, to, vertices)),
    ...roads.map((road) => spanInRoad(from, to, road, roadHalfWidth)),
  ].filter(Boolean)
}

/**
 * 回傳這張地圖的可走範圍判斷：
 * - isWalkable(point) 這一點能不能走
 * - clampToWalkable(point) 可走的點原樣回傳；不可走的點拉回最近的可走位置
 * - tribeAt(point) 這一點在哪個部落格子裡（代碼）；不在任何格子裡是 null
 * - isStraightWalkable(from, to) 兩點之間的直線是否整段可走
 * - reachToward(from, to) 從 from 往 to 直走，最遠走得到的那一點（整段可走就是 to）
 */
export function createWalkable(map) {
  const { hexRadius, roadHalfWidth, tribes } = map
  const hexes = tribes.flatMap(tribeHexes).map((center) => hexVertices(hexRadius, center))
  const roads = roadSegments(map).map((segment) => ({
    ...segment,
    corners: roadCorners(segment, roadHalfWidth),
  }))

  const tribeAt = (point) =>
    tribes.find((tribe) =>
      tribeHexes(tribe).some((center) => isInsideHex(point, hexRadius, center))
    )?.code ?? null

  const isOnRoad = (point) =>
    roads.some(
      ({ from, to }) =>
        distance(point, closestOnSegment(point, from, to)) <= roadHalfWidth + EDGE_TOLERANCE
    )

  const isWalkable = (point) => tribeAt(point) !== null || isOnRoad(point)

  const clampToWalkable = (point) =>
    isWalkable(point) ? copy(point) : nearestEdgePoint(point, map, roads)

  /** 從 from 往 to 直走最遠能到直線的哪裡（0～1）；from 不可走是 null */
  const reachFraction = (from, to) => {
    const length = distance(from, to)
    if (length === 0) {
      return isWalkable(from) ? 1 : null
    }
    const spans = walkableSpans(from, to, hexes, roads, roadHalfWidth)
    return reachAlong(spans, SPAN_TOLERANCE / length)
  }

  const isStraightWalkable = (from, to) => (reachFraction(from, to) ?? 0) >= 1

  const reachToward = (from, to) => {
    const reach = reachFraction(from, to) ?? 0
    return reach >= 1 ? copy(to) : pointAt(from, to, reach)
  }

  return { isWalkable, clampToWalkable, tribeAt, isStraightWalkable, reachToward }
}
