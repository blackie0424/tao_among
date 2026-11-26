// 魚島 3D：哪裡可以走（#200 驗收條件 3）。可走範圍＝所有部落格子 ∪ 所有公路（中線加路寬）。
// 不依賴 three.js。地圖資料的格式見 islandMap.js。
import { clampToHex, closestOnSegment, isInsideHex } from './hexBounds'
import { roadSegments } from './islandMap'

// 剛好在路邊的點因為小數誤差會差一點點，差在這個範圍內都算在路上
const EDGE_TOLERANCE = 1e-9
// 判斷「直線整段可走」時，沿線每隔這麼遠檢查一點（比公路窄很多，不會漏掉缺口）
const STRAIGHT_SAMPLE_SPACING = 0.25

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)

/** 公路外的點拉回這一段公路最近的路邊 */
function clampToRoad(point, { from, to }, halfWidth) {
  const onCenterLine = closestOnSegment(point, from, to)
  const away = distance(point, onCenterLine)
  if (away <= halfWidth) {
    return { x: point.x, z: point.z }
  }
  const ratio = halfWidth / away
  return {
    x: onCenterLine.x + (point.x - onCenterLine.x) * ratio,
    z: onCenterLine.z + (point.z - onCenterLine.z) * ratio,
  }
}

/**
 * 回傳這張地圖的可走範圍判斷：
 * - isWalkable(point) 這一點能不能走
 * - clampToWalkable(point) 可走的點原樣回傳；不可走的點拉回最近的可走位置
 * - tribeAt(point) 這一點在哪個部落格子裡（代碼）；不在任何格子裡是 null
 * - isStraightWalkable(from, to) 兩點之間的直線是否整段可走
 */
export function createWalkable(map) {
  const { hexRadius, roadHalfWidth, tribes } = map
  const segments = roadSegments(map)

  const tribeAt = (point) =>
    tribes.find(({ center }) => isInsideHex(point, hexRadius, center))?.code ?? null

  const isOnRoad = (point) =>
    segments.some(
      ({ from, to }) =>
        distance(point, closestOnSegment(point, from, to)) <= roadHalfWidth + EDGE_TOLERANCE
    )

  const isWalkable = (point) => tribeAt(point) !== null || isOnRoad(point)

  const clampToWalkable = (point) => {
    if (isWalkable(point)) {
      return { x: point.x, z: point.z }
    }
    const candidates = [
      ...tribes.map(({ center }) => clampToHex(point, hexRadius, center)),
      ...segments.map((segment) => clampToRoad(point, segment, roadHalfWidth)),
    ]
    return candidates.reduce((best, candidate) =>
      distance(candidate, point) < distance(best, point) ? candidate : best
    )
  }

  const isStraightWalkable = (from, to) => {
    const steps = Math.max(1, Math.ceil(distance(from, to) / STRAIGHT_SAMPLE_SPACING))
    for (let i = 0; i <= steps; i++) {
      const t = i / steps
      if (!isWalkable({ x: from.x + (to.x - from.x) * t, z: from.z + (to.z - from.z) * t })) {
        return false
      }
    }
    return true
  }

  return { isWalkable, clampToWalkable, tribeAt, isStraightWalkable }
}
