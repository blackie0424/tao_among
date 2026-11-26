// 魚島 3D：部落的地標（#207）——清單、佔地的判斷、程式拼的造型、招牌什麼時候顯示。
// 全部是裝飾：走不進去的只是因為放在可走範圍外，格子裡的可以穿過。不依賴 three.js。
import { closestOnSegment } from './hexBounds'
import { ISLAND_MAP, besideRoad } from './islandMap'

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

/** 兩塊凸的區域（各是一圈頂點，例如兩個佔地、佔地與六角格）有沒有重疊 */
export function footprintsOverlap(a, b) {
  const edgesCross = edgesOf(a).some(([p, q]) =>
    edgesOf(b).some(([r, s]) => segmentsCross(p, q, r, s))
  )
  return edgesCross || isInPolygon(a[0], b) || isInPolygon(b[0], a)
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
const center = (code) => ISLAND_MAP.tribes.find((tribe) => tribe.code === code).center

// 格子裡的地標靠邊放：佔地最靠近格子中心的那一邊離中心這麼遠，中間留給之後的 3 戶人家（#201）
const INNER_CLEARANCE = 7.2
// 一般房子的佔地
const HOUSE = { width: 3, depth: 2.4 }

/**
 * 格子裡的地標：在 tribe 部落中心的 degrees 方向（0 是東、90 是南），正面朝格子中心。
 * extra 是比一般位置再往外多少。
 */
function inHex(tribe, sign, kind, degrees, { width, depth } = HOUSE, extra = 0) {
  const angle = degrees * DEGREES_TO_RADIANS
  const away = INNER_CLEARANCE + depth / 2 + extra
  const { x, z } = center(tribe)
  return {
    tribe,
    sign,
    kind,
    placement: 'in-hex',
    x: x + Math.cos(angle) * away,
    z: z + Math.sin(angle) * away,
    width,
    depth,
    heading: Math.atan2(-Math.cos(angle), -Math.sin(angle)),
    model: null,
  }
}

/** 公路旁的地標：沿 from → to 那一段離 from 走 along，放在 side 那一側（見 besideRoad） */
function roadside(tribe, sign, kind, [from, to, along, side], { width, depth } = HOUSE) {
  return {
    tribe,
    sign,
    kind,
    placement: 'roadside',
    ...besideRoad(from, to, along, side, width, depth),
    model: null,
  }
}

// 環島公路在紅頭往島南端那一段的另一端（islandMap.js 的南端折點）
const SOUTH_OF_IMOWROD = { x: -30, z: 76 }

/**
 * 島上的地標。每一項：tribe 所屬部落、sign 招牌文字、kind 造型種類、placement 放法
 * （'in-hex' 格子裡、'roadside' 公路旁）、x z 位置、heading 朝向、width depth 佔地大小、
 * model 模型檔路徑（null 就用程式拼的造型）。方位依 OpenStreetMap 查到的相對位置（見 #207）。
 */
export const LANDMARKS = [
  inHex('imowrod', '郵局', 'post-office', 20),
  inHex('imowrod', '衛生所', 'clinic', 50, HOUSE, 0.6),
  inHex('imowrod', '警察局', 'police', 180),
  roadside('imowrod', '學校', 'school', [center('imowrod'), SOUTH_OF_IMOWROD, 24, 'inland'], {
    width: 4,
    depth: 3,
  }),
  inHex('iratay', '文物館', 'museum', 135),
  roadside('iratay', '機場', 'airport', [center('iratay'), center('yayo'), 21, 'seaward'], {
    width: 10,
    depth: 4,
  }),
  inHex('yayo', '農會', 'farmers', -62),
  roadside('yayo', '學校', 'school', [center('yayo'), center('iratay'), 18, 'inland'], {
    width: 4,
    depth: 3,
  }),
  roadside('yayo', '便利商店', 'store', [center('yayo'), center('iraraley'), 17, 'seaward']),
  roadside('yayo', '超市', 'supermarket', [center('yayo'), center('iraraley'), 23, 'seaward'], {
    width: 4,
    depth: 3,
  }),
  inHex('iraraley', '學校', 'school', -45),
  inHex('iraraley', '路邊攤', 'stall', -90),
  inHex('iranmeilek', '夜市', 'night-market', -20),
  inHex('iranmeilek', '便利商店', 'store', 10),
  inHex('iranmeilek', '警察局', 'police', 42),
  inHex('iranmeilek', '學校', 'school', 126),
  inHex('ivalino', '籃球場', 'court', 200, { width: 4, depth: 2.6 }),
]

// ── 程式拼的造型 ──
// 零件用佔地的比例寫（0～1），所以不論佔地多大，零件都在佔地裡。
// 每個零件：shape 形狀、[寬, 高, 深]（寬與深是佔地的比例、高是實際高度）、[x, z]（離佔地中心的比例）、顏色。

const WALL_HEIGHT = 1.6
const ROOF_HEIGHT = 0.9
const FLAT = 0.06
const POST_WIDTH = 0.08

/** 房子：一個方盒加屋頂 */
const house = (wall, roof, wallHeight = WALL_HEIGHT) => [
  { shape: 'box', size: [0.9, wallHeight, 0.8], at: [0, 0], color: wall },
  { shape: 'roof', size: [1, ROOF_HEIGHT, 0.9], at: [0, 0], base: wallHeight, color: roof },
]

/** 小攤：四根柱子加一片棚子，中心在佔地寬度的 at 比例處 */
const stall = (at, canopy) => [
  ...[-1, 1].flatMap((side) =>
    [-1, 1].map((front) => ({
      shape: 'post',
      size: [POST_WIDTH, 1.4, POST_WIDTH],
      at: [at + side * 0.1, front * 0.3],
      color: '#8a6a4a',
    }))
  ),
  { shape: 'box', size: [0.28, 0.12, 0.8], at: [at, 0], base: 1.4, color: canopy },
]

const SHAPES = {
  'post-office': house('#f3f1e7', '#3f8f5f'),
  clinic: house('#ffffff', '#d9534f'),
  police: house('#e8edf5', '#2f4f8f'),
  school: [
    ...house('#f6e7c8', '#b5653c', 1.9),
    { shape: 'post', size: [POST_WIDTH, 2.6, POST_WIDTH], at: [0.42, 0.42], color: '#9aa0a6' },
  ],
  museum: house('#d8c7a8', '#6b4a32', 1.3),
  farmers: house('#f1ead2', '#7a9a3a'),
  store: house('#f7f7f7', '#f0a030', 1.3),
  supermarket: house('#f7f7f7', '#3a8fb7', 1.4),
  airport: [
    { shape: 'plane', size: [1, FLAT, 0.45], at: [0, -0.25], color: '#5c6166' },
    { shape: 'plane', size: [0.9, FLAT, 0.04], at: [0, -0.25], base: FLAT, color: '#ffffff' },
    { shape: 'box', size: [0.3, 1.4, 0.3], at: [0.3, 0.3], color: '#e6e9ee' },
    { shape: 'box', size: [0.08, 2.4, 0.2], at: [0.1, 0.3], color: '#cfd5dd' },
  ],
  court: [
    { shape: 'plane', size: [1, FLAT, 1], at: [0, 0], color: '#c9764a' },
    { shape: 'plane', size: [0.02, FLAT, 0.9], at: [0, 0], base: FLAT, color: '#ffffff' },
    ...[-1, 1].flatMap((side) => [
      { shape: 'post', size: [POST_WIDTH, 2, POST_WIDTH], at: [side * 0.45, 0], color: '#6b7280' },
      { shape: 'box', size: [0.06, 0.5, 0.3], at: [side * 0.42, 0], base: 1.6, color: '#ffffff' },
    ]),
  ],
  stall: [...stall(-0.25, '#e07a3f'), ...stall(0.25, '#3f8fd0')],
  'night-market': [...stall(-0.33, '#d94f4f'), ...stall(0, '#f0c040'), ...stall(0.33, '#4fa36a')],
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
