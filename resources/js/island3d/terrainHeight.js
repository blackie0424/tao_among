// 魚島 3D：島中間的地形（#223）——由實際地形的原始高度，收出部落格子、公路、水田、特色建築周圍的平地，
// 再提供「某點有多高」。繪圖、點擊判定都讀這一份。不依賴 three.js。
// 高度表每格固定沿 (行, 列) → (行＋1, 列＋1) 的對角線切成兩個三角形；「某點有多高」用那個點所在三角形的
// 三個頂點內插，跟畫出來的網格是同一組三角形，所以查到的高度就是畫出來的表面。
import { closestOnSegment, hexVertices } from './hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, ISLAND_SCALE, roadSegments, tribeHexes } from './islandMap'
import { LANDMARKS, footprintCorners, footprintGapToPoint, isInPolygon } from './landmarks'
import { TERRAIN_RAW } from './terrainData'

// 高度照實際換算再誇大這麼多倍，手機上才看得出起伏
export const TERRAIN_EXAGGERATION = 1.5
// 1 公尺是多少世界單位：1 公里＝22.5 單位（#207）再乘上整座島的放大倍數
const METERS_PER_KILOMETER = 1000
const UNITS_PER_KILOMETER = 22.5 * ISLAND_SCALE
const UNITS_PER_METER = (UNITS_PER_KILOMETER / METERS_PER_KILOMETER) * TERRAIN_EXAGGERATION
// 離平地範圍這麼近的網格點都是 0：比網格的對角線（2 × √2 ≈ 2.83）長，平地範圍裡任何一點所在的三角形
// 三個頂點都是 0
export const FLAT_MARGIN = 3
// 平地範圍往外 FLAT_MARGIN 之後，再這麼遠才升到原始高度
export const SLOPE_WIDTH = 10
// 離沙灘以內陸地的邊這麼近的，同樣往海邊收到 0
export const COAST_WIDTH = 6

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)

/** 平滑曲線：t ≤ 0 是 0、t ≥ 1 是 1，中間兩端都不陡 */
const smoothstep = (t) => {
  const clamped = Math.min(1, Math.max(0, t))
  return clamped * clamped * (3 - 2 * clamped)
}

/**
 * 平地範圍：{ polygon } 一圈頂點（格子、水田與特色建築的佔地），或 { segment: { from, to, halfWidth } }
 * 有寬度的一段路。預設是整座島的 18 格、每段公路、14 處水田、6 個特色建築。
 */
export function flatZones({
  map = ISLAND_MAP,
  paddies = ISLAND_DECOR.paddies,
  landmarks = LANDMARKS,
} = {}) {
  return [
    ...map.tribes.flatMap(tribeHexes).map((center) => ({ polygon: hexVertices(map.hexRadius, center) })),
    ...[...paddies, ...landmarks].map((item) => ({ polygon: footprintCorners(item) })),
    ...roadSegments(map).map(({ from, to }) => ({
      segment: { from, to, halfWidth: map.roadHalfWidth },
    })),
  ]
}

/** 點離一個平地範圍多遠；在裡面是 0 */
function zoneGap(zone, point) {
  if (zone.polygon) {
    return footprintGapToPoint(zone.polygon, point)
  }
  const { from, to, halfWidth } = zone.segment
  return Math.max(0, distance(point, closestOnSegment(point, from, to)) - halfWidth)
}

/** 點離陸地的邊多遠（點在陸地裡時） */
function edgeGap(polygon, point) {
  return Math.min(
    ...polygon.map((from, i) =>
      distance(point, closestOnSegment(point, from, polygon[(i + 1) % polygon.length]))
    )
  )
}

/** 一個網格點收好平地後的高度 */
function gridHeight(meters, point, { zones, inland }) {
  if (meters <= 0 || !isInPolygon(point, inland)) {
    return 0
  }
  const nearestFlat = Math.min(...zones.map((zone) => zoneGap(zone, point)))
  const slope = smoothstep((nearestFlat - FLAT_MARGIN) / SLOPE_WIDTH)
  const coast = smoothstep(edgeGap(inland, point) / COAST_WIDTH)
  return meters * UNITS_PER_METER * slope * coast
}

/**
 * 收好平地的高度表。raw：{ x0, z0, step, cols, rows, meters（列 × cols ＋ 行） }；
 * zones 平地範圍（flatZones）；inland 陸地的一圈頂點，外面都是 0。
 * 回傳 { x0, z0, step, cols, rows, heights }，heights 是世界單位、順序同 meters。
 */
export function heightField(raw, { zones, inland }) {
  const { x0, z0, step, cols, rows, meters } = raw
  const heights = new Float32Array(cols * rows)
  for (let row = 0; row < rows; row += 1) {
    for (let col = 0; col < cols; col += 1) {
      const point = { x: x0 + col * step, z: z0 + row * step }
      heights[row * cols + col] = gridHeight(meters[row * cols + col], point, { zones, inland })
    }
  }
  return { x0, z0, step, cols, rows, heights }
}

/**
 * 某點有多高：那個點所在三角形的三個頂點內插。高度表外是 0。
 * 上三角形 (行, 列)(行＋1, 列)(行＋1, 列＋1)、下三角形 (行, 列)(行, 列＋1)(行＋1, 列＋1)。
 */
export function heightAt(field, { x, z }) {
  const { x0, z0, step, cols, rows, heights } = field
  const u = (x - x0) / step
  const w = (z - z0) / step
  const col = Math.min(Math.floor(u), cols - 2)
  const row = Math.min(Math.floor(w), rows - 2)
  if (u < 0 || w < 0 || u > cols - 1 || w > rows - 1) {
    return 0
  }
  const across = u - col
  const down = w - row
  const at = (dc, dr) => heights[(row + dr) * cols + col + dc]
  if (across >= down) {
    return at(0, 0) * (1 - across) + at(1, 0) * (across - down) + at(1, 1) * down
  }
  return at(0, 0) * (1 - down) + at(0, 1) * (down - across) + at(1, 1) * across
}

let islandField = null

/** 整座島收好平地的高度表（第一次用到才算，之後沿用同一份） */
export function islandHeightField() {
  islandField ??= heightField(TERRAIN_RAW, { zones: flatZones(), inland: ISLAND_DECOR.inland })
  return islandField
}
