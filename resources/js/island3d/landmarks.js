// 魚島 3D：部落的地標（#207）——清單、佔地的判斷、程式拼的造型、招牌什麼時候顯示。
// 全部是裝飾：走不進去的只是因為放在可走範圍外，格子裡的可以穿過。不依賴 three.js。
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
const landmark = (tribe, sign, kind, placement, [x, z, degrees], [width, depth]) => ({
  tribe,
  sign,
  kind,
  placement,
  x,
  z,
  heading: degrees * DEGREES_TO_RADIANS,
  width,
  depth,
  model: null,
})

/**
 * 島上的地標。每一項：tribe 所屬部落、sign 招牌文字、kind 造型種類、placement 放法
 * （'in-hex' 格子裡、'roadside' 公路旁）、x z 位置、heading 朝向、width depth 佔地大小、
 * model 模型檔路徑（null 就用程式拼的造型）。
 * 格子裡的照實際的羅盤方位靠邊放（中間留給之後的 3 戶人家，#201），正面朝格子中心；
 * 公路旁的放在實際位置旁的環島公路邊，正面朝路。位置依 OpenStreetMap 換算後寫死（見 #207）。
 */
export const LANDMARKS = [
  landmark('imowrod', '郵局', 'post-office', 'in-hex', [19.8, 46.3, -113], [3, 2.4]),
  landmark('imowrod', '衛生所', 'clinic', 'in-hex', [12.9, 52.0, -175], [3, 2.4]),
  landmark('imowrod', '警察局', 'police', 'in-hex', [4.4, 46.4, 114], [3, 2.4]),
  landmark('iratay', '文物館', 'museum', 'in-hex', [-24.2, 45.1, 135], [3, 2.4]),
  landmark('yayo', '農會', 'farmers', 'in-hex', [-81.5, -22.6, 0], [3, 2.4]),
  landmark('iraraley', '學校', 'school', 'in-hex', [-49.9, -88.4, -39], [3, 2.4]),
  landmark('iraraley', '路邊攤', 'stall', 'in-hex', [-55.2, -90.3, 0], [3, 2.4]),
  landmark('iranmeilek', '夜市', 'night-market', 'in-hex', [37.0, -35.7, -91], [3, 2.4]),
  landmark('iranmeilek', '便利商店', 'store', 'in-hex', [35.9, -31.7, -119], [3, 2.4]),
  landmark('iranmeilek', '警察局', 'police', 'in-hex', [32.9, -28.6, -149], [3, 2.4]),
  landmark('iranmeilek', '學校', 'school', 'in-hex', [24.1, -28.7, 148], [3, 2.4]),
  landmark('ivalino', '籃球場', 'court', 'in-hex', [27.5, 19.3, 90], [4, 2.6]),
  landmark('imowrod', '學校', 'school', 'roadside', [32.7, 62.2, -52], [4, 3]),
  landmark('iratay', '機場', 'airport', 'roadside', [-34.8, 43.7, 177], [10, 4]),
  landmark('yayo', '學校', 'school', 'roadside', [-62.8, 8.3, -22], [4, 3]),
  landmark('yayo', '便利商店', 'store', 'roadside', [-96.7, -41.6, 70], [3, 2.4]),
  landmark('yayo', '超市', 'supermarket', 'roadside', [-95.2, -46.3, 70], [4, 3]),
]

// ── 程式拼的造型 ──
// 零件用佔地的比例寫（0～1），所以不論佔地多大，零件都在佔地裡。
// 形狀：box 盒子、roof 屋頂（四角錐）、plane 貼地的平面、post 柱子、ring 平放的圓框。
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
    // 兩個籃框：柱子、籃板、朝場內伸出去的橘色框
    ...[-1, 1].flatMap((side) => [
      { shape: 'post', size: [POST_WIDTH, 2, POST_WIDTH], at: [side * 0.45, 0], color: '#6b7280' },
      { shape: 'box', size: [0.03, 0.5, 0.3], at: [side * 0.42, 0], base: 1.6, color: '#ffffff' },
      {
        shape: 'ring',
        size: [0.1, 0.06, 0.16],
        at: [side * 0.35, 0],
        base: 1.6,
        color: '#e8590c',
      },
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
