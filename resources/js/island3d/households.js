// 魚島 3D：每個部落格子裡的一戶人家（#201 驗收條件 1–4）——四件東西擺在哪、朝哪，每件的範圍。
// 不依賴 three.js。
//
// 每格的擺法不一樣：環島公路穿過大部分格子、方向各不相同，所以逐格排。規則（測試逐格檢查）：
// 同一戶四件朝同一個方向（和格子中心到最近海岸的方向相差不超過 45 度）；整個在格子裡；
// 離公路中線至少「路寬的一半＋0.5」；件與件、和水田與特色建築至少隔 1.0；離格子中心至少 2.5；
// 格子中心到公路、到每個範圍中心的直線離每一件至少 0.5；vahay 最靠山（其他三件比它前面至少 2）。
// 朗島第 2 格旁邊沒有公路，另外讓主格中心到第 2 格中心的直線也離每一件至少 0.5（從主格走得過去）。
// 下面的位置由一次性的試算腳本照這些規則找出來，不在 repo 裡（同 #212 的做法）。
import { footprintCorners } from './landmarks'

const DEGREES_TO_RADIANS = Math.PI / 180

/** 四件依序的種類 */
export const HOUSEHOLD_KIND_ORDER = ['vahay', 'pananadengan', 'zazawan', 'tagakal']

/** 每種東西的佔地（寬 × 深）與範圍半徑（家人活動、走近出現提示的範圍） */
export const HOUSEHOLD_KINDS = {
  vahay: { width: 7, depth: 5, zoneRadius: 2.2 },
  pananadengan: { width: 3.4, depth: 2.4, zoneRadius: 2 },
  zazawan: { width: 4, depth: 3, zoneRadius: 2.6 },
  tagakal: { width: 3, depth: 3, zoneRadius: 2.2 },
}

/**
 * 一戶：tribe 部落代碼、hex 第幾格（0 是主格）、degrees 朝向（和 three.js 的 rotation.y 一樣，0 朝 +z）；
 * items 每件 [種類, 中心 x, 中心 z, 範圍中心 x, 範圍中心 z]
 */
const household = (tribe, hex, degrees, items) => {
  const heading = degrees * DEGREES_TO_RADIANS
  return {
    tribe,
    hex,
    heading,
    items: items.map(([kind, x, z, zoneX, zoneZ]) => {
      const { width, depth, zoneRadius } = HOUSEHOLD_KINDS[kind]
      return { kind, x, z, width, depth, heading, zone: { x: zoneX, z: zoneZ, radius: zoneRadius } }
    }),
  }
}

/** 18 戶，依部落與格子的順序（和 ISLAND_MAP.tribes、tribeHexes 一樣） */
export const HOUSEHOLDS = [
  household('iraraley', 0, 157, [
    ['vahay', -83.3, -116.55, -81.45, -120.92],
    ['pananadengan', -79.28, -131.13, -80.55, -128.14],
    ['zazawan', -88.53, -128.54, -90.15, -124.72],
    ['tagakal', -74.98, -119.53, -73.51, -122.98],
  ]),
  household('iraraley', 1, -172, [
    ['vahay', -99.83, -105.53, -100.49, -110.23],
    ['pananadengan', -105.02, -120.95, -104.57, -117.73],
    ['zazawan', -109.36, -112.26, -104.75, -112.91],
    ['tagakal', -92.45, -114.14, -96.17, -113.61],
  ]),
  household('iraraley', 2, -172, [
    ['vahay', -57.63, -122.77, -58.29, -127.47],
    ['pananadengan', -62.82, -138.19, -62.37, -134.98],
    ['zazawan', -53.99, -136.41, -53.41, -132.3],
    ['tagakal', -64.91, -124.27, -65.43, -127.98],
  ]),
  household('iranmeilek', 0, 54, [
    ['vahay', 37.24, -57.81, 41.08, -55.02],
    ['pananadengan', 48.42, -45.98, 45.79, -47.89],
    ['zazawan', 37.28, -48.51, 40.64, -46.07],
    ['tagakal', 46.68, -61.46, 44.48, -58.42],
  ]),
  household('iranmeilek', 1, 86, [
    ['vahay', 57.33, -70.36, 62.07, -70.03],
    ['pananadengan', 65.33, -55.76, 65.59, -59.5],
    ['zazawan', 69.45, -64.5, 65.31, -64.79],
    ['tagakal', 58.95, -57.71, 59.21, -61.45],
  ]),
  household('iranmeilek', 2, 91, [
    ['vahay', 34.9, -24.58, 39.65, -24.66],
    ['pananadengan', 50.91, -24.36, 47.66, -24.3],
    ['zazawan', 40.53, -17.17, 44.68, -17.25],
    ['tagakal', 38.26, -32.64, 42.01, -32.7],
  ]),
  household('ivalino', 0, 141, [
    ['vahay', 49.59, 34.39, 52.58, 30.7],
    ['pananadengan', 58.5, 21.01, 56.45, 23.54],
    ['zazawan', 47.86, 24.62, 50.47, 21.39],
    ['tagakal', 59.35, 35.85, 56.43, 33.49],
  ]),
  household('ivalino', 1, 145, [
    ['vahay', 68.73, 43.78, 71.46, 39.89],
    ['pananadengan', 77.09, 30.1, 75.23, 32.76],
    ['zazawan', 78.03, 43.57, 80.41, 40.17],
    ['tagakal', 64.03, 37.43, 66.18, 34.36],
  ]),
  household('ivalino', 2, 145, [
    ['vahay', 86.88, 59.33, 89.61, 55.44],
    ['pananadengan', 99.75, 54.91, 96.68, 52.76],
    ['zazawan', 85.49, 47.37, 89.3, 50.04],
    ['tagakal', 94.75, 61.18, 96.9, 58.11],
  ]),
  household('imowrod', 0, -32, [
    ['vahay', 21.86, 58.56, 19.34, 62.59],
    ['pananadengan', 14.23, 72.66, 15.95, 69.91],
    ['zazawan', 12.85, 58.83, 10.65, 62.35],
    ['tagakal', 26.15, 67.73, 22.97, 65.75],
  ]),
  household('imowrod', 1, -52, [
    ['vahay', 45.22, 82.59, 41.47, 85.51],
    ['pananadengan', 30.32, 87.89, 32.63, 90.84],
    ['zazawan', 45.5, 91.89, 42.23, 94.44],
    ['tagakal', 34.85, 94.5, 37.8, 92.19],
  ]),
  household('imowrod', 2, -52, [
    ['vahay', 62.22, 101.09, 58.47, 104.01],
    ['pananadengan', 60.36, 113.33, 58.05, 110.37],
    ['zazawan', 50.9, 100.41, 54.17, 97.86],
    ['tagakal', 61.97, 108.9, 64.93, 106.59],
  ]),
  household('iratay', 0, 22, [
    ['vahay', -30.07, 52.31, -28.29, 56.71],
    ['pananadengan', -22.69, 66.58, -23.91, 63.57],
    ['zazawan', -33.65, 63.46, -29.34, 61.72],
    ['tagakal', -20.41, 54.88, -19, 58.35],
  ]),
  household('iratay', 1, -31, [
    ['vahay', -45.64, 64.5, -48.09, 68.58],
    ['pananadengan', -61.93, 63.47, -58.72, 65.4],
    ['zazawan', -55.33, 70.93, -53.2, 67.37],
    ['tagakal', -56.87, 61.84, -54.94, 58.63],
  ]),
  household('iratay', 2, -77, [
    ['vahay', -64.58, 34.73, -69.21, 35.79],
    ['pananadengan', -79.46, 39.19, -80.3, 35.53],
    ['zazawan', -75.26, 28.47, -74.21, 33],
    ['tagakal', -65.55, 41.62, -69.21, 42.46],
  ]),
  household('yayo', 0, -75, [
    ['vahay', -115.49, -23.11, -120.08, -21.88],
    ['pananadengan', -131.46, -20.9, -128.32, -21.74],
    ['zazawan', -123.82, -13.63, -125.02, -18.12],
    ['tagakal', -122.87, -29.42, -126.49, -28.45],
  ]),
  household('yayo', 1, -93, [
    ['vahay', -129.91, -57.63, -134.65, -57.88],
    ['pananadengan', -145.94, -57.47, -142.69, -57.3],
    ['zazawan', -136.79, -50.48, -140.94, -50.7],
    ['tagakal', -140.98, -66.22, -141.17, -62.48],
  ]),
  household('yayo', 2, -76, [
    ['vahay', -102.71, -1.49, -107.32, -0.34],
    ['pananadengan', -118.72, 0.44, -115.56, -0.35],
    ['zazawan', -111.69, 7.96, -112.81, 3.45],
    ['tagakal', -110.47, -7.8, -114.1, -6.9],
  ]),
]

/** 四件的佔地（四個角），給可走範圍當擋路的東西 */
export function householdObstacles(households = HOUSEHOLDS) {
  return households.flatMap(({ items }) => items.map(footprintCorners))
}

/**
 * 這一點在哪一件的範圍裡（走近的提示，#201 驗收條件 9）：{ tribe, hex 第幾格, slot 第幾件（0～3） }；
 * 不在任何範圍裡是 null。範圍彼此不重疊，所以最多一個。
 */
export function spotAt(point, households = HOUSEHOLDS) {
  for (const { tribe, hex, items } of households) {
    const slot = items.findIndex(
      ({ zone }) => Math.hypot(point.x - zone.x, point.z - zone.z) <= zone.radius
    )
    if (slot !== -1) {
      return { tribe, hex, slot }
    }
  }
  return null
}
