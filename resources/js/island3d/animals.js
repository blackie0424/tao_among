// 魚島 3D：島上的動物（#222）——狗 ino、貓 cito、豬 kois、山羊 kagling 的種類、位置、路線與走動。不依賴 three.js。
// 動物只是裝飾：比人小（最高不超過角色身高的一半）、不擋路、不能點；玩家走近時頭上顯示族語名稱。
import { heightAt } from './terrainHeight'
import { wanderPosition } from './wander'

/**
 * 每一種動物：model 模型檔（public/island3d/animals/<model>.glb）、name 族語名稱、height 最高點離地多高、
 * speed 每秒走多遠、coats 毛色（材質名稱 → 顏色；同一種有兩種的交替分配）
 */
const PIG_COAT = { body: '#2b2724', detail: '#1c1917' }
export const ANIMAL_KINDS = {
  ino: {
    model: 'ino',
    name: 'ino',
    height: 0.7,
    speed: 0.9,
    coats: [{ body: '#e6bd7a' }, { body: '#2a2624' }],
  },
  cito: {
    model: 'cito',
    name: 'cito',
    height: 0.4,
    speed: 0.5,
    coats: [
      { body: '#f0a24e', belly: '#fbf3e6' },
      { body: '#262322', belly: '#f4f1ec' },
    ],
  },
  kois: { model: 'kois', name: 'kois', height: 0.5, speed: 0.5, coats: [PIG_COAT] },
  piglet: { model: 'kois', name: 'kois', height: 0.25, speed: 0.5, coats: [PIG_COAT] },
  kagling: {
    model: 'kagling',
    name: 'kagling',
    height: 0.85,
    speed: 0.4,
    coats: [{ body: '#2a2420' }, { body: '#5a3e2b' }],
  },
}

// 每一端停多久；每一群起步時間錯開多少
const PAUSE_SECONDS = 3
const GROUP_OFFSET_SECONDS = 1.3
// 看多久之後的位置來決定面向
const LOOK_AHEAD_SECONDS = 0.1
// 玩家離動物這麼近（水平距離）才顯示名稱
export const NAME_DISTANCE = 4

const point = ([x, z]) => ({ x, z })
/**
 * 一群動物：place 在哪（部落代碼、'cross-road' 橫貫公路、'ring-road' 環島公路旁）、kinds 每一隻的種類、
 * ends 路線兩端、offsets 每一隻離路線的固定偏移（同一群一起走、彼此錯開；單獨一隻是 [0, 0]）
 */
const group = (place, kinds, [from, to], offsets = [[0, 0]]) => ({
  place,
  kinds,
  from: point(from),
  to: point(to),
  offsets: offsets.map(point),
})

// 位置照規格的條件找過（不穿過家屋四件、特色建築、水田、海；部落裡的不出格子；公路上的在路面或路邊 5 以內）
const ANIMAL_GROUPS = [
  // 部落裡：狗 2、貓 1；紅頭、野銀另有單獨走動的大豬
  group('iraraley', ['ino'], [[-84.8, -122.85], [-80.8, -122.85]]),
  group('iraraley', ['ino'], [[-101.77, -114.21], [-99.83, -110.71]]),
  group('iraraley', ['cito'], [[-57.94, -130.76], [-59.26, -128.64]]),
  group('iranmeilek', ['ino'], [[40.9, -53.7], [44.9, -53.7]]),
  group('iranmeilek', ['ino'], [[59.93, -65.84], [61.87, -62.34]]),
  group('iranmeilek', ['cito'], [[42.56, -25.76], [41.24, -23.64]]),
  group('ivalino', ['ino'], [[52, 28.95], [56, 28.95]]),
  group('ivalino', ['ino'], [[71.78, 36.29], [73.72, 39.79]]),
  group('ivalino', ['cito'], [[91.56, 52.54], [90.24, 54.66]]),
  group('ivalino', ['kois'], [[54.53, 27.28], [51.53, 27.12]]),
  group('imowrod', ['ino'], [[16.15, 64.5], [20.15, 64.5]]),
  group('imowrod', ['ino'], [[38.73, 85.15], [40.67, 88.65]]),
  group('imowrod', ['cito'], [[57.36, 104.34], [56.04, 106.46]]),
  group('imowrod', ['kois'], [[18.68, 62.83], [15.68, 62.67]]),
  group('imowrod', ['kois'], [[38.38, 87.97], [37.06, 85.27]]),
  group('iratay', ['ino'], [[-29.45, 58.8], [-25.45, 58.8]]),
  group('iratay', ['ino'], [[-53.47, 61.55], [-51.53, 65.05]]),
  group('iratay', ['cito'], [[-70.74, 35.24], [-72.06, 37.36]]),
  group('yayo', ['ino'], [[-124.25, -21.3], [-120.25, -21.3]]),
  group('yayo', ['ino'], [[-137.87, -59.75], [-135.93, -56.25]]),
  group('yayo', ['cito'], [[-108.84, -0.86], [-110.16, 1.26]]),
  // 橫貫公路：大豬帶 3 隻小豬
  group(
    'cross-road',
    ['kois', 'piglet', 'piglet', 'piglet'],
    [[32.96, 39.9], [32.29, 34.95]],
    [[0, 0], [0.6, 0.73], [-0.37, 0.96], [0.2, 1.49]]
  ),
  // 部落之間的環島公路旁：山羊 5 群（紅頭到漁人那一段很短，不放）
  group(
    'ring-road',
    ['kagling', 'kagling', 'kagling'],
    [[-42.18, -134.82], [-37.79, -139.47]],
    [[0, 0], [0.78, -0.92], [-0.92, -0.78]]
  ),
  group(
    'ring-road',
    ['kagling', 'kagling', 'kagling', 'kagling'],
    [[45.64, -10.68], [42.6, -5.04]],
    [[0, 0], [-0.62, 1.03], [-1.03, -0.62], [0.96, 0.23]]
  ),
  group(
    'ring-road',
    ['kagling', 'kagling', 'kagling', 'kagling', 'kagling'],
    [[64.83, 123.93], [64.9, 117.53]],
    [[0, 0], [0.07, -1.2], [1.2, 0.07], [-0.96, 0.24], [0.28, 1.17]]
  ),
  group(
    'ring-road',
    ['kagling', 'kagling', 'kagling'],
    [[-66.88, 49.53], [-73.11, 48.08]],
    [[0, 0], [-1.19, -0.21], [-0.21, 1.19]]
  ),
  group(
    'ring-road',
    ['kagling', 'kagling', 'kagling', 'kagling'],
    [[-119.03, -127.95], [-114.77, -123.17]],
    [[0, 0], [0.75, 0.94], [-0.94, 0.75], [0.54, -0.82]]
  ),
]

const shifted = (p, offset) => ({ x: p.x + offset.x, z: p.z + offset.z })

/**
 * 島上每一隻動物：{ kind 種類, name 族語名稱, model, height, coat 毛色, place 在哪, group 第幾群,
 *   route 牠自己走的路線（群的路線加上牠的偏移）, startSeconds 起步時間 }
 */
export function islandAnimals(groups = ANIMAL_GROUPS) {
  const seen = {}
  return groups.flatMap(({ place, kinds, from, to, offsets }, g) =>
    kinds.map((kind, k) => {
      const { model, name, height, speed, coats } = ANIMAL_KINDS[kind]
      seen[kind] = (seen[kind] ?? -1) + 1
      return {
        kind,
        name,
        model,
        height,
        coat: coats[seen[kind] % coats.length],
        place,
        group: g,
        route: {
          from: shifted(from, offsets[k]),
          to: shifted(to, offsets[k]),
          speed,
          pauseSeconds: PAUSE_SECONDS,
        },
        startSeconds: g * GROUP_OFFSET_SECONDS,
      }
    })
  )
}

const onGround = (field, { x, z }) => ({ x, y: heightAt(field, { x, z }), z })
const facing = (from, to) => Math.atan2(to.x - from.x, to.z - from.z)

/**
 * 過了 seconds 秒，這隻動物在哪（y 是那一點的地形高度）、面向哪、在做什麼（'walk' 或 'idle'）。
 * field 是地形高度表（terrainHeight.js）；reducedMotion（減少動態效果）時站在路線起點不動。
 */
export function animalPlacement(animal, seconds, { field, reducedMotion = false }) {
  const { route } = animal
  if (reducedMotion) {
    return { position: onGround(field, route.from), heading: facing(route.from, route.to), pose: 'idle' }
  }
  const at = seconds + animal.startSeconds
  const now = wanderPosition(at, route)
  const next = wanderPosition(at + LOOK_AHEAD_SECONDS, route)
  // 剛走過來、也還要往前走才算在走（停在端點、正要起步的那一刻仍是站著）
  const before = wanderPosition(at - LOOK_AHEAD_SECONDS, route)
  const differs = (a, b) => a.x !== b.x || a.z !== b.z
  const moving = differs(before, now) && differs(now, next)
  return {
    position: onGround(field, now),
    heading: moving ? facing(now, next) : facing(route.from, route.to),
    pose: moving ? 'walk' : 'idle',
  }
}

/** 玩家（player）離這隻動物（position）夠近、要顯示名稱嗎：水平距離 NAME_DISTANCE 以內 */
export function isNameVisible(position, player) {
  return Math.hypot(position.x - player.x, position.z - player.z) <= NAME_DISTANCE
}
