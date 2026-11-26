// 魚島 3D：部落的地標（#207、#212）——清單、佔地的判斷、程式拼的造型、招牌什麼時候顯示。
// 全部是裝飾，放在可走範圍外（公路邊、格子外），走不進去。不依賴 three.js。
import { closestOnSegment } from './hexBounds'

// ── 佔地：以位置為中心、依朝向旋轉的長方形 ──

const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const edgesOf = (polygon) => polygon.map((from, i) => [from, polygon[(i + 1) % polygon.length]])

/**
 * 佔地的四個角。item: { x, z 中心, width 寬, depth 深, heading 朝向 }；
 * heading 和 three.js 的 rotation.y 一樣：0 是正面朝 +z，轉四分之一圈正面朝 +x。
 */
export function footprintCorners({ x, z, width, depth, heading }) {
  const cos = Math.cos(heading)
  const sin = Math.sin(heading)
  return [
    [-1, -1],
    [1, -1],
    [1, 1],
    [-1, 1],
  ].map(([side, front]) => {
    const across = (side * width) / 2
    const forward = (front * depth) / 2
    return { x: x + across * cos + forward * sin, z: z - across * sin + forward * cos }
  })
}

/** 點是否在多邊形裡（射線法，凹的多邊形也可以） */
function isInPolygon(point, polygon) {
  return edgesOf(polygon).reduce((inside, [a, b]) => {
    const crosses =
      a.z > point.z !== b.z > point.z &&
      point.x < ((b.x - a.x) * (point.z - a.z)) / (b.z - a.z) + a.x
    return crosses ? !inside : inside
  }, false)
}

/** 兩條線段有沒有相交 */
function segmentsCross(a, b, c, d) {
  const side = (p, q, r) => Math.sign((q.x - p.x) * (r.z - p.z) - (q.z - p.z) * (r.x - p.x))
  return side(a, b, c) !== side(a, b, d) && side(c, d, a) !== side(c, d, b)
}

/** 兩條線段最近的距離；相交是 0 */
function segmentGap(a, b, c, d) {
  if (segmentsCross(a, b, c, d)) {
    return 0
  }
  return Math.min(
    distance(a, closestOnSegment(a, c, d)),
    distance(b, closestOnSegment(b, c, d)),
    distance(c, closestOnSegment(c, a, b)),
    distance(d, closestOnSegment(d, a, b))
  )
}

/** 佔地（四個角）離線段 from → to 最近的距離；線段碰到或穿過佔地是 0 */
export function footprintGapToSegment(corners, from, to) {
  if (isInPolygon(from, corners)) {
    return 0
  }
  return Math.min(...edgesOf(corners).map(([a, b]) => segmentGap(a, b, from, to)))
}

/** 佔地（四個角）離一個點最近的距離；點在佔地裡是 0 */
export function footprintGapToPoint(corners, point) {
  if (isInPolygon(point, corners)) {
    return 0
  }
  return Math.min(
    ...edgesOf(corners).map(([a, b]) => distance(point, closestOnSegment(point, a, b)))
  )
}

// 兩塊區域在某個方向上交疊不到這麼多，就當成只是碰到（共用邊或角），不算重疊
const TOUCH_TOLERANCE = 1e-9

/** 一圈頂點投影到 axis 方向上的範圍 */
function projectOnto(polygon, axis) {
  const along = polygon.map((point) => point.x * axis.x + point.z * axis.z)
  return { min: Math.min(...along), max: Math.max(...along) }
}

/** 每條邊的法線方向（單位向量） */
const edgeNormals = (polygon) =>
  edgesOf(polygon).map(([from, to]) => {
    const length = distance(from, to)
    return { x: -(to.z - from.z) / length, z: (to.x - from.x) / length }
  })

/**
 * 兩塊凸的區域（各是一圈頂點，例如兩個佔地、佔地與六角格）有沒有重疊。
 * 只共用一條邊或一個角不算重疊。做法是分離軸：只要有一條邊的法線方向上兩者的投影沒有交疊，
 * 就是分開的。
 */
export function footprintsOverlap(a, b) {
  return [...edgeNormals(a), ...edgeNormals(b)].every((axis) => {
    const spanA = projectOnto(a, axis)
    const spanB = projectOnto(b, axis)
    return Math.min(spanA.max, spanB.max) - Math.max(spanA.min, spanB.min) > TOUCH_TOLERANCE
  })
}

/** 整個佔地是否都在多邊形 polygon 裡（凹的多邊形也可以）：四個角都在裡面，而且四條邊都不穿出去 */
export function footprintInside(corners, polygon) {
  const leaves = edgesOf(corners).some(([p, q]) =>
    edgesOf(polygon).some(([r, s]) => segmentsCross(p, q, r, s))
  )
  return !leaves && corners.every((corner) => isInPolygon(corner, polygon))
}

// ── 地標清單 ──

const DEGREES_TO_RADIANS = Math.PI / 180

/** 清單的一項：[x, z, 朝向（度）]、[寬, 深]；模型檔先不填 */
const landmark = (tribe, sign, kind, [x, z, degrees], [width, depth]) => ({
  tribe,
  sign,
  kind,
  x,
  z,
  heading: degrees * DEGREES_TO_RADIANS,
  width,
  depth,
  model: null,
})

// 平房類的佔地：約 4～5 個人物寬（人物高 1.7）
const HOUSE_FOOTPRINT = [8, 6]

/**
 * 島上的地標：每個部落 1 個特色建築（#212）。每一項：tribe 所屬部落、sign 招牌文字、kind 造型種類、
 * x z 位置、heading 朝向、width depth 佔地大小、model 模型檔路徑（null 就用程式拼的造型）。
 * 都放在公路邊、格子外，正面朝路；位置是 chung 在設計稿上標的（見 #212），寫死在這裡。
 */
export const LANDMARKS = [
  // 紅頭主格與第 2 格之間
  landmark('imowrod', '郵局', 'post-office', [31.9, 72.5, -54], HOUSE_FOOTPRINT),
  // 椰油主格北邊
  landmark('yayo', '農會', 'farmers', [-131.5, -41.9, -52], HOUSE_FOOTPRINT),
  // 東清主格與第 3 格之間
  landmark('iranmeilek', '便利商店', 'store', [32.9, -39.9, 89], HOUSE_FOOTPRINT),
  // 朗島主格與第 3 格之間
  landmark('iraraley', '路邊攤', 'stall', [-72.6, -133.3, 19], [6, 3]),
  // 野銀主格西邊、橫貫公路旁
  landmark('ivalino', '籃球場', 'court', [39.7, 18.0, -141], [12, 7]),
  // 椰油往漁人的公路旁、靠海那一側
  landmark('iratay', '機場', 'airport', [-98.0, 26.0, 158], [20, 8]),
]

// ── 程式拼的造型 ──
// 零件用佔地的比例寫（0～1），所以不論佔地多大，零件都在佔地裡。
// 形狀：box 盒子、roof 屋頂（四角錐）、plane 貼地的平面、post 柱子、ring 平放的圓框。
// 每個零件：shape 形狀、[寬, 高, 深]（寬與深是佔地的比例、高是實際高度）、[x, z]（離佔地中心的比例）、顏色。
// 高度和人物（1.7）合比例（#212）：平房約兩個人高。

const WALL_HEIGHT = 2.4
const ROOF_HEIGHT = 1
const FLAT = 0.06
const POST_WIDTH = 0.04
// 路邊攤：柱子撐著一片棚子，棚頂比人高一點
const STALL_POST_HEIGHT = 2
const STALL_CANOPY_THICKNESS = 0.2
// 籃球場：籃框離地的高度、籃板、柱子
const RIM_HEIGHT = 3
const RIM_THICKNESS = 0.06
const BACKBOARD_HEIGHT = 0.9
const HOOP_POST_HEIGHT = 3.5
// 機場：航站與塔台
const TERMINAL_HEIGHT = 2.6
const TOWER_HEIGHT = 4.8

/** 房子：一個方盒加屋頂 */
const house = (wall, roof) => [
  { shape: 'box', size: [0.9, WALL_HEIGHT, 0.8], at: [0, 0], color: wall },
  { shape: 'roof', size: [1, ROOF_HEIGHT, 0.9], at: [0, 0], base: WALL_HEIGHT, color: roof },
]

/** 小攤：四根柱子加一片棚子，中心在佔地寬度的 at 比例處 */
const stall = (at, canopy) => [
  ...[-1, 1].flatMap((side) =>
    [-1, 1].map((front) => ({
      shape: 'post',
      size: [POST_WIDTH, STALL_POST_HEIGHT, POST_WIDTH],
      at: [at + side * 0.1, front * 0.3],
      color: '#8a6a4a',
    }))
  ),
  {
    shape: 'box',
    size: [0.28, STALL_CANOPY_THICKNESS, 0.8],
    at: [at, 0],
    base: STALL_POST_HEIGHT,
    color: canopy,
  },
]

/** 籃球架（side 是 -1 左、1 右）：柱子、籃板、朝場內伸出去的橘色框 */
const hoop = (side) => [
  {
    shape: 'post',
    size: [POST_WIDTH, HOOP_POST_HEIGHT, POST_WIDTH],
    at: [side * 0.45, 0],
    color: '#6b7280',
  },
  {
    shape: 'box',
    size: [0.03, BACKBOARD_HEIGHT, 0.3],
    at: [side * 0.42, 0],
    base: RIM_HEIGHT - RIM_THICKNESS / 2,
    color: '#ffffff',
  },
  {
    shape: 'ring',
    size: [0.1, RIM_THICKNESS, 0.16],
    at: [side * 0.35, 0],
    base: RIM_HEIGHT - RIM_THICKNESS / 2,
    color: '#e8590c',
  },
]

const SHAPES = {
  'post-office': house('#f3f1e7', '#3f8f5f'),
  farmers: house('#f1ead2', '#7a9a3a'),
  store: house('#f7f7f7', '#f0a030'),
  airport: [
    { shape: 'plane', size: [1, FLAT, 0.45], at: [0, -0.25], color: '#5c6166' },
    { shape: 'plane', size: [0.9, FLAT, 0.04], at: [0, -0.25], base: FLAT, color: '#ffffff' },
    { shape: 'box', size: [0.3, TERMINAL_HEIGHT, 0.3], at: [0.3, 0.3], color: '#e6e9ee' },
    { shape: 'box', size: [0.08, TOWER_HEIGHT, 0.2], at: [0.1, 0.3], color: '#cfd5dd' },
  ],
  court: [
    { shape: 'plane', size: [1, FLAT, 1], at: [0, 0], color: '#c9764a' },
    { shape: 'plane', size: [0.02, FLAT, 0.9], at: [0, 0], base: FLAT, color: '#ffffff' },
    ...hoop(-1),
    ...hoop(1),
  ],
  stall: [...stall(-0.25, '#e07a3f'), ...stall(0.25, '#3f8fd0')],
}

/**
 * kind 這種造型在寬 width、深 depth 的佔地上的零件：
 * [{ shape, width, height, depth 零件的大小, x, z 離佔地中心, y 零件中心的高度, color }]
 */
export function shapeParts(kind, width, depth) {
  const parts = SHAPES[kind]
  if (!parts) {
    throw new Error(`魚島 3D：沒有「${kind}」這種地標造型`)
  }
  return parts.map(({ shape, size, at, base = 0, color }) => ({
    shape,
    width: size[0] * width,
    height: size[1],
    depth: size[2] * depth,
    x: at[0] * width,
    z: at[1] * depth,
    y: base + size[1] / 2,
    color,
  }))
}

// ── 招牌 ──

// 鏡頭拉遠超過這個程度（zoom：0 最近、1 最遠）招牌就隱藏，只留部落標示
const SIGN_HIDE_ZOOM = 0.5

export function signsVisible(zoom) {
  return zoom <= SIGN_HIDE_ZOOM
}
