// 魚島 3D：每戶的四位家人（#201 驗收條件 5–8）——誰、長什麼樣子、在哪裡、什麼姿勢。不依賴 three.js。
import { HOUSEHOLDS } from './households'
import { BACKREST_FRONT_Z } from './householdShapes'
import { wanderPosition } from './wander'

/** 每一件的家人 */
export const FAMILY_ROLES = {
  vahay: 'kaminan',
  pananadengan: 'akay',
  zazawan: 'maran',
  tagakal: 'kaka',
}

/**
 * 外觀（Kenney Mini Characters）：第幾格的戶 → 稱呼 → { model 模型名, palette 衣服配色（PALETTE_SWAPS 的第幾組） }。
 * kaminan 3 戶是 3 個不同的人；男性同一款，靠衣服配色分。
 */
export const FAMILY_LOOKS = ['female-d', 'female-e', 'female-f'].map((kaminan, palette) => ({
  kaminan: { model: kaminan, palette: 0 },
  maran: { model: 'male-b', palette },
  akay: { model: 'male-e', palette },
  kaka: { model: 'male-f', palette },
}))

// 人物貼圖是 8 欄 × 4 列的色塊，色塊寫成 [欄, 列]（從 0 數）
/** 皮膚與頭髮用的色塊：貼圖第 3 列的前 4 欄，換配色時不碰 */
export const SKIN_AND_HAIR_CELLS = [0, 1, 2, 3].map((column) => [column, 2])

/** 衣服配色：每組是 [原本的色塊, 換成的色塊] 的清單；第 0 組是原色 */
export const PALETTE_SWAPS = [
  [],
  [
    [
      [0, 1],
      [4, 2],
    ],
    [
      [4, 2],
      [0, 1],
    ],
    [
      [2, 1],
      [7, 2],
    ],
    [
      [7, 2],
      [2, 1],
    ],
    [
      [6, 2],
      [1, 1],
    ],
    [
      [1, 1],
      [6, 2],
    ],
    [
      [5, 2],
      [3, 1],
    ],
    [
      [3, 1],
      [5, 2],
    ],
    [
      [0, 3],
      [0, 1],
    ],
  ],
  [
    [
      [0, 1],
      [7, 2],
    ],
    [
      [7, 2],
      [5, 2],
    ],
    [
      [4, 2],
      [2, 1],
    ],
    [
      [2, 1],
      [4, 2],
    ],
    [
      [6, 2],
      [0, 1],
    ],
    [
      [5, 2],
      [6, 2],
    ],
    [
      [1, 1],
      [3, 1],
    ],
    [
      [0, 3],
      [4, 2],
    ],
  ],
]

// ── 走動 ──

// 路線兩端離範圍中心幾成半徑；每位家人的路線方向與起步時間錯開
const ROUTE_REACH = 0.75
const ROUTE_TURN_RADIANS = 1.3
const START_OFFSET_SECONDS = 1.7
const WALK_SPEED = 0.8
const PAUSE_SECONDS = 2
// 看多久之後的位置來決定面向
const LOOK_AHEAD_SECONDS = 0.1

/** 範圍裡的來回路線：沿著一條直徑，兩端在範圍裡 */
function walkRoute(zone, index) {
  const angle = index * ROUTE_TURN_RADIANS
  const reach = zone.radius * ROUTE_REACH
  const dx = Math.cos(angle) * reach
  const dz = Math.sin(angle) * reach
  return {
    from: { x: zone.x - dx, z: zone.z - dz },
    to: { x: zone.x + dx, z: zone.z + dz },
    speed: WALK_SPEED,
    pauseSeconds: PAUSE_SECONDS,
  }
}

/** akay 坐的位置：pananadengan 中間立石前緣再往前 0.3 */
const SEAT_GAP = 0.3
function seatOf(stones, heading) {
  const ahead = BACKREST_FRONT_Z + SEAT_GAP
  return { x: stones.x + Math.sin(heading) * ahead, z: stones.z + Math.cos(heading) * ahead }
}

/**
 * 72 位家人：{ tribe, hex 第幾格, kind 在哪一件, role 稱呼, look 外觀, zone 範圍, heading 這一戶的朝向,
 *   index 第幾位（錯開用）, route 走動路線（akay 沒有）, seat 坐的位置（只有 akay） }
 */
export function familyMembers(households = HOUSEHOLDS) {
  return households.flatMap(({ tribe, hex, heading, items }, h) =>
    items.map((item, k) => {
      const role = FAMILY_ROLES[item.kind]
      const index = h * items.length + k
      const member = {
        tribe,
        hex,
        kind: item.kind,
        role,
        look: FAMILY_LOOKS[hex][role],
        zone: item.zone,
        heading,
        index,
      }
      return role === 'akay'
        ? { ...member, seat: seatOf(item, heading) }
        : { ...member, route: walkRoute(item.zone, index) }
    })
  )
}

// ── akay 的坐姿 ──

export const AKAY_POSES = ['sit', 'wheelchair-look-left', 'sit', 'wheelchair-look-right']
const POSE_SECONDS = 8
const POSE_OFFSET_SECONDS = POSE_SECONDS / 3

/** akay 現在的坐姿：每 8 秒換一次，第 hex 格的戶錯開；不在所在部落或減少動態效果時固定正面坐 */
export function akayPose(seconds, hex, { active, reducedMotion = false }) {
  if (!active || reducedMotion) {
    return AKAY_POSES[0]
  }
  const step = Math.floor((seconds + hex * POSE_OFFSET_SECONDS) / POSE_SECONDS)
  return AKAY_POSES[step % AKAY_POSES.length]
}

/**
 * 過了 seconds 秒，這位家人在哪、面向哪、在做什麼：{ position, heading, pose }。
 * active 是否在所在部落（不在的不動）；reducedMotion 是系統的「減少動態效果」。
 * pose：akay 是坐姿；其他人 'walk' 或 'idle'。
 */
export function memberPlacement(member, seconds, { active, reducedMotion = false }) {
  if (member.seat) {
    return {
      position: { ...member.seat },
      heading: member.heading,
      pose: akayPose(seconds, member.hex, { active, reducedMotion }),
    }
  }
  if (!active || reducedMotion) {
    return {
      position: { x: member.zone.x, z: member.zone.z },
      heading: member.heading,
      pose: 'idle',
    }
  }
  const at = seconds + member.index * START_OFFSET_SECONDS
  const position = wanderPosition(at, member.route)
  const next = wanderPosition(at + LOOK_AHEAD_SECONDS, member.route)
  const moving = next.x !== position.x || next.z !== position.z
  return {
    position,
    heading: moving ? Math.atan2(next.x - position.x, next.z - position.z) : member.heading,
    pose: moving ? 'walk' : 'idle',
  }
}

/**
 * 這張畫面動畫要往前推多少秒：只有所在部落、而且沒有「減少動態效果」才照實際經過的秒數播；
 * 其他的只在換動作的那一張一次跑完淡入淡出（fadeSeconds），之後停住不動。
 */
export function animationSeconds(
  deltaSeconds,
  { active, reducedMotion = false, changed, fadeSeconds }
) {
  if (active && !reducedMotion) {
    return deltaSeconds
  }
  return changed ? fadeSeconds : 0
}
