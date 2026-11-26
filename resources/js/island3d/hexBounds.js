// 魚島 3D：部落六角格的邊界。格子是平頂六角形，中心在原點，躺在 x–z 平面上；
// radius 是中心到頂點的距離。這裡不依賴 three.js，只算座標。

const HEX_SIDES = 6
const HEX_STEP_RADIANS = Math.PI / 3

/** 六個頂點，從 +x 方向開始逆時針 */
function hexVertices(radius) {
  return Array.from({ length: HEX_SIDES }, (_, i) => ({
    x: radius * Math.cos(i * HEX_STEP_RADIANS),
    z: radius * Math.sin(i * HEX_STEP_RADIANS),
  }))
}

/** 點是否在六角格裡（含邊上） */
export function isInsideHex(point, radius) {
  const vertices = hexVertices(radius)
  return vertices.every((from, i) => {
    const to = vertices[(i + 1) % HEX_SIDES]
    // 頂點逆時針排列：點在每條邊的左側（或邊上）才算在裡面
    return (to.x - from.x) * (point.z - from.z) - (to.z - from.z) * (point.x - from.x) >= 0
  })
}

/** 線段 from–to 上離 point 最近的點 */
function closestOnSegment(point, from, to) {
  const dx = to.x - from.x
  const dz = to.z - from.z
  const along = ((point.x - from.x) * dx + (point.z - from.z) * dz) / (dx * dx + dz * dz)
  const t = Math.min(1, Math.max(0, along))
  return { x: from.x + dx * t, z: from.z + dz * t }
}

/** 格子裡的點原樣回傳；格子外的點拉回最近的邊（或頂點）上 */
export function clampToHex(point, radius) {
  if (isInsideHex(point, radius)) {
    return { x: point.x, z: point.z }
  }
  const vertices = hexVertices(radius)
  return vertices
    .map((from, i) => closestOnSegment(point, from, vertices[(i + 1) % HEX_SIDES]))
    .reduce((best, candidate) =>
      Math.hypot(candidate.x - point.x, candidate.z - point.z) <
      Math.hypot(best.x - point.x, best.z - point.z)
        ? candidate
        : best
    )
}
