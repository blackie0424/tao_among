// 魚島 3D：島中間的地形（#223）——由實際地形的原始高度，收出部落格子、公路、水田、特色建築周圍的平地，
// 再提供「某點有多高」、地形的顏色、溪流的路線。繪圖、點擊判定都讀這一份。不依賴 three.js。
// 高度表每格固定沿 (行, 列) → (行＋1, 列＋1) 的對角線切成兩個三角形；「某點有多高」用那個點所在三角形的
// 三個頂點內插，跟畫出來的網格是同一組三角形，所以查到的高度就是畫出來的表面。
import { closestOnSegment, hexVertices } from './hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, ISLAND_SCALE, roadSegments, tribeHexes } from './islandMap'
import { LANDMARKS, footprintCorners, footprintGapToPoint, isInPolygon } from './landmarks'
import { RIVERS, TERRAIN_RAW } from './terrainData'

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
    ...map.tribes
      .flatMap(tribeHexes)
      .map((center) => ({ polygon: hexVertices(map.hexRadius, center) })),
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

// 離平地範圍這麼遠以外，收坡係數都是 1，不必算精確的距離
const SLOPE_REACH = FLAT_MARGIN + SLOPE_WIDTH

/** 平地範圍的外框（x、z 的最小最大值），往外再擴 SLOPE_REACH */
function reachBox(zone) {
  const points = zone.polygon ?? [zone.segment.from, zone.segment.to]
  const pad = SLOPE_REACH + (zone.segment?.halfWidth ?? 0)
  const xs = points.map((point) => point.x)
  const zs = points.map((point) => point.z)
  return {
    minX: Math.min(...xs) - pad,
    maxX: Math.max(...xs) + pad,
    minZ: Math.min(...zs) - pad,
    maxZ: Math.max(...zs) + pad,
  }
}

const insideBox = (box, { x, z }) =>
  x >= box.minX && x <= box.maxX && z >= box.minZ && z <= box.maxZ

/** 點離最近的平地範圍多遠；外框沒碰到的範圍一定在 SLOPE_REACH 以外，跳過 */
function nearestFlatGap(boxedZones, point) {
  return boxedZones.reduce(
    (nearest, { zone, box }) =>
      insideBox(box, point) ? Math.min(nearest, zoneGap(zone, point)) : nearest,
    SLOPE_REACH
  )
}

/** 一個網格點收好平地後的高度 */
function gridHeight(meters, point, { boxedZones, inland }) {
  if (meters <= 0 || !isInPolygon(point, inland)) {
    return 0
  }
  const slope = smoothstep((nearestFlatGap(boxedZones, point) - FLAT_MARGIN) / SLOPE_WIDTH)
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
  const boxedZones = zones.map((zone) => ({ zone, box: reachBox(zone) }))
  for (let row = 0; row < rows; row += 1) {
    for (let col = 0; col < cols; col += 1) {
      const point = { x: x0 + col * step, z: z0 + row * step }
      heights[row * cols + col] = gridHeight(meters[row * cols + col], point, {
        boxedZones,
        inland,
      })
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

// ── 顏色：越高越深 ──

// 高度 0 是陸地的綠（terrain.js 的 COLORS.land），最高處是深綠
const TERRAIN_COLORS = { low: [0x5f, 0xae, 0x73], high: [0x1f, 0x5e, 0x33] }
// 最高處：島上最高的原始高度（紅頭山一帶約 535 公尺）換算的高度
const TOP_METERS = 535
const TERRAIN_TOP = TOP_METERS * UNITS_PER_METER

/** 地形在這個高度的顏色（#rrggbb）：照高度在兩色之間等比例漸層，最高處以上都是深綠 */
export function terrainColor(height) {
  const t = Math.min(1, Math.max(0, height / TERRAIN_TOP))
  return TERRAIN_COLORS.low
    .map((low, i) => Math.round(low + (TERRAIN_COLORS.high[i] - low) * t))
    .reduce((hex, channel) => hex + channel.toString(16).padStart(2, '0'), '#')
}

// ── 溪流 ──

// 溪的折線每這麼長補一點，高度跟著地形，溪才平順
const RIVER_STEP = 0.8
// 溪的半寬（寬 1.6）
export const RIVER_HALF_WIDTH = 0.8
// 中線離格子、水田、特色建築這麼近就不畫（往外 1 單位，再加半寬，溪的兩邊也不會碰到）
const RIVER_CLEARANCE = 1 + RIVER_HALF_WIDTH
// 相鄰兩點超過這麼遠是資料跳點，那段不畫
const RIVER_MAX_GAP = 8

/** 原始高度表在這一點的公尺數（取最近的網格點）；表外是 0 */
export function rawMetersAt({ x, z }, raw = TERRAIN_RAW) {
  const col = Math.round((x - raw.x0) / raw.step)
  const row = Math.round((z - raw.z0) / raw.step)
  if (col < 0 || row < 0 || col >= raw.cols || row >= raw.rows) {
    return 0
  }
  return raw.meters[row * raw.cols + col]
}

const samePoint = (a, b) => a.x === b.x && a.z === b.z
const toPoints = (points) => points.map(([x, z]) => ({ x, z }))

/** 同名的段頭尾相接成一條：每次找一段的頭或尾跟目前這條的頭或尾是同一點，接上去 */
function joinSegments(segments) {
  const rest = segments.map(toPoints)
  let line = rest.shift()
  while (rest.length) {
    const i = rest.findIndex((segment) =>
      [segment[0], segment[segment.length - 1]].some(
        (end) => samePoint(end, line[0]) || samePoint(end, line[line.length - 1])
      )
    )
    const [segment] = rest.splice(i, 1)
    const forward =
      samePoint(segment[0], line[line.length - 1]) ||
      samePoint(segment[segment.length - 1], line[0])
    const piece = forward ? segment : [...segment].reverse()
    line = samePoint(piece[0], line[line.length - 1])
      ? [...line, ...piece.slice(1)]
      : [...piece.slice(0, -1), ...line]
  }
  return line
}

/** 11 條溪：[{ name, points 由上游到下游 }]；同名的段接成一條，原始高度高的那一端當上游 */
export function riverLines(rivers = RIVERS) {
  const names = [...new Set(rivers.map((river) => river.name))]
  return names.map((name) => {
    const line = joinSegments(
      rivers.filter((river) => river.name === name).map((river) => river.points)
    )
    const downhill = rawMetersAt(line[0]) >= rawMetersAt(line[line.length - 1])
    return { name, points: downhill ? line : [...line].reverse() }
  })
}

/** 折線每 RIVER_STEP 補一點；相鄰兩點超過 RIVER_MAX_GAP 的地方插一個 null（斷開） */
function densify(points) {
  const dense = [points[0]]
  points.slice(1).forEach((to, i) => {
    const from = points[i]
    const length = distance(from, to)
    if (length > RIVER_MAX_GAP) {
      dense.push(null, to)
      return
    }
    const pieces = Math.max(1, Math.ceil(length / RIVER_STEP))
    for (let k = 1; k <= pieces; k += 1) {
      dense.push({
        x: from.x + ((to.x - from.x) * k) / pieces,
        z: from.z + ((to.z - from.z) * k) / pieces,
      })
    }
  })
  return dense
}

/**
 * 一條溪要畫的幾段：[[{ x, z, height 那一點的地形高度 }]]。
 * 中線離 cut 裡任一圈頂點（格子、水田、特色建築）不到 RIVER_CLEARANCE、在 coast 外、或資料跳點的地方斷開。
 */
export function riverRuns(points, { field, cut, coast }) {
  const keep = (point) =>
    point !== null &&
    isInPolygon(point, coast) &&
    cut.every((polygon) => footprintGapToPoint(polygon, point) > RIVER_CLEARANCE)
  const runs = []
  let run = []
  densify(points).forEach((point) => {
    if (keep(point)) {
      run.push({ ...point, height: heightAt(field, point) })
      return
    }
    if (run.length > 1) {
      runs.push(run)
    }
    run = []
  })
  if (run.length > 1) {
    runs.push(run)
  }
  return runs
}

/** 整座島要畫的溪：11 條溪各自的幾段，避開 18 格、14 處水田、6 個特色建築，海岸線外不畫 */
export function islandRiverRuns(field = islandHeightField()) {
  const cut = flatZones()
    .filter((zone) => zone.polygon)
    .map((zone) => zone.polygon)
  return riverLines().flatMap((line) =>
    riverRuns(line.points, { field, cut, coast: ISLAND_DECOR.coast })
  )
}
