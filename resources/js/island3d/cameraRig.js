// 魚島 3D：鏡頭規則與手勢判斷（#200 驗收條件 6、7）。不依賴 three.js。
// 鏡頭繞著它對準的位置轉。對準的位置最近時是角色，拉遠時移向島的中心，
// 最遠時是島的中心（整座島置中）。rig 是 { heading, pitch, zoom }：
//   heading 鏡頭轉的角度（弧度）：0 時鏡頭在對準位置的 +z 那一側往 -z 看
//   pitch   俯視的角度（弧度）：越大越從正上方往下看
//   zoom    0 是最近、1 是最遠
// 狀態都是不可變的：每個函式回傳新的狀態。

// 上下視角（度）
export const CAMERA_FIELD_OF_VIEW = 45
const DEGREES_TO_RADIANS = Math.PI / 180
// 進入頁面時的俯角與最近的距離沿用 #199：鏡頭在角色後方 13、上方 12 的方向
const NEAR_BACK = 13
const NEAR_UP = 12
const NEAREST_DISTANCE = Math.hypot(NEAR_BACK, NEAR_UP)
const DEFAULT_PITCH = Math.atan2(NEAR_UP, NEAR_BACK)
// 俯角的上下限：再低會看到地平線、山會擋住角色；再高就接近正上方
const MIN_PITCH = 25 * DEGREES_TO_RADIANS
const MAX_PITCH = 75 * DEGREES_TO_RADIANS
// 畫面比這個寬高比窄（直式手機）時，最近的距離往後拉，讓一個格子的寬度進到畫面；最多拉這麼多倍
const NEAREST_FIT_ASPECT = 1
const NEAREST_MAX_PULLBACK = 2.4
// 左右拖曳一個像素轉多少：拖 360 像素轉半圈
const ROTATE_RADIANS_PER_PIXEL = Math.PI / 360
// 上下拖曳一個像素改變多少俯角：拖 200 像素從最低到最高
const TILT_RADIANS_PER_PIXEL = (MAX_PITCH - MIN_PITCH) / 200
// 滾輪一個單位改變多少 zoom：一般滑鼠一格是 100，十格從最近到最遠
const WHEEL_ZOOM_PER_UNIT = 0.001
// 按下到放開移動不超過這麼多像素才算輕點
const TAP_SLOP_PIXELS = 8

const clamp01 = (value) => Math.min(1, Math.max(0, value))
const between = (a, b) => Math.hypot(a.x - b.x, a.y - b.y)

export function createCameraRig() {
  return { heading: 0, pitch: DEFAULT_PITCH, zoom: 0 }
}

/**
 * 拖曳：deltaX 是左右移動的像素（往右是正的），鏡頭左右轉，沒有角度上限；
 * deltaY 是上下移動的像素（往下是正的），往下拖鏡頭升高、更俯視，到了上下限就不再變
 */
export function rotateCamera(rig, deltaX, deltaY = 0) {
  const pitch = rig.pitch + deltaY * TILT_RADIANS_PER_PIXEL
  return {
    ...rig,
    heading: rig.heading - deltaX * ROTATE_RADIANS_PER_PIXEL,
    pitch: Math.min(MAX_PITCH, Math.max(MIN_PITCH, pitch)),
  }
}

/** 滾輪：deltaY 是正的（往自己滾）拉遠，負的拉近；到了上下限就不再變 */
export function zoomByWheel(rig, deltaY) {
  return { ...rig, zoom: clamp01(rig.zoom + deltaY * WHEEL_ZOOM_PER_UNIT) }
}

/** 雙指：scale 是兩指距離變成原來的幾倍。張開（大於 1）拉近，距離除以 scale；到了上下限就不再變 */
export function zoomByPinch(rig, scale, range) {
  const distance = cameraDistance(rig, range) / scale
  const zoom = Math.log(distance / range.nearest) / Math.log(range.farthest / range.nearest)
  return { ...rig, zoom: clamp01(zoom) }
}

/** 鏡頭離角色多遠：在最近與最遠之間等比例變化（每一格滾輪看起來縮放的幅度一樣） */
export function cameraDistance(rig, { nearest, farthest }) {
  return nearest * (farthest / nearest) ** rig.zoom
}

/** 一群點的外接範圍：{ center 左右、前後的正中間, radius 中心到最遠那一點的距離 } */
export function boundingCircle(points) {
  const xs = points.map((point) => point.x)
  const zs = points.map((point) => point.z)
  const center = {
    x: (Math.min(...xs) + Math.max(...xs)) / 2,
    z: (Math.min(...zs) + Math.max(...zs)) / 2,
  }
  const radius = Math.max(...points.map(({ x, z }) => Math.hypot(x - center.x, z - center.z)))
  return { center, radius }
}

/**
 * 鏡頭距離的上下限。aspect 是畫面的寬高比，viewRadius 是拉到最遠時要看得到的半徑（整座島），
 * pitch 是當下的俯角。
 * 最遠：以對準的位置為中心、半徑 viewRadius 的地面圓剛好整個在畫面裡（不論鏡頭轉到哪個角度）。
 */
export function cameraRange({ aspect, viewRadius: radius, pitch }) {
  const pullback = Math.min(NEAREST_MAX_PULLBACK, Math.max(1, NEAREST_FIT_ASPECT / aspect))
  const tanVertical = Math.tan((CAMERA_FIELD_OF_VIEW / 2) * DEGREES_TO_RADIANS)
  const tanHorizontal = tanVertical * aspect
  // 上下：最吃緊的是圓上離鏡頭最近的那一點；左右：圓的兩側
  const fitVertical = Math.sin(pitch) / tanVertical + Math.cos(pitch)
  const fitHorizontal = Math.sqrt(1 / tanHorizontal ** 2 + Math.cos(pitch) ** 2)
  return {
    nearest: NEAREST_DISTANCE * pullback,
    farthest: radius * Math.max(fitVertical, fitHorizontal),
  }
}

/**
 * 鏡頭對準地面上的哪裡：在角色 player 與島中心 islandCenter 之間，依鏡頭距離移動——
 * 最近時是角色、最遠時是島中心。這樣移動時，角色離對準位置的距離不會超過當下看得到的範圍，
 * 所以任何縮放程度下角色都在畫面裡。
 */
export function cameraTarget(rig, player, range, islandCenter) {
  const toIsland = (cameraDistance(rig, range) - range.nearest) / (range.farthest - range.nearest)
  // 寫成兩邊各乘比例再相加：拉到最遠（比例是 1）時剛好等於島的中心，不留浮點誤差
  return {
    x: player.x * (1 - toIsland) + islandCenter.x * toIsland,
    z: player.z * (1 - toIsland) + islandCenter.z * toIsland,
  }
}

/** 鏡頭的位置（看向地面上的 target）：{ x, y, z } */
export function cameraPlacement(rig, target, range) {
  const distance = cameraDistance(rig, range)
  const back = distance * Math.cos(rig.pitch)
  return {
    x: target.x + Math.sin(rig.heading) * back,
    y: distance * Math.sin(rig.pitch),
    z: target.z + Math.cos(rig.heading) * back,
  }
}

// ── 手勢 ──
// 一次手勢是從第一根手指（或滑鼠左鍵）按下到全部放開。mode：
//   idle   沒有手指
//   single 一根手指：移動超過 TAP_SLOP_PIXELS 之後是拖曳（轉鏡頭），否則放開時是輕點
//   pinch  兩根手指：縮放
//   spent  雙指放開過一根：剩下的手指（與之後再放上來的）都沒有作用，直到全部放開
// 事件：{ type: 'down' | 'move' | 'up' | 'cancel', id, x, y, button }；button 是滑鼠按鍵（觸控是 0）。
// reduceGesture 回傳 { state, effect }，effect 是 null、{ type: 'tap', x, y }、
// { type: 'rotate', deltaX, deltaY } 或 { type: 'zoom', scale }。

const LEFT_BUTTON = 0

export function createGesture() {
  return { mode: 'idle', pointers: {} }
}

function onDown(state, { id, x, y, button }) {
  if (button !== LEFT_BUTTON) {
    return { state, effect: null }
  }
  const pointers = { ...state.pointers, [id]: { x, y } }
  if (state.mode === 'idle') {
    const start = { mode: 'single', pointers, origin: { x, y }, last: { x, y }, dragged: false }
    return { state: start, effect: null }
  }
  if (state.mode === 'single') {
    const [first] = Object.keys(state.pointers)
    const pinch = {
      mode: 'pinch',
      pointers,
      pinchIds: [first, String(id)],
      pinchDistance: between(state.pointers[first], { x, y }),
    }
    return { state: pinch, effect: null }
  }
  return { state: { ...state, pointers }, effect: null }
}

function moveSingle(state, pointers, point) {
  const dragged = state.dragged || between(state.origin, point) > TAP_SLOP_PIXELS
  if (!dragged) {
    return { state: { ...state, pointers }, effect: null }
  }
  return {
    state: { ...state, pointers, dragged, last: point },
    effect: { type: 'rotate', deltaX: point.x - state.last.x, deltaY: point.y - state.last.y },
  }
}

function movePinch(state, pointers, id) {
  if (!state.pinchIds.includes(String(id))) {
    return { state: { ...state, pointers }, effect: null }
  }
  const [a, b] = state.pinchIds
  const pinchDistance = between(pointers[a], pointers[b])
  const effect =
    state.pinchDistance > 0 && pinchDistance > 0
      ? { type: 'zoom', scale: pinchDistance / state.pinchDistance }
      : null
  return { state: { ...state, pointers, pinchDistance }, effect }
}

function onMove(state, { id, x, y }) {
  if (!(id in state.pointers)) {
    return { state, effect: null }
  }
  const pointers = { ...state.pointers, [id]: { x, y } }
  if (state.mode === 'single') {
    return moveSingle(state, pointers, { x, y })
  }
  if (state.mode === 'pinch') {
    return movePinch(state, pointers, id)
  }
  return { state: { ...state, pointers }, effect: null }
}

/** 放開（up）或被系統取消（cancel）：只有沒拖曳過的單指放開才算輕點 */
function onRelease(state, { type, id }) {
  if (!(id in state.pointers)) {
    return { state, effect: null }
  }
  const pointers = Object.fromEntries(
    Object.entries(state.pointers).filter(([held]) => held !== String(id))
  )
  const isTap = state.mode === 'single' && type === 'up' && !state.dragged
  const effect = isTap ? { type: 'tap', x: state.origin.x, y: state.origin.y } : null
  if (Object.keys(pointers).length === 0) {
    return { state: createGesture(), effect }
  }
  const endsPinch = state.mode === 'pinch' && state.pinchIds.includes(String(id))
  return { state: endsPinch ? { mode: 'spent', pointers } : { ...state, pointers }, effect }
}

const GESTURE_HANDLERS = { down: onDown, move: onMove, up: onRelease, cancel: onRelease }

export function reduceGesture(state, event) {
  return GESTURE_HANDLERS[event.type](state, event)
}
