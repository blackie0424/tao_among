// 魚島 3D：點畫面時，先點到的是不是山或船（#200 驗收條件 4：點山、海、船沒有反應）。不依賴 three.js。
// 做法：看從鏡頭到「視線打到地面的那一點」這一段直線——
// 船當成圓錐（船身在地面、中心在 (x, z)、半徑 radius、高 height），直線有一處在圓錐裡就是被它擋住；
// 山是地形（#223，terrainHeight.js），直線有一段比地形低超過 TERRAIN_TOLERANCE 就是被它擋住。
import { heightAt } from './terrainHeight'

// 直線比地形低不到這麼多（只是擦過山頂或山脊）不算擋住
const TERRAIN_TOLERANCE = 0.01

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

/**
 * 直線投影到地面後，穿過高度表的格線（x、z 方向）與每格的對角線的位置（t，0～1 之間，含兩端）。
 * 相鄰兩個 t 之間，直線都在同一個三角形裡。
 */
function triangleBreaks(camera, ground, { x0, z0, step }) {
  const u = (t) => (camera.x + (ground.x - camera.x) * t - x0) / step
  const w = (t) => (camera.z + (ground.z - camera.z) * t - z0) / step
  const families = [u, w, (t) => u(t) - w(t)]
  const breaks = [0, 1]
  families.forEach((value) => {
    const [start, end] = [value(0), value(1)]
    const [low, high] = start < end ? [start, end] : [end, start]
    for (let k = Math.ceil(low); k <= high; k += 1) {
      if (end !== start) {
        breaks.push((k - start) / (end - start))
      }
    }
  })
  return breaks.sort((a, b) => a - b)
}

/**
 * 從鏡頭 camera（{ x, y, z }）看向地面上的 ground（{ x, z }，y = 0）時，中間有沒有被地形 field
 * （terrainHeight.js 的高度表）擋住。精確判定：三角形裡直線與地形的高度差是一次式，
 * 所以只要檢查直線進出每個三角形的那幾點。
 */
export function isBlockedByTerrain(camera, ground, field) {
  return triangleBreaks(camera, ground, field).some((t) => {
    const point = {
      x: camera.x + (ground.x - camera.x) * t,
      z: camera.z + (ground.z - camera.z) * t,
    }
    return camera.y * (1 - t) < heightAt(field, point) - TERRAIN_TOLERANCE
  })
}
