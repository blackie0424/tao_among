// 魚島 3D：整座島的資料（#200）——6 個部落格子的位置、兩條公路、走路速度。
// 繪圖、可走範圍、找路都讀這一份。不依賴 three.js。
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
// 橫貫公路穿過山區的折點
const CROSS_BEND = { x: 4, z: 30 }

/**
 * hexRadius 部落格子中心到頂點的距離；roadHalfWidth 公路中線到路邊的距離。
 * roads 的 points 是公路中線依序經過的點；closed 表示最後一點接回第一點。
 */
export const ISLAND_MAP = {
  hexRadius: 6,
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
    // 橫貫公路：紅頭 ↔ 野銀
    { points: [IMOWROD, CROSS_BEND, IVALINO], closed: false },
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
