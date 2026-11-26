// 魚島 3D：整座島的資料（#200、#207）——6 個部落格子的位置、兩條公路、走路速度，
// 以及海岸線、沙灘、山、水田、船。繪圖、可走範圍、找路都讀這一份。不依賴 three.js。
// 座標是 three.js 的世界單位，島的中心在原點：x 越大越東、z 越大越南。

// 走路速度（每秒走多遠）：#199 的 2 倍
export const WALK_SPEED = 5

// 部落格子的中心
const IRARALEY = { x: 0, z: -70 } // 朗島
const IRANMEILEK = { x: 50, z: -32 } // 東清
const IVALINO = { x: 46, z: 26 } // 野銀
const IMOWROD = { x: -34, z: 44 } // 紅頭
const IRATAY = { x: -54, z: 0 } // 漁人
const YAYO = { x: -50, z: -46 } // 椰油

// 環島公路在野銀與紅頭之間繞過島南端的折點（由東往西）
const SOUTH_BENDS = [
  { x: 34, z: 70 },
  { x: 0, z: 88 },
  { x: -30, z: 76 },
]
// 橫貫公路（#207）：兩端接在部落外的環島公路中線上，下來後要沿環島公路再走一段才進部落。
// 西端在紅頭往島南端那一段的正中間；東端在野銀往東清那一段、離野銀三成的地方
const CROSS_WEST_JUNCTION = { x: -32, z: 60 }
const CROSS_EAST_JUNCTION = { x: 47.2, z: 8.6 }
// 中間穿過山區的折點（由西往東），避開紅頭與野銀的格子
const CROSS_BENDS = [
  { x: -10, z: 52 },
  { x: 22, z: 10 },
]

/**
 * hexRadius 部落格子中心到頂點的距離；roadHalfWidth 公路中線到路邊的距離。
 * roads 的 points 是公路中線依序經過的點；closed 表示最後一點接回第一點。
 */
export const ISLAND_MAP = {
  hexRadius: 12,
  roadHalfWidth: 2,
  tribes: [
    { code: 'iraraley', center: IRARALEY },
    { code: 'iranmeilek', center: IRANMEILEK },
    { code: 'ivalino', center: IVALINO },
    { code: 'imowrod', center: IMOWROD },
    { code: 'iratay', center: IRATAY },
    { code: 'yayo', center: YAYO },
  ],
  roads: [
    // 環島公路：朗島 → 東清 → 野銀 → 島的南端 → 紅頭 → 漁人 → 椰油 → 回朗島
    {
      points: [IRARALEY, IRANMEILEK, IVALINO, ...SOUTH_BENDS, IMOWROD, IRATAY, YAYO],
      closed: true,
    },
    // 橫貫公路：紅頭旁的接點 ↔ 野銀旁的接點
    { points: [CROSS_WEST_JUNCTION, ...CROSS_BENDS, CROSS_EAST_JUNCTION], closed: false },
  ],
}

/** 地圖上所有公路的每一段：[{ from, to }] */
export function roadSegments(map) {
  return map.roads.flatMap(({ points, closed }) => {
    const ends = closed ? [...points, points[0]] : points
    return ends.slice(1).map((to, i) => ({ from: ends[i], to }))
  })
}

/** 找路用的「路線點」：部落中心與公路的折點，同一個位置只算一次 */
export function routePoints(map) {
  const all = [
    ...map.tribes.map((tribe) => tribe.center),
    ...map.roads.flatMap((road) => road.points),
  ]
  return all.filter(
    (point, i) => all.findIndex((other) => other.x === point.x && other.z === point.z) === i
  )
}

// ── 裝飾（走不進去）：海岸線、沙灘、山、水田、船 ──

// 海岸線（#207）：手寫的輪廓，北邊寬、往南收窄、南端突出；順時針，從西北角開始
const COAST = [
  [-22, -94],
  [10, -97],
  [40, -84],
  [66, -60],
  [78, -34],
  [76, -4],
  [72, 30],
  [64, 62],
  [48, 88],
  [24, 104],
  [0, 112],
  [-28, 106],
  [-48, 92],
  [-58, 70],
  [-60, 48],
  [-72, 28],
  [-80, 2],
  [-78, -30],
  [-74, -60],
  [-56, -82],
].map(([x, z]) => ({ x, z }))

// 沙灘：海岸線往內這麼寬是灰白色
const BEACH_WIDTH = 5

/**
 * 把多邊形 polygon 的每個頂點往內縮：縮完的每條邊離原本的邊都是 width。
 * 頂點順時針或逆時針排都可以；回傳同樣數量、同樣順序的頂點。
 */
export function insetOutline(polygon, width) {
  const at = (i) => polygon[(i + polygon.length) % polygon.length]
  const doubleArea = polygon.reduce((sum, a, i) => sum + a.x * at(i + 1).z - at(i + 1).x * a.z, 0)
  const turn = Math.sign(doubleArea)
  // 邊 from → to 朝多邊形裡面的法線
  const inward = (from, to) => {
    const length = Math.hypot(to.x - from.x, to.z - from.z)
    return { x: (-(to.z - from.z) / length) * turn, z: ((to.x - from.x) / length) * turn }
  }
  return polygon.map((vertex, i) => {
    const before = inward(at(i - 1), vertex)
    const after = inward(vertex, at(i + 1))
    // 沿兩條邊法線的角平分線走，走到離兩條邊都是 width
    const scale = width / (1 + before.x * after.x + before.z * after.z)
    return {
      x: vertex.x + (before.x + after.x) * scale,
      z: vertex.z + (before.z + after.z) * scale,
    }
  })
}

// 山的顏色由高度決定：這個高度以下最淺、這個高度以上最深，中間照比例
const MOUNTAIN_LOW = { height: 8, color: [0x7d, 0xbb, 0x88] }
const MOUNTAIN_HIGH = { height: 22, color: [0x2f, 0x7a, 0x45] }

/** 山的顏色（#rrggbb）：越高越深、同高同色 */
export function mountainColor(height) {
  const span = MOUNTAIN_HIGH.height - MOUNTAIN_LOW.height
  const t = Math.min(1, Math.max(0, (height - MOUNTAIN_LOW.height) / span))
  return MOUNTAIN_LOW.color
    .map((low, i) => Math.round(low + (MOUNTAIN_HIGH.color[i] - low) * t))
    .reduce((hex, channel) => hex + channel.toString(16).padStart(2, '0'), '#')
}

const unit = (from, to) => {
  const length = Math.hypot(to.x - from.x, to.z - from.z)
  return { x: (to.x - from.x) / length, z: (to.z - from.z) / length }
}

// 公路旁的東西離路邊留這麼寬
const ROADSIDE_GAP = 1.5

/**
 * 公路 from → to 旁邊的一塊長方形佔地：沿路離 from 走 along、放在路的 side 那一側
 * （'inland' 靠島中心、'seaward' 靠海），寬 width 沿著路、深 depth 垂直於路，正面朝路。
 * 回傳 { x, z, width, depth, heading }。
 */
export function besideRoad(from, to, along, side, width, depth) {
  const forward = unit(from, to)
  const onRoad = { x: from.x + forward.x * along, z: from.z + forward.z * along }
  const left = { x: -forward.z, z: forward.x }
  // 往左跨一步會不會離島中心（原點）更近
  const leftIsInland = left.x * onRoad.x + left.z * onRoad.z < 0
  const sign = leftIsInland === (side === 'inland') ? 1 : -1
  const away = ISLAND_MAP.roadHalfWidth + ROADSIDE_GAP + depth / 2
  return {
    x: onRoad.x + left.x * sign * away,
    z: onRoad.z + left.z * sign * away,
    width,
    depth,
    heading: Math.atan2(-left.x * sign, -left.z * sign),
  }
}

// 水田（芋頭田）的大小
const PADDY = { width: 6, depth: 4 }
const paddyBeside = (from, to, along) =>
  besideRoad(from, to, along, 'inland', PADDY.width, PADDY.depth)
// 部落旁的水田離部落中心這麼遠（格子外），在往島中心的方向
const PADDY_FROM_TRIBE = 17
function paddyNear({ code, center }) {
  const inland = unit(center, { x: 0, z: 0 })
  return {
    tribe: code,
    x: center.x + inland.x * PADDY_FROM_TRIBE,
    z: center.z + inland.z * PADDY_FROM_TRIBE,
    ...PADDY,
    heading: Math.atan2(inland.x, inland.z),
  }
}

/**
 * coast 海岸線（多邊形的頂點）；beachWidth 沙灘的寬度；inland 沙灘以內的陸地（海岸線往內縮）；
 * mountains 山（位置、山腳半徑、高度），都不壓到部落、公路、地標、水田；
 * paddies 水田（佔地：位置、寬、深、朝向；tribe 是它在哪個部落旁，公路旁的沒有）；
 * boats 海上的船（位置與船頭朝向的角度）。
 */
export const ISLAND_DECOR = {
  coast: COAST,
  beachWidth: BEACH_WIDTH,
  inland: insetOutline(COAST, BEACH_WIDTH),
  mountains: [
    // 由高到低；最高的在北半部中間（對應紅頭山）
    { x: -4, z: -30, radius: 14, height: 22 },
    { x: -14, z: -48, radius: 9, height: 20 },
    { x: 16, z: 62, radius: 11, height: 19.5 },
    { x: 22, z: -32, radius: 9, height: 19 },
    { x: 10, z: -8, radius: 10, height: 18 },
    { x: 28, z: -6, radius: 9, height: 17.5 },
    { x: -22, z: 14, radius: 10, height: 16.5 },
    { x: 26, z: 40, radius: 8, height: 15 },
    { x: -8, z: 70, radius: 7, height: 12 },
    { x: -34, z: -18, radius: 7, height: 10 },
  ],
  paddies: [
    // 每個部落旁、靠山的那一側
    ...ISLAND_MAP.tribes.map(paddyNear),
    // 部落之間的環島公路旁、靠山的那一側
    paddyBeside(IRARALEY, IRANMEILEK, 24),
    paddyBeside(IRANMEILEK, IVALINO, 26),
    paddyBeside(IVALINO, SOUTH_BENDS[0], 30),
    paddyBeside(IMOWROD, IRATAY, 26),
    paddyBeside(IRATAY, YAYO, 20),
    paddyBeside(YAYO, IRARALEY, 18),
  ],
  boats: [
    { x: 92, z: -62, heading: Math.PI / 5 },
    { x: -96, z: 24, heading: -Math.PI / 3 },
    { x: 62, z: 108, heading: Math.PI },
  ],
}
