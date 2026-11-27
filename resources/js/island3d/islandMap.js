// 魚島 3D：整座島的資料（#200、#207、#212）——6 個部落各 3 格的位置、兩條公路、走路速度，
// 以及海岸線、沙灘、水田、船。繪圖、可走範圍、找路都讀這一份。不依賴 three.js。
// 山在 #223 改成實際地形，見 terrainData.js、terrainHeight.js。
// 座標是 three.js 的世界單位：x 越大越東、z 越大越南。
//
// 位置照蘭嶼的實際地圖（#207 規格修訂 1）：海岸線、公路、村落的位置取自 OpenStreetMap
// （© OpenStreetMap contributors，ODbL），以北緯 22.045、東經 121.55 為原點，1 公里＝22.5 單位，
// 換算、簡化後寫死在這裡；做法見 docs/design.md 8.8。#212 再整座放大（見 ISLAND_SCALE）。
import { closestOnSegment } from './hexBounds'
import { PADDY_LAYOUTS, paddySize } from './paddyShape'

// 整座島的放大倍數（#212）：下面的座標是 #207 照實際地圖換算的數值，乘上這個倍數才是世界單位。
// 格子、公路的寬度、人物不跟著放大，所以倍數越大，部落周圍的空間越多。
export const ISLAND_SCALE = 1.5

// 走路速度（每秒走多遠）：#207 是 5，跟著島放大，走一樣的路花一樣的時間
const WALK_SPEED_BEFORE_SCALE = 5
export const WALK_SPEED = WALK_SPEED_BEFORE_SCALE * ISLAND_SCALE

const point = ([x, z]) => ({ x: x * ISLAND_SCALE, z: z * ISLAND_SCALE })

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

// 橫貫公路：兩端接在環島公路上，接點分別在紅頭第 2 格、野銀第 3 格裡（下山就進部落，#212），
// 中間走紅頭與野銀之間的山坳
const CROSS_ROAD = [
  [22.7, 57.5],
  [29.0, 46.0],
  [22.5, 30.5],
  [20.0, 12.0],
  [32.7, 1.6],
].map(point)

const HEX_RADIUS = 12
// 相鄰兩格（共用一條邊）的中心距離
const HEX_NEIGHBOR_DISTANCE = HEX_RADIUS * Math.sqrt(3)
const DEGREES_TO_RADIANS = Math.PI / 180

/**
 * 和 center 這一格貼著同一條邊的鄰格中心。degrees 是那條邊的垂直方向（0 是 +x、90 是 +z）；
 * slide 是沿著那條邊錯開多遠（0 是整條邊對齊；錯開後兩格仍貼著，只是共用的那一段變短）。
 * 用算的不手寫座標：手寫的四捨五入會讓兩格之間留下一條走不過去的細縫。
 */
const neighborHex = (center, degrees, slide = 0) => {
  const across = degrees * DEGREES_TO_RADIANS
  const along = across - Math.PI / 2
  return {
    x: center.x + HEX_NEIGHBOR_DISTANCE * Math.cos(across) + slide * Math.cos(along),
    z: center.z + HEX_NEIGHBOR_DISTANCE * Math.sin(across) + slide * Math.sin(along),
  }
}

/**
 * 一個部落：center 是主格的中心（環島公路上的點，[x, z] 是放大前的數值）；
 * others(center) 回傳另外幾格的中心（世界單位）。hexes 是全部格子的中心，主格排第一。
 */
const tribe = (code, [x, z], others) => {
  const scaled = point([x, z])
  const center = RING_ROAD.find((p) => p.x === scaled.x && p.z === scaled.z)
  return { code, center, hexes: [center, ...others(center)] }
}

/**
 * hexRadius 部落格子中心到頂點的距離；roadHalfWidth 公路中線到路邊的距離。
 * roads 的 points 是公路中線依序經過的點；closed 表示最後一點接回第一點。
 */
export const ISLAND_MAP = {
  hexRadius: HEX_RADIUS,
  roadHalfWidth: 2,
  tribes: [
    // 朗島：第 2 格貼著主格的西南邊，第 3 格在東邊的公路上
    tribe('iraraley', [-55.2, -81.9], (main) => [neighborHex(main, 150), { x: -58.6, z: -129.7 }]),
    // 東清
    tribe('iranmeilek', [28.6, -35.8], (main) => [neighborHex(main, -30), { x: 41.9, z: -24.7 }]),
    // 野銀：第 3 格在東南邊的環島公路旁（#201 規格修訂 1：原本在橫貫公路的接點上，三條公路交會、家屋排不下）
    tribe('ivalino', [36.0, 19.3], (main) => [neighborHex(main, 30, 1.5), { x: 90.9, z: 53.6 }]),
    // 紅頭
    tribe('imowrod', [12.1, 43.0], () => [
      { x: 39.7, z: 86.9 },
      { x: 56.7, z: 105.4 },
    ]),
    // 漁人
    tribe('iratay', [-18.3, 39.2], () => [
      { x: -52.5, z: 63.3 },
      { x: -71.4, z: 36.3 },
    ]),
    // 椰油
    tribe('yayo', [-81.5, -14.2], () => [
      { x: -136.9, z: -58.0 },
      { x: -109.5, z: 0.2 },
    ]),
  ],
  roads: [
    // 環島公路：朗島 → 東清 → 野銀 → 島的東南端 → 紅頭 → 漁人 → 椰油 → 回朗島
    { points: RING_ROAD, closed: true },
    // 橫貫公路：紅頭旁的接點 ↔ 野銀旁的接點
    { points: CROSS_ROAD, closed: false },
  ],
}

/** 一個部落所有格子的中心；只有一格的地圖（測試用的小地圖）可以只寫 center */
export const tribeHexes = (tribe) => tribe.hexes ?? [tribe.center]

/** 地圖上所有公路的每一段：[{ from, to }] */
export function roadSegments(map) {
  return map.roads.flatMap(({ points, closed }) => {
    const ends = closed ? [...points, points[0]] : points
    return ends.slice(1).map((to, i) => ({ from: ends[i], to }))
  })
}

/** 公路上離 point 最近的點 */
function nearestOnRoads(map, point) {
  const gap = (other) => Math.hypot(other.x - point.x, other.z - point.z)
  return roadSegments(map)
    .map(({ from, to }) => closestOnSegment(point, from, to))
    .reduce((best, candidate) => (gap(candidate) < gap(best) ? candidate : best))
}

/**
 * 找路用的「路線點」：每個格子的中心、公路的折點，以及每個格子中心在公路上最近的那一點
 * （格子不在折點上時，從格子中心要先走到這一點才上得了公路）。同一個位置只算一次。
 */
export function routePoints(map) {
  const centers = map.tribes.flatMap(tribeHexes)
  const all = [
    ...centers,
    ...map.roads.flatMap((road) => road.points),
    ...(map.roads.length > 0 ? centers.map((center) => nearestOnRoads(map, center)) : []),
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
  // 這 3 點比實際的海岸線再往外推（#212）：公路靠海那一側要放得下機場
  [-76.0, 21.5],
  [-66.0, 24.4],
  [-58.0, 26.5],
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

// 水田（芋頭田）：好幾小塊拼成一片，拼法決定大小（#211 規格修訂 1）；位置直接寫世界單位
// （#212：#207 的位置放大後挪過；#211：拼成一片變大後再挪過、朝向調過）
const paddy = ([x, z, degrees], layout) => ({
  x,
  z,
  layout: PADDY_LAYOUTS[layout],
  ...paddySize(PADDY_LAYOUTS[layout]),
  heading: degrees * DEGREES_TO_RADIANS,
})

/**
 * coast 海岸線（多邊形的頂點）；beachWidth 沙灘的寬度；inland 沙灘以內的陸地（海岸線往內縮）；
 * paddies 水田（佔地：位置、拼法、寬、深、朝向）；
 * boats 海上的船（位置與船頭朝向的角度）。
 */
export const ISLAND_DECOR = {
  coast: COAST,
  beachWidth: BEACH_WIDTH,
  inland: insetOutline(COAST, BEACH_WIDTH),
  paddies: [
    // 都在溪旁、環島公路靠山的那一側：佔地離溪的中線 1.8～4（溪不會被切斷）、一條溪最多 2 塊
    // （#223 規格修訂 2；位置由搜尋腳本照規則找：每條溪挑離公路最近、海拔 260 公尺以下、
    // 離橫貫公路的路邊超過 24（不壓平山坳兩側的坡）的，正面朝向溪）
    paddy([112.8, 69.0, -90], '3x3'), // 日托那溪
    paddy([-85.6, 12.8, 66], '3x3'), // 椰油南溪
    paddy([-72.6, 22.4, -90], '1x3'), // 椰油南溪
    paddy([50.0, -82.2, -72], '3x3'), // 東清北溪
    paddy([37.6, -68.7, 104], '1x3'), // 東清北溪
    paddy([-18.9, -125.5, -90], '3x3'), // 朗島東溪
    paddy([-34.1, -126.7, 113], '2x2'), // 朗島東溪
    paddy([148.0, 119.6, 176], '2x3'), // 龍門溪
    paddy([161.1, 105.6, -51], '1x3'), // 龍門溪
    paddy([-3.0, 51.0, 90], '2x3'), // 漁人溪
    paddy([-99.1, -15.1, -153], '2x3'), // 椰油溪
    paddy([-107.4, -29.8, 79], '2x2'), // 椰油溪
    paddy([24.5, -59.5, -11], '2x2'), // 東清溪
    paddy([24.9, -42.1, 135], '1x3'), // 東清溪
  ],
  boats: [
    { ...point([-103, 2]), heading: Math.PI / 5 },
    { ...point([131, 74]), heading: -Math.PI / 3 },
    { ...point([-45, -104]), heading: Math.PI },
  ],
}
