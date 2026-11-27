// 魚島 3D：點畫面時，先點到的是不是船（#200 驗收條件 4：點山、海、船沒有反應；山在 #223 改成地形）。
// 不依賴 three.js。船當成圓錐：船身在地面、中心在 (x, z)、半徑 radius、高 height。
// 做法：從鏡頭到「視線打到地面的那一點」這一段直線，只要有一處在某個圓錐裡，就是被它擋住。

// 船當成這麼大的圓錐（比模型略大，點在船邊也算點到船）
const BOAT_RADIUS = 2
const BOAT_HEIGHT = 2.5

/** 會擋住點擊的圓錐：decor（islandMap.js 的 ISLAND_DECOR）裡的每艘船 */
export function tapBlockers(decor) {
  return decor.boats.map(({ x, z }) => ({ x, z, radius: BOAT_RADIUS, height: BOAT_HEIGHT }))
}

/**
 * 直線 camera → ground 有沒有穿過這個圓錐。直線上的位置用 t 表示（0 是鏡頭、1 是地面那一點）。
 * 在圓錐裡 ⇔ 高度在 0～height 之間，而且離中軸的距離不超過圓錐在那個高度的半徑；
 * 後者寫成 inside(t) <= 0，inside 是 t 的二次式。
 */
function passesThroughCone(camera, ground, { x, z, radius, height }) {
  const fromX = camera.x - x
  const fromZ = camera.z - z
  const stepX = ground.x - camera.x
  const stepZ = ground.z - camera.z
  const slope = radius / height
  // 圓錐在高度 y 的半徑是 slope × (height − y)；直線的高度是 camera.y × (1 − t)
  const reach = slope * (height - camera.y)
  const reachStep = slope * camera.y
  const a = stepX * stepX + stepZ * stepZ - reachStep * reachStep
  const b = 2 * (fromX * stepX + fromZ * stepZ - reach * reachStep)
  const c = fromX * fromX + fromZ * fromZ - reach * reach
  const inside = (t) => a * t * t + b * t + c

  // 只看高度不超過山頂的那一段
  const start = Math.max(0, 1 - height / camera.y)
  const lowest = -b / (2 * a)
  const dipsBetween = a > 0 && lowest > start && lowest < 1 && inside(lowest) <= 0
  return inside(start) <= 0 || inside(1) <= 0 || dipsBetween
}

/**
 * 從鏡頭 camera（{ x, y, z }，在地面以上）看向地面上的 ground（{ x, z }）時，
 * 中間有沒有被 blockers 裡的山或船擋住。
 */
export function isTapBlocked(camera, ground, blockers) {
  return blockers.some((cone) => passesThroughCone(camera, ground, cone))
}
