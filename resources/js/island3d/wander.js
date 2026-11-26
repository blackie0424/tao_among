// 魚島 3D：NPC 在自己的範圍裡來回走動（#199 驗收條件 3）。不依賴 three.js。
// 路線 route: { from, to 兩端的位置, speed 每秒走多遠, pauseSeconds 每端停多久 }

// 預設路線：兩端各在離中心幾成半徑的地方（都在六角格裡）
const ROUTE_REACH = 0.45
const ROUTE_DEPTH = 0.35
const DEFAULT_SPEED = 1.2
const DEFAULT_PAUSE_SECONDS = 2

/** 半徑 radius 的六角格上的預設路線：在格子後半（畫面遠處）左右來回 */
export function wanderRoute(radius) {
  return {
    from: { x: -radius * ROUTE_REACH, z: -radius * ROUTE_DEPTH },
    to: { x: radius * ROUTE_REACH, z: -radius * ROUTE_DEPTH },
    speed: DEFAULT_SPEED,
    pauseSeconds: DEFAULT_PAUSE_SECONDS,
  }
}

const between = (from, to, t) => ({
  x: from.x + (to.x - from.x) * t,
  z: from.z + (to.z - from.z) * t,
})

/**
 * 過了 elapsedSeconds 之後 NPC 在哪裡：起點停一下 → 走到終點 → 停一下 → 走回起點，一直重複。
 * reducedMotion（系統設定「減少動態效果」）時站在起點不動。
 */
export function wanderPosition(elapsedSeconds, route, { reducedMotion = false } = {}) {
  const { from, to, speed, pauseSeconds } = route
  if (reducedMotion) {
    return { ...from }
  }
  const travelSeconds = Math.hypot(to.x - from.x, to.z - from.z) / speed
  const legSeconds = pauseSeconds + travelSeconds
  const inCycle = elapsedSeconds % (legSeconds * 2)
  const returning = inCycle >= legSeconds
  const inLeg = returning ? inCycle - legSeconds : inCycle
  const progress = Math.max(0, inLeg - pauseSeconds) / travelSeconds
  return returning ? between(to, from, progress) : between(from, to, progress)
}
