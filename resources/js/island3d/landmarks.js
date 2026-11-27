// 魚島 3D：部落的地標（#207、#212）——清單、佔地的判斷、程式拼的造型、招牌什麼時候顯示。
// 全部是裝飾，放在可走範圍外（公路邊、格子外），走不進去。不依賴 three.js。
import { closestOnSegment } from './hexBounds'
import { HOUSEHOLD_SHAPES } from './householdShapes'
import { part, scaledTo } from './shapeKit'

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
// 每種造型照 chung 選的樣品（#211）用公尺寫在它的設計佔地上，換算成佔地的比例（0～1），
// 所以不論佔地多大，零件都在佔地裡。零件都不旋轉（和佔地的邊平行），寬、高、深就是它佔的範圍。
// 形狀：box 盒子、roof 屋頂（四角錐）、plane 貼地的平面、post 柱子、ring 平放的圓框、
// cylinder 圓柱（axis 是軸朝哪一邊：x、y、z，預設 y）、dome 半球（圓頂朝上）、
// gable 兩面坡屋頂（屋脊沿著寬）、umbrella／umbrella-alt 陽傘的兩組瓣（交錯，配兩種顏色）。
// 每個零件：shape、size [寬, 高, 深]、at [x, z]（離佔地中心）、base 底部離地多高、color；
// 可以再加 axis（圓柱）、opacity（半透明，玻璃用）。
// 高度和人物（1.7）合比例：平房最高處約人物的 2 倍。

const FLAT = 0.05
const LINE = 0.02
const GLASS = { color: '#bfe8f5', opacity: 0.55 }
const WHEEL_COLOR = '#333333'
const WOOD = '#9a6b3f'
const WOOD_DARK = '#6e4a2a'
const RED = '#d9363e'
const WHITE = '#ffffff'
const PLASTIC_BLUE = '#2f6fd0'

const glass = (size, at, base) =>
  part('box', size, at, base, GLASS.color, { opacity: GLASS.opacity })
const wheel = (axis, diameter, thickness, at) => {
  const size = axis === 'x' ? [thickness, diameter, diameter] : [diameter, diameter, thickness]
  return part('cylinder', size, at, 0, WHEEL_COLOR, { axis })
}

/** 塑膠椅：椅面加四隻腳 */
const STOOL_SEAT_HEIGHT = 0.42
const stool = ([x, z], color) => [
  part('box', [0.4, 0.06, 0.4], [x, z], STOOL_SEAT_HEIGHT, color),
  ...[
    [-1, -1],
    [1, -1],
    [1, 1],
    [-1, 1],
  ].map(([sx, sz]) =>
    part('box', [0.05, STOOL_SEAT_HEIGHT, 0.05], [x + sx * 0.16, z + sz * 0.16], 0, color)
  ),
]

// 朗島路邊攤（6 × 3）：左邊兩輪木推車（玻璃櫃、紅白條紋棚子），右邊大陽傘、折疊桌、塑膠椅
const CART_X = -1.5
const CART_Z = -0.3
const CANOPY_HEIGHT = 2.3
const CANOPY_STRIPES = 8
const CANOPY_WIDTH = 2.5
const UMBRELLA_X = 1.5
const STALL = [
  part('box', [2.2, 0.8, 1.0], [CART_X, CART_Z], 0.35, WOOD),
  part('box', [2.3, 0.08, 1.1], [CART_X, CART_Z], 1.15, WOOD_DARK),
  glass([1.8, 0.55, 0.8], [CART_X, CART_Z], 1.23),
  wheel('x', 0.7, 0.08, [CART_X - 1.15, CART_Z + 0.15]),
  wheel('x', 0.7, 0.08, [CART_X + 1.15, CART_Z + 0.15]),
  ...[-1, 1].flatMap((sx) =>
    [-1, 1].map((sz) =>
      part(
        'post',
        [0.06, CANOPY_HEIGHT, 0.06],
        [CART_X + sx * 1.05, CART_Z + sz * 0.45],
        0,
        '#cccccc'
      )
    )
  ),
  ...Array.from({ length: CANOPY_STRIPES }, (_, i) =>
    part(
      'box',
      [CANOPY_WIDTH / CANOPY_STRIPES, 0.06, 1.5],
      [CART_X - CANOPY_WIDTH / 2 + ((i + 0.5) * CANOPY_WIDTH) / CANOPY_STRIPES, CART_Z],
      CANOPY_HEIGHT,
      i % 2 ? WHITE : RED
    )
  ),
  // 保麗龍箱
  part('box', [0.6, 0.4, 0.4], [-2.6, 0.9], 0, WHITE),
  part('box', [0.6, 0.4, 0.4], [-2.6, 0.9], 0.4, WHITE),
  // 大陽傘
  part('cylinder', [0.08, 1.9, 0.08], [UMBRELLA_X, 0], 0, '#dddddd'),
  part('umbrella', [2.6, 0.7, 2.6], [UMBRELLA_X, 0], 1.85, RED),
  part('umbrella-alt', [2.6, 0.7, 2.6], [UMBRELLA_X, 0], 1.85, WHITE),
  part('box', [0.9, 0.05, 0.9], [UMBRELLA_X, 0], 0.7, '#f2f2f2'),
  ...stool([0.7, 0.9], RED),
  ...stool([2.3, 0.9], RED),
  ...stool([UMBRELLA_X, -1.0], PLASTIC_BLUE),
]

// 郵筒：底座、圓柱身、半球頂、黑色投信口
const MAILBOX_BODY = 1.0
const mailbox = ([x, z], color) => [
  part('box', [0.72, 0.1, 0.72], [x, z], 0, '#555555'),
  part('cylinder', [0.64, MAILBOX_BODY, 0.64], [x, z], 0.1, color),
  part('dome', [0.64, 0.32, 0.64], [x, z], 0.1 + MAILBOX_BODY, color),
  part('box', [0.3, 0.05, 0.05], [x, z + 0.32], 0.85, '#222222'),
]

// 紅頭郵局（8 × 6）：白色平房、綠色屋簷帶與雨遮、玻璃門窗，門前紅綠郵筒，旁邊旗桿
const POST_GREEN = '#1f8a4c'
const POST_WALL = 2.5
const POST_OFFICE = [
  part('box', [6.6, POST_WALL, 3.6], [0, -1.0], 0, '#f5f3ea'),
  part('box', [7.0, 0.5, 4.0], [0, -1.0], POST_WALL, POST_GREEN),
  part('box', [6.6, 0.15, 3.6], [0, -1.0], POST_WALL + 0.5, '#e8e5da'),
  part('box', [3.0, 0.12, 1.2], [0, 1.4], 2.2, POST_GREEN),
  glass([2.4, 2.0, 0.08], [0, 0.84], 0),
  part('box', [0.08, 2.0, 0.1], [0, 0.86], 0, '#888888'),
  glass([1.3, 1.1, 0.08], [-2.4, 0.84], 0.9),
  glass([1.3, 1.1, 0.08], [2.4, 0.84], 0.9),
  ...mailbox([2.2, 2.3], RED),
  ...mailbox([3.1, 2.3], POST_GREEN),
  part('post', [0.08, 3.7, 0.08], [-3.3, 2.3], 0, '#cccccc'),
  part('box', [0.9, 0.55, 0.03], [-2.8, 2.3], 3.1, WHITE),
  part('box', [0.3, 0.3, 0.04], [-2.95, 2.3], 3.32, '#1f5fbf'),
]

// 麻袋：躺著的圓柱
const sack = (at, base) => part('cylinder', [0.6, 0.4, 0.4], at, base, '#e9dcb5', { axis: 'x' })
const SHUTTER_SLATS = 8

// 椰油農會（8 × 6）：淺黃倉庫、綠色兩面坡屋頂、鐵捲門、門前麻袋堆、載麻袋的白色小貨車
const WAREHOUSE_WALL = 2.5
const TRUCK_Z = 2.2
const FARMERS = [
  part('box', [7, WAREHOUSE_WALL, 4.0], [0, -0.9], 0, '#efe3b8'),
  part('gable', [7.4, 1.0, 4.2], [0, -0.85], WAREHOUSE_WALL, '#3f8a3a'),
  part('box', [2.6, 2.1, 0.08], [-1.6, 1.14], 0, '#9aa3ab'),
  ...Array.from({ length: SHUTTER_SLATS }, (_, i) =>
    part('box', [2.6, 0.04, 0.1], [-1.6, 1.2], 0.2 + i * 0.24, '#7d868f')
  ),
  glass([1.6, 1.8, 0.08], [1.9, 1.14], 0),
  sack([0.4, 1.7], 0),
  sack([1.0, 1.7], 0),
  sack([1.6, 1.7], 0),
  sack([0.7, 1.7], 0.4),
  sack([1.3, 1.7], 0.4),
  // 小貨車：車斗朝 +x、車頭朝 -x
  part('box', [1.8, 0.45, 1.1], [-1.0, TRUCK_Z], 0.35, '#d9d9d9'),
  part('box', [1.0, 1.0, 1.1], [-2.4, TRUCK_Z], 0.35, WHITE),
  glass([0.05, 0.4, 1.0], [-2.92, TRUCK_Z], 0.85),
  ...[-2.4, -0.5].flatMap((x) =>
    [-1, 1].map((side) => wheel('z', 0.5, 0.08, [x, TRUCK_Z + side * 0.58]))
  ),
  sack([-1.3, TRUCK_Z], 0.8),
  sack([-0.7, TRUCK_Z], 0.8),
]

// 漁人機場（20 × 8）：後面是跑道（中線、兩端斑馬線）與小飛機，前面是航站、塔台、風向袋
const RUNWAY_Z = -2.1
const RUNWAY_DASHES = 9
const ZEBRA_STRIPES = 5
const TERMINAL_BLUE = '#2f6fb0'
const TOWER_SHAFT = 4.0
const PLANE_X = -4
const SOCK_SEGMENTS = 4
const AIRPORT = [
  part('plane', [20, FLAT, 3.6], [0, RUNWAY_Z], 0, '#55595e'),
  ...Array.from({ length: RUNWAY_DASHES }, (_, i) =>
    part('plane', [1.0, LINE, 0.15], [-8 + i * 2, RUNWAY_Z], FLAT, WHITE)
  ),
  ...[-1, 1].flatMap((end) =>
    Array.from({ length: ZEBRA_STRIPES }, (_, k) =>
      part('plane', [0.8, LINE, 0.25], [end * 9.3, RUNWAY_Z - 1.3 + k * 0.65], FLAT, WHITE)
    )
  ),
  // 航站
  part('box', [6, 2.6, 2.6], [3, 2.3], 0, '#eef1f4'),
  part('box', [6.2, 0.25, 2.8], [3, 2.3], 2.6, TERMINAL_BLUE),
  glass([4.5, 1.4, 0.08], [3, 3.64], 0.6),
  // 塔台
  part('box', [1.0, TOWER_SHAFT, 1.0], [-1.2, 2.4], 0, '#dfe4ea'),
  glass([1.6, 0.9, 1.6], [-1.2, 2.4], TOWER_SHAFT),
  part('box', [1.8, 0.15, 1.8], [-1.2, 2.4], TOWER_SHAFT + 0.9, TERMINAL_BLUE),
  // 風向袋：柱子加一節節變細的橘白圓柱
  part('post', [0.06, 3, 0.06], [-7, 1.2], 0, '#999999'),
  ...Array.from({ length: SOCK_SEGMENTS }, (_, k) => {
    const diameter = 0.36 - k * 0.06
    return part(
      'cylinder',
      [0.35, diameter, diameter],
      [-6.8 + k * 0.35, 1.2],
      2.9 - diameter / 2,
      k % 2 ? WHITE : '#e8590c',
      { axis: 'x' }
    )
  }),
  // 小飛機：機身、藍色腰線、機身上方的主翼、尾翼、兩具引擎與螺旋槳、輪子
  part('cylinder', [4.4, 0.9, 0.9], [PLANE_X, RUNWAY_Z], 0.45, WHITE, { axis: 'x' }),
  part('box', [4.0, 0.12, 0.92], [PLANE_X, RUNWAY_Z], 0.75, TERMINAL_BLUE),
  part('box', [0.7, 0.08, 3.6], [PLANE_X + 0.2, RUNWAY_Z], 1.35, WHITE),
  part('box', [0.4, 1.0, 0.08], [PLANE_X - 2.0, RUNWAY_Z], 0.9, TERMINAL_BLUE),
  part('box', [0.5, 0.06, 1.6], [PLANE_X - 2.0, RUNWAY_Z], 1.85, WHITE),
  ...[-1, 1].flatMap((side) => [
    part('cylinder', [0.8, 0.36, 0.36], [PLANE_X + 0.4, RUNWAY_Z + side * 1.1], 1.0, '#dddddd', {
      axis: 'x',
    }),
    part('box', [0.04, 0.9, 0.08], [PLANE_X + 0.85, RUNWAY_Z + side * 1.1], 0.73, WHEEL_COLOR),
  ]),
  ...[-1, 1].map((side) => wheel('z', 0.36, 0.08, [PLANE_X + 0.2, RUNWAY_Z + side * 0.5])),
]

/** 機車（車頭朝 +z）：兩個輪子、車身、黑色坐墊、龍頭 */
const scooter = ([x, z], color) => [
  wheel('x', 0.4, 0.08, [x, z - 0.45]),
  wheel('x', 0.4, 0.08, [x, z + 0.45]),
  part('box', [0.35, 0.35, 0.9], [x, z - 0.05], 0.25, color),
  part('box', [0.3, 0.12, 0.55], [x, z - 0.15], 0.6, '#222222'),
  part('box', [0.08, 0.6, 0.08], [x, z + 0.4], 0.4, '#555555'),
  part('box', [0.6, 0.06, 0.06], [x, z + 0.4], 1.0, '#555555'),
]

// 東清超商（8 × 6）：白色方盒、由下往上綠橘紅三條色帶、落地玻璃、門口停機車
const STORE_WALL = 3.1
const STORE_BAND_BASE = 2.2
const STORE_BAND_STEP = 0.25
const STORE = [
  part('box', [7, STORE_WALL, 3.8], [0, -1.0], 0, '#fafafa'),
  ...['#1f8a4c', '#f08c00', RED].map((color, i) =>
    part('box', [7.06, 0.22, 3.86], [0, -1.0], STORE_BAND_BASE + i * STORE_BAND_STEP, color)
  ),
  glass([6.2, 2.0, 0.08], [0, 0.94], 0.05),
  ...[-1.6, 0, 1.6].map((x) => part('box', [0.06, 2.0, 0.1], [x, 0.96], 0.05, '#bbbbbb')),
  part('box', [7.0, 0.12, 0.9], [0, 1.35], 2.05, '#e6e6e6'),
  ...scooter([-2.6, 2.2], '#2f6fb0'),
  ...scooter([-1.8, 2.2], RED),
  ...scooter([-1.0, 2.2], WHITE),
]

// 野銀籃球場（12 × 7）：紅色外框、綠色場地、白線與中圈、兩邊禁區、兩個籃架、場邊長椅
const RIM_HEIGHT = 3
const RIM_THICKNESS = 0.06
const HOOP_POST_HEIGHT = 3.4
const COURT_LINE_LEVEL = 0.06
/** 籃架（side 是 -1 左、1 右）：柱子、橫桿、白籃板、朝場內伸出去的橘色框 */
const hoop = (side) => [
  part('post', [0.12, HOOP_POST_HEIGHT, 0.12], [side * 5.8, 0], 0, '#6b7280'),
  part('box', [0.6, 0.1, 0.1], [side * 5.55, 0], 3.25, '#6b7280'),
  part('box', [0.06, 0.9, 1.5], [side * 5.25, 0], 2.5, WHITE),
  part(
    'ring',
    [0.5, RIM_THICKNESS, 0.5],
    [side * 4.95, 0],
    RIM_HEIGHT - RIM_THICKNESS / 2,
    '#e8590c'
  ),
]
const COURT = [
  part('plane', [12, 0.04, 7], [0, 0], 0, '#c0392b'),
  part('plane', [11, FLAT, 6], [0, 0], 0.005, '#2f8f5f'),
  part('plane', [11, LINE, 0.08], [0, -3], COURT_LINE_LEVEL, WHITE),
  part('plane', [11, LINE, 0.08], [0, 3], COURT_LINE_LEVEL, WHITE),
  ...[-5.5, 0, 5.5].map((x) => part('plane', [0.08, LINE, 6], [x, 0], COURT_LINE_LEVEL, WHITE)),
  part('ring', [2.0, LINE, 2.0], [0, 0], COURT_LINE_LEVEL, WHITE),
  ...[-1, 1].map((side) => part('plane', [2.4, LINE, 2.0], [side * 4.3, 0], 0.055, '#c97a3a')),
  ...hoop(-1),
  ...hoop(1),
  part('box', [2.4, 0.08, 0.4], [0, 3.2], 0.45, WOOD),
  ...[-1, 1].map((side) => part('box', [0.08, 0.45, 0.08], [side, 3.2], 0, '#555555')),
]

const SHAPES = {
  'post-office': scaledTo(8, 6, POST_OFFICE),
  farmers: scaledTo(8, 6, FARMERS),
  store: scaledTo(8, 6, STORE),
  airport: scaledTo(20, 8, AIRPORT),
  court: scaledTo(12, 7, COURT),
  stall: scaledTo(6, 3, STALL),
  ...HOUSEHOLD_SHAPES,
}

/**
 * kind 這種造型在寬 width、深 depth 的佔地上的零件：
 * [{ shape, width, height, depth 零件的大小, x, z 離佔地中心, y 零件中心的高度, color,
 *    axis 圓柱的軸（有才有）, opacity 半透明（有才有） }]
 */
export function shapeParts(kind, width, depth) {
  const parts = SHAPES[kind]
  if (!parts) {
    throw new Error(`魚島 3D：沒有「${kind}」這種地標造型`)
  }
  return parts.map(({ shape, size, at, base = 0, color, ...extra }) => ({
    shape,
    width: size[0] * width,
    height: size[1],
    depth: size[2] * depth,
    x: at[0] * width,
    z: at[1] * depth,
    y: base + size[1] / 2,
    color,
    ...extra,
  }))
}

// ── 招牌 ──

// 招牌比造型的最高點再高這麼多，才不會被建物擋住
const SIGN_GAP_ABOVE_SHAPE = 1.2

/** 招牌離地多高：零件（{ y 中心高度, height 高 }）最高的地方再往上 SIGN_GAP_ABOVE_SHAPE */
export function signHeightAbove(parts) {
  return Math.max(...parts.map((part) => part.y + part.height / 2)) + SIGN_GAP_ABOVE_SHAPE
}

/** 這個地標的招牌離地多高：用程式拼的造型的零件算 */
export function signHeight({ kind, width, depth }) {
  return signHeightAbove(shapeParts(kind, width, depth))
}

// 鏡頭拉遠超過這個程度（zoom：0 最近、1 最遠）招牌就隱藏，只留部落標示
const SIGN_HIDE_ZOOM = 0.5

export function signsVisible(zoom) {
  return zoom <= SIGN_HIDE_ZOOM
}
