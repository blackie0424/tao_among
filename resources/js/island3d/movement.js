// 魚島 3D：角色的輸入與移動規則（#199 驗收條件 2、#200 驗收條件 3～6）。不依賴 three.js。
// 輸入狀態是不可變的：每個函式回傳新的狀態。
//   keys 目前按住的方向鍵
//   path 點地面後要依序走過的點（最後一個是目的地），沒有時是空的
// world 是這張地圖的規則：createWalkable(map) 的結果加上 findRoute（見 walkable.js、route.js）。

// 方向鍵對應的移動方向（鏡頭沒轉時）：上＝往畫面遠處（-z）
const KEY_DIRECTIONS = {
  ArrowUp: { x: 0, z: -1 },
  ArrowDown: { x: 0, z: 1 },
  ArrowLeft: { x: -1, z: 0 },
  ArrowRight: { x: 1, z: 0 },
}

// 單步最多算這麼久：分頁從背景回來時兩張畫面隔很久，角色不該瞬間跳一大段
const MAX_STEP_SECONDS = 0.1

export function createMovementInput() {
  return { keys: [], path: [] }
}

/** 角色是否正要移動（有按住方向鍵，或有還沒走到的目的地） */
export function isMoving(input) {
  return input.keys.length > 0 || input.path.length > 0
}

/** 點地面選的目的地（路線的最後一點）；沒有要走的路線時是 null */
export function destinationOf(input) {
  const last = input.path.at(-1)
  return last ? { x: last.x, z: last.z } : null
}

/** 按下方向鍵：取消點選的目的地，改由方向鍵控制。不是方向鍵的按鍵不理會 */
export function pressKey(input, key) {
  if (!(key in KEY_DIRECTIONS)) {
    return input
  }
  return { keys: input.keys.includes(key) ? input.keys : [...input.keys, key], path: [] }
}

export function releaseKey(input, key) {
  return { ...input, keys: input.keys.filter((held) => held !== key) }
}

/**
 * 點地面：按住方向鍵時不理會；點在不可走的地方（山、海）沒有反應、原本的目的地不變；
 * 否則從目前位置 position 找路，（重新）設定要走的路線
 */
export function tapGround(input, point, position, world) {
  if (input.keys.length > 0) {
    return input
  }
  const path = world.findRoute(position, point)
  return path === null ? input : { ...input, path }
}

/** 視窗失去焦點或分頁切到背景：清掉所有輸入，角色停下 */
export function clearInput() {
  return createMovementInput()
}

/**
 * 按住的方向鍵合起來的單位方向；互相抵銷（例如同時按左右）時是 null。
 * heading 是鏡頭繞著角色轉的角度（弧度）：0 時鏡頭在角色的 +z 那一側往 -z 看；
 * 方向跟著鏡頭轉，所以「上」永遠是往畫面遠處。
 */
function keyDirection(keys, heading) {
  const sum = keys.reduce(
    (total, key) => ({ x: total.x + KEY_DIRECTIONS[key].x, z: total.z + KEY_DIRECTIONS[key].z }),
    { x: 0, z: 0 }
  )
  const length = Math.hypot(sum.x, sum.z)
  if (length === 0) {
    return null
  }
  const cos = Math.cos(heading)
  const sin = Math.sin(heading)
  return {
    x: (sum.x * cos + sum.z * sin) / length,
    z: (-sum.x * sin + sum.z * cos) / length,
  }
}

function stepByKeys(input, position, distance, heading, world) {
  const direction = keyDirection(input.keys, heading)
  if (direction === null) {
    return { input, position }
  }
  const next = { x: position.x + direction.x * distance, z: position.z + direction.z * distance }
  // 走出可走範圍就拉回最近的邊上：往斜向走時會沿著邊滑動
  const target = world.clampToWalkable(next)
  // 這一步的直線要整段可走；中間隔著不可走的地方（格子與公路交界的凹角）就只走到邊上
  const reachable = world.isStraightWalkable(position, target)
  return { input, position: reachable ? target : world.reachToward(position, next) }
}

/** 沿著路線走 distance 這麼遠：走到的點從路線拿掉，走完就停在目的地 */
function stepAlongPath(input, position, distance) {
  let current = position
  let remaining = distance
  let path = input.path
  while (path.length > 0) {
    const [next, ...rest] = path
    const gap = Math.hypot(next.x - current.x, next.z - current.z)
    if (gap > remaining) {
      const ratio = remaining / gap
      current = {
        x: current.x + (next.x - current.x) * ratio,
        z: current.z + (next.z - current.z) * ratio,
      }
      break
    }
    remaining -= gap
    current = { x: next.x, z: next.z }
    path = rest
  }
  return { input: { ...input, path }, position: current }
}

/**
 * 過了 deltaSeconds 之後角色在哪裡。方向鍵優先於點選的路線；走到目的地就停。
 * options: { speed 每秒走多遠, heading 鏡頭轉的角度（弧度，沒給是 0）, world 這張地圖的規則 }
 */
export function stepMovement(input, position, deltaSeconds, { speed, heading = 0, world }) {
  const distance = speed * Math.min(deltaSeconds, MAX_STEP_SECONDS)
  if (input.keys.length > 0) {
    return stepByKeys(input, position, distance, heading, world)
  }
  if (input.path.length > 0) {
    return stepAlongPath(input, position, distance)
  }
  return { input, position }
}
