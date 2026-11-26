// 魚島 3D：整座島的資料（#200、#207）——6 個部落格子的位置、兩條公路、走路速度，
// 以及海岸線、沙灘、山、水田、船。繪圖、可走範圍、找路都讀這一份。不依賴 three.js。
// 座標是 three.js 的世界單位：x 越大越東、z 越大越南。
//
// 位置照蘭嶼的實際地圖（#207 規格修訂 1）：海岸線、公路、村落與山頂的位置取自 OpenStreetMap
// （© OpenStreetMap contributors，ODbL），以北緯 22.045、東經 121.55 為原點，1 公里＝22.5 單位，
// 換算、簡化後寫死在這裡；做法見 docs/design.md 8.8。

// 走路速度（每秒走多遠）：#199 的 2 倍
export const WALK_SPEED = 5

const point = ([x, z]) => ({ x, z })

// 環島公路：實際路線簡化成折線，順時針一圈。部落的格子中心也是折線上的點——
// 部落都在海邊，格子中心從實際的村落位置往內陸挪到整個格子落在陸地上，公路跟著彎進來。
const RING_ROAD = [
  [-55.2, -81.9], // 朗島
  [-38.2, -87.8],
  [1.2, -93.3],
  [22.7, -85.9],
  [31.3, -88.7],
  [38.4, -96.1],
  [41.6, -96.4],
  [45.7, -90.3],
  [39.1, -75.7],
  [39.7, -53.4],
  [44.4, -45.9],
  [40.8, -43.0],
  [28.6, -35.8], // 東清
  [28.3, -22.4],
  [32.7, 1.6], // 橫貫公路的東端接點
  [36.0, 19.3], // 野銀
  [51.5, 25.1],
  [72.4, 36.4],
  [107.1, 52.7],
  [115.0, 63.2],
  [114.3, 69.5],
  [103.4, 82.4],
  [98.2, 104.3],
  [85.2, 107.8],
  [80.2, 106.0],
  [71.6, 96.4],
  [66.4, 98.9],
  [57.8, 98.2],
  [49.8, 92.1],
  [22.7, 57.5], // 橫貫公路的西端接點
  [20.4, 54.6],
  [12.1, 43.0], // 紅頭
  [-18.3, 39.2], // 漁人
  [-39.9, 37.9],
  [-57.2, 15.9],
  [-66.8, 12.1],
  [-75.6, -2.6],
  [-81.5, -14.2], // 椰油
  [-95.3, -31.8],
  [-89.0, -48.7],
  [-90.6, -58.3],
  [-95.9, -69.8],
  [-115.5, -88.3],
  [-112.8, -94.5],
  [-104.5, -98.7],
  [-95.4, -90.8],
  [-87.2, -87.3],
].map(point)

// 橫貫公路：兩端接在部落外的環島公路上（比實際的接點往外移，下山後要再走一段才進部落），
// 中間走紅頭與野銀兩個格子之間的山坳
const CROSS_ROAD = [
  [22.7, 57.5],
  [29.0, 46.0],
  [22.5, 30.5],
  [20.0, 12.0],
  [32.7, 1.6],
].map(point)

const tribe = (code, [x, z]) => ({ code, center: RING_ROAD.find((p) => p.x === x && p.z === z) })

/**
 * hexRadius 部落格子中心到頂點的距離；roadHalfWidth 公路中線到路邊的距離。
 * roads 的 points 是公路中線依序經過的點；closed 表示最後一點接回第一點。
 */
export const ISLAND_MAP = {
  hexRadius: 12,
  roadHalfWidth: 2,
  tribes: [
    tribe('iraraley', [-55.2, -81.9]), // 朗島
    tribe('iranmeilek', [28.6, -35.8]), // 東清
    tribe('ivalino', [36.0, 19.3]), // 野銀
    tribe('imowrod', [12.1, 43.0]), // 紅頭
    tribe('iratay', [-18.3, 39.2]), // 漁人
    tribe('yayo', [-81.5, -14.2]), // 椰油
  ],
  roads: [
    // 環島公路：朗島 → 東清 → 野銀 → 島的東南端 → 紅頭 → 漁人 → 椰油 → 回朗島
    { points: RING_ROAD, closed: true },
    // 橫貫公路：紅頭旁的接點 ↔ 野銀旁的接點
    { points: CROSS_ROAD, closed: false },
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

// 海岸線：實際的海岸線簡化成多邊形，再整圈往外推 4 單位，讓貼著海邊的公路與部落格子都在陸地上
const COAST = [
  [-100.5, -29.8],
  [-103.9, -23.6],
  [-97.4, -21.6],
  [-94.4, -14.6],
  [-94.3, -3.0],
  [-81.2, -0.0],
  [-71.6, 18.2],
  [-64.1, 17.5],
  [-60.1, 20.8],
  [-57.0, 30.9],
  [-35.0, 53.5],
  [-24.7, 55.7],
  [-11.8, 50.4],
  [-4.1, 50.8],
  [5.2, 53.9],
  [12.9, 61.9],
  [16.5, 60.0],
  [41.9, 92.5],
  [47.2, 106.2],
  [61.2, 108.1],
  [70.0, 104.3],
  [79.9, 113.7],
  [102.5, 108.7],
  [112.1, 79.6],
  [122.1, 69.1],
  [120.1, 64.0],
  [119.2, 53.6],
  [111.5, 47.0],
  [73.9, 30.2],
  [44.1, 9.2],
  [45.7, 6.3],
  [36.1, -12.9],
  [36.0, -23.7],
  [40.0, -29.3],
  [49.2, -29.4],
  [50.9, -38.1],
  [47.6, -54.6],
  [49.5, -57.9],
  [45.5, -74.7],
  [52.6, -90.7],
  [42.7, -110.5],
  [23.5, -93.6],
  [18.6, -100.4],
  [-1.0, -100.2],
  [-40.9, -94.3],
  [-47.6, -95.1],
  [-54.0, -97.8],
  [-85.7, -93.6],
  [-103.1, -106.8],
  [-116.5, -100.6],
  [-122.2, -88.1],
  [-100.4, -67.4],
  [-94.9, -52.6],
  [-99.2, -46.3],
  [-100.0, -31.8],
].map(point)

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

// 水田（芋頭田）的大小
const PADDY = { width: 6, depth: 4 }
const DEGREES_TO_RADIANS = Math.PI / 180
const paddy = ([x, z, degrees], tribeCode) => ({
  ...(tribeCode ? { tribe: tribeCode } : {}),
  x,
  z,
  ...PADDY,
  heading: degrees * DEGREES_TO_RADIANS,
})

/**
 * coast 海岸線（多邊形的頂點）；beachWidth 沙灘的寬度；inland 沙灘以內的陸地（海岸線往內縮）；
 * mountains 山（位置、山腳半徑、高度），照實際山頭的位置與相對高低，都不壓到部落、公路、地標、水田；
 * paddies 水田（佔地：位置、寬、深、朝向；tribe 是它在哪個部落旁，公路旁的沒有）；
 * boats 海上的船（位置與船頭朝向的角度）。
 */
export const ISLAND_DECOR = {
  coast: COAST,
  beachWidth: BEACH_WIDTH,
  inland: insetOutline(COAST, BEACH_WIDTH),
  mountains: [
    // 由高到低；最高的對應紅頭山
    { x: -35.8, z: -20.0, radius: 14.1, height: 21.9 },
    { x: -73.4, z: -33.9, radius: 7.0, height: 20.4 },
    { x: -39.2, z: -51.9, radius: 13.2, height: 19.8 },
    { x: 82.9, z: 86.6, radius: 10.6, height: 19.3 },
    { x: 15.6, z: -72.1, radius: 10.7, height: 18.6 },
    { x: -16.5, z: -36.9, radius: 12.3, height: 17.6 },
    { x: 16.3, z: -0.5, radius: 7.7, height: 17.3 },
    { x: -25.5, z: 17.7, radius: 8.8, height: 16.3 },
    { x: 102.0, z: 63.2, radius: 5.8, height: 15.1 },
    { x: 39.5, z: 39.4, radius: 7.0, height: 11.9 },
    { x: -33.4, z: 28.0, radius: 5.9, height: 9.4 },
  ],
  paddies: [
    // 每個部落旁、靠山的那一側
    paddy([-58.2, -65.2, 170], 'iraraley'),
    paddy([15.6, -46.7, 50], 'iranmeilek'),
    paddy([49.8, 30.9, -130], 'ivalino'),
    paddy([15.1, 26.3, -10], 'imowrod'),
    paddy([-12.5, 23.2, -20], 'iratay'),
    paddy([-66.8, -22.7, -60], 'yayo'),
    // 部落之間的環島公路旁、靠內陸的那一側
    paddy([-15.7, -85.4, -172]),
    paddy([31.5, -83.0, -162]),
    paddy([62.2, 37.2, 152]),
    paddy([91.3, 51.3, 155]),
    paddy([90.8, 100.6, 15]),
    paddy([54.7, 88.9, -37]),
    paddy([-52.7, 12.7, -52]),
    paddy([-84.2, -45.7, -110]),
  ],
  boats: [
    { x: -103, z: 2, heading: Math.PI / 5 },
    { x: 131, z: 74, heading: -Math.PI / 3 },
    { x: -45, z: -104, heading: Math.PI },
  ],
}
