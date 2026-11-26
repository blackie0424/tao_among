// 測試用的手建小地圖（不是真的魚島），座標好算：
//   west (0,0) ── east (100,0) ── (160,0)
//                    │               │
//                 south (100,100) ── (160,100)
// 六角格半徑 10（上下兩條邊在中心 ±8.66）、公路半寬 2。
// east → south 直走 100；繞 (160,0)、(160,100) 是 220。
export const WEST = { x: 0, z: 0 }
export const EAST = { x: 100, z: 0 }
export const SOUTH = { x: 100, z: 100 }
export const BEND_NORTH = { x: 160, z: 0 }
export const BEND_SOUTH = { x: 160, z: 100 }

export const THREE_TRIBE_MAP = {
  hexRadius: 10,
  roadHalfWidth: 2,
  tribes: [
    { code: 'west', center: WEST },
    { code: 'east', center: EAST },
    { code: 'south', center: SOUTH },
  ],
  roads: [
    { points: [WEST, EAST], closed: false },
    { points: [EAST, SOUTH], closed: false },
    { points: [EAST, BEND_NORTH, BEND_SOUTH, SOUTH], closed: false },
  ],
}

// 只有一個半徑 10 的六角格、沒有公路（等於 #199 的單一格子）
export const ONE_HEX_MAP = {
  hexRadius: 10,
  roadHalfWidth: 2,
  tribes: [{ code: 'only', center: { x: 0, z: 0 } }],
  roads: [],
}

// 一個部落兩格：第 2 格貼著主格的右下邊（中心距離是半徑的 √3 倍，方向是 +x 往 +z 轉 30 度），
// 兩格共用從 (10, 0) 到 (5, 8.66) 的那條邊。沒有公路。
export const TWIN_SECOND = {
  x: 10 * Math.sqrt(3) * Math.cos(Math.PI / 6),
  z: 10 * Math.sqrt(3) * 0.5,
}
export const TWIN_HEX_MAP = {
  hexRadius: 10,
  roadHalfWidth: 2,
  tribes: [{ code: 'twin', center: { x: 0, z: 0 }, hexes: [{ x: 0, z: 0 }, TWIN_SECOND] }],
  roads: [],
}

// 一條東西向的直路（z = 0，從 x = -100 到 100），旁邊有一格的中心偏離公路 6（公路從格子裡穿過，
// 但中心不在路上、也不在公路的折點上）；far 格在路的另一頭。
export const OFF_ROAD_CENTER = { x: 0, z: 6 }
export const FAR_CENTER = { x: 100, z: 0 }
export const OFF_ROAD_MAP = {
  hexRadius: 10,
  roadHalfWidth: 2,
  tribes: [
    { code: 'off', center: OFF_ROAD_CENTER },
    { code: 'far', center: FAR_CENTER },
  ],
  roads: [{ points: [{ x: -100, z: 0 }, FAR_CENTER], closed: false }],
}

// west 格子的右上邊（從 (10,0) 到 (5,8.66)）和公路上緣（z = 2）在 (8.845, 2) 相交，
// 交角外側是一小塊不可走的凹角。這兩點都可走、相距 0.16，連線的中段 (8.875, 2.025) 在凹角裡。
export const NOTCH_IN_HEX = { x: 8.8, z: 2.05 }
export const NOTCH_ON_ROAD = { x: 8.95, z: 2 }

/** from 到 to 的直線上，每隔 spacing 取一點（含兩端） */
export function pointsBetween(from, to, spacing = 0.001) {
  const steps = Math.max(1, Math.ceil(Math.hypot(to.x - from.x, to.z - from.z) / spacing))
  return Array.from({ length: steps + 1 }, (_, i) => ({
    x: from.x + ((to.x - from.x) * i) / steps,
    z: from.z + ((to.z - from.z) * i) / steps,
  }))
}
