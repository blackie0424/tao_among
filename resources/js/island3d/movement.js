// 魚島 3D：角色的輸入與移動規則（#199 驗收條件 2）。不依賴 three.js。
// 輸入狀態是不可變的：每個函式回傳新的狀態。
//   keys   目前按住的方向鍵
//   target 點地面選的目的地，沒有時是 null
import { clampToHex, isInsideHex } from './hexBounds'

// 方向鍵對應的移動方向：上＝往畫面遠處（-z）
const KEY_DIRECTIONS = {
  ArrowUp: { x: 0, z: -1 },
  ArrowDown: { x: 0, z: 1 },
  ArrowLeft: { x: -1, z: 0 },
  ArrowRight: { x: 1, z: 0 },
}

// 單步最多算這麼久：分頁從背景回來時兩張畫面隔很久，角色不該瞬間跳一大段
const MAX_STEP_SECONDS = 0.1

export function createMovementInput() {
  return { keys: [], target: null }
}

/** 角色是否正要移動（有按住方向鍵，或有還沒走到的目的地） */
export function isMoving(input) {
  return input.keys.length > 0 || input.target !== null
}

/** 按下方向鍵：取消點選的目的地，改由方向鍵控制。不是方向鍵的按鍵不理會 */
export function pressKey(input, key) {
  if (!(key in KEY_DIRECTIONS)) {
    return input
  }
  return { keys: input.keys.includes(key) ? input.keys : [...input.keys, key], target: null }
}

export function releaseKey(input, key) {
  return { ...input, keys: input.keys.filter((held) => held !== key) }
}

/** 點地面：按住方向鍵時不理會；點在格子外沒有反應；否則（重新）設定目的地 */
export function tapGround(input, point, radius) {
  if (input.keys.length > 0 || !isInsideHex(point, radius)) {
    return input
  }
  return { ...input, target: { x: point.x, z: point.z } }
}

/** 視窗失去焦點或分頁切到背景：清掉所有輸入，角色停下 */
export function clearInput() {
  return createMovementInput()
}

/** 按住的方向鍵合起來的單位方向；互相抵銷（例如同時按左右）時是 null */
function keyDirection(keys) {
  const sum = keys.reduce(
    (total, key) => ({ x: total.x + KEY_DIRECTIONS[key].x, z: total.z + KEY_DIRECTIONS[key].z }),
    { x: 0, z: 0 }
  )
  const length = Math.hypot(sum.x, sum.z)
  return length === 0 ? null : { x: sum.x / length, z: sum.z / length }
}

function stepByKeys(input, position, distance, radius) {
  const direction = keyDirection(input.keys)
  if (direction === null) {
    return { input, position }
  }
  const next = { x: position.x + direction.x * distance, z: position.z + direction.z * distance }
  return { input, position: clampToHex(next, radius) }
}

function stepTowardTarget(input, position, distance) {
  const dx = input.target.x - position.x
  const dz = input.target.z - position.z
  const remaining = Math.hypot(dx, dz)
  if (remaining <= distance) {
    return { input: { ...input, target: null }, position: { ...input.target } }
  }
  const ratio = distance / remaining
  return { input, position: { x: position.x + dx * ratio, z: position.z + dz * ratio } }
}

/**
 * 過了 deltaSeconds 之後角色在哪裡。方向鍵優先於點選的目的地；走到目的地就停。
 * options: { speed 每秒走多遠, radius 六角格半徑 }
 */
export function stepMovement(input, position, deltaSeconds, { speed, radius }) {
  const distance = speed * Math.min(deltaSeconds, MAX_STEP_SECONDS)
  if (input.keys.length > 0) {
    return stepByKeys(input, position, distance, radius)
  }
  if (input.target !== null) {
    return stepTowardTarget(input, position, distance)
  }
  return { input, position }
}
