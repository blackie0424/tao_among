// 魚島 3D：把整座島畫出來並接上輸入（#199、#200、#207）。這個檔只管繪圖與接輸入；
// 島的資料、可走範圍、找路、走路、鏡頭、手勢、畫質的規則在同資料夾的純函式模組裡，
// 不會動的地形在 terrain.js。
import * as THREE from 'three'
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js'
import {
  CAMERA_FIELD_OF_VIEW,
  CHEST_HEIGHT,
  cameraDistance,
  cameraPlacement,
  boundingCircle,
  cameraRange,
  cameraTarget,
  createCameraRig,
  createGesture,
  labelScale,
  reduceGesture,
  rotateCamera,
  terrainClearPitch,
  zoomBeyondEntry,
  zoomByPinch,
  zoomByWheel,
} from './cameraRig'
import { createCameraOcclusion } from './cameraOcclusion'
import { nameLabelsOf, scaleNameLabels } from './nameLabels'
import { createAnimals } from './animalMeshes'
import { createFamily } from './familyMeshes'
import { ACTION_FADE_SECONDS, ACTOR_HEIGHT, placeholderFigure } from './figures'
import { createFrameClock } from './frameClock'
import { ISLAND_DECOR, ISLAND_MAP, WALK_SPEED } from './islandMap'
import { householdObstacles, spotAt, spotKey } from './households'
import { islandHeightField } from './terrainHeight'
import { buildHouseholdSigns, buildHouseholds, buildLandmarks, showSigns } from './landmarkMeshes'
import {
  clearInput,
  createMovementInput,
  destinationOf,
  isMoving,
  pressKey,
  releaseKey,
  stepMovement,
  tapGround,
} from './movement'
import { isBlockedByTerrain, isTapBlocked, tapBlockers } from './occlusion'
import { createQualityGovernor, initialQuality, pixelRatioFor, sampleFrame } from './quality'
import { createRouter } from './route'
import { createTapMarker, markerScale, rejectTap, rejectedPointAt, tapOutcome } from './tapMarker'
import { bobBoats, buildTerrain, fitModel } from './terrain'
import { createWalkable } from './walkable'

// 整座島的範圍（海岸線以內）：鏡頭拉到最遠時對準它的中心、整個看得到
const ISLAND_BOUNDS = boundingCircle(ISLAND_DECOR.coast)
// 山與船：點到它們沒有反應，就算畫面上它們後面是可走的地面
const TAP_BLOCKERS = tapBlockers(ISLAND_DECOR)
// 鏡頭看得到的最近、最遠距離：最近的隨鏡頭遠近調整（拉遠時重疊的地面才不會閃爍）
const CAMERA_NEAR_RATIO = 0.02
const CAMERA_MIN_NEAR = 0.1
const CAMERA_FAR = 8000
// 滾輪以「行」為單位回報時（部分瀏覽器），一行當成這麼多像素
const WHEEL_LINE_MODE = 1
const WHEEL_LINE_PIXELS = 33

// destination 是魚島的重點色（tailwind.config.js 的 island.accent）
const COLORS = {
  sky: '#e6f6fa',
  sea: '#8fd0e6',
  player: '#c2410c',
  destination: '#c2410c',
  rejected: '#4b5563',
}
// 點地面的提示：貼在地面上的圓圈（鏡頭在最近的距離時的大小）
const TAP_RING = { inner: 0.55, outer: 0.85, segments: 32, level: 0.1, opacity: 0.9 }
const PLAYER_MODEL = '/island3d/characters/character-male-a.glb'

// 燈光
const SKY_LIGHT_INTENSITY = 1.6
const SUN_INTENSITY = 1.8
const SUN_POSITION = { x: 6, y: 12, z: 8 }

const STATS_INTERVAL_MS = 500
const MS_PER_SECOND = 1000

function buildRenderer(container, level) {
  // 瀏覽器無法建立 3D 畫面時，這裡會丟出例外，由呼叫端顯示說明文字
  const renderer = new THREE.WebGLRenderer({ antialias: true })
  renderer.setPixelRatio(pixelRatioFor(level, window.devicePixelRatio))
  renderer.setSize(container.clientWidth, container.clientHeight)
  renderer.domElement.style.display = 'block'
  container.appendChild(renderer.domElement)
  return renderer
}

function buildLights(scene) {
  scene.add(new THREE.HemisphereLight('#ffffff', COLORS.sea, SKY_LIGHT_INTENSITY))
  const sun = new THREE.DirectionalLight('#ffffff', SUN_INTENSITY)
  sun.position.set(SUN_POSITION.x, SUN_POSITION.y, SUN_POSITION.z)
  scene.add(sun)
}

/**
 * 會走路的人物：先放簡單人形，模型載入後換上，並依是否在走切換動作。
 * 回傳 { group, update(position, facing, walking, deltaSeconds) }
 */
function createActor(loader, url, color) {
  const group = new THREE.Group()
  let figure = placeholderFigure(color)
  group.add(figure)
  let mixer = null
  let actions = null
  let current = null

  loader.load(
    url,
    (gltf) => {
      fitModel(gltf.scene, ACTOR_HEIGHT, 'y')
      group.remove(figure)
      figure = gltf.scene
      group.add(figure)
      mixer = new THREE.AnimationMixer(figure)
      const clip = (name) => THREE.AnimationClip.findByName(gltf.animations, name)
      actions = { idle: mixer.clipAction(clip('idle')), walk: mixer.clipAction(clip('walk')) }
    },
    undefined,
    (error) => console.warn('魚島 3D：人物模型載入失敗，改用簡單人形', error)
  )

  function play(name) {
    if (!actions || current === name) {
      return
    }
    actions[current]?.fadeOut(ACTION_FADE_SECONDS)
    actions[name].reset().fadeIn(ACTION_FADE_SECONDS).play()
    current = name
  }

  return {
    group,
    update(position, facing, walking, deltaSeconds) {
      group.position.set(position.x, 0, position.z)
      if (facing) {
        group.rotation.y = Math.atan2(facing.x, facing.z)
      }
      play(walking ? 'walk' : 'idle')
      mixer?.update(deltaSeconds)
    },
  }
}

/** 從鏡頭穿過畫面上一點（clientX、clientY）的射線 */
function rayThrough({ x, y }, renderer, camera) {
  const rect = renderer.domElement.getBoundingClientRect()
  const pointer = new THREE.Vector2(
    ((x - rect.left) / rect.width) * 2 - 1,
    -((y - rect.top) / rect.height) * 2 + 1
  )
  const raycaster = new THREE.Raycaster()
  raycaster.setFromCamera(pointer, camera)
  return raycaster
}

/** 畫面上的一點（clientX、clientY）對到地面（y = 0）的哪裡；沒對到地面時回傳 null */
function groundPointAt(point, renderer, camera) {
  const hit = rayThrough(point, renderer, camera).ray.intersectPlane(
    new THREE.Plane(new THREE.Vector3(0, 1, 0), 0),
    new THREE.Vector3()
  )
  return hit ? { x: hit.x, z: hit.z } : null
}

const rangeOf = (camera, rig) =>
  cameraRange({ aspect: camera.aspect, viewRadius: ISLAND_BOUNDS.radius, pitch: rig.pitch })

/** 接方向鍵；state.input 由 movement.js 的規則更新。回傳解除監聽的函式 */
function bindKeys(state) {
  const onKeyDown = (event) => {
    const next = pressKey(state.input, event.key)
    if (next !== state.input) {
      event.preventDefault() // 方向鍵不要捲動頁面
      state.input = next
    }
  }
  const onKeyUp = (event) => (state.input = releaseKey(state.input, event.key))

  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('keyup', onKeyUp)
  return () => {
    window.removeEventListener('keydown', onKeyDown)
    window.removeEventListener('keyup', onKeyUp)
  }
}

/**
 * 輕點地面上的 point（沒對到地面是 null）：可以走就設路線；
 * 點到山、船或其他不能走的地方就在那裡出現灰色圓圈，角色不動
 */
function handleTap(state, point, camera) {
  if (point === null) {
    return
  }
  const before = state.input
  const blocked =
    isTapBlocked(camera.position, point, TAP_BLOCKERS) ||
    isBlockedByTerrain(camera.position, point, islandHeightField())
  state.input = blocked ? before : tapGround(before, point, state.position, state.world)
  if (tapOutcome(before, state.input) === 'rejected') {
    state.marker = rejectTap(state.marker, point, state.elapsedSeconds)
  }
}

/** 手勢判斷出來的結果：輕點＝走過去、拖曳＝轉鏡頭（左右轉、上下改俯角）、雙指＝縮放 */
function applyGesture(state, effect, renderer, camera) {
  if (effect === null) {
    return
  }
  if (effect.type === 'rotate') {
    state.rig = rotateCamera(state.rig, effect.deltaX, effect.deltaY)
  } else if (effect.type === 'zoom') {
    state.rig = zoomByPinch(state.rig, effect.scale, rangeOf(camera, state.rig))
  } else if (!state.tapMember(rayThrough(effect, renderer, camera))) {
    handleTap(state, groundPointAt(effect, renderer, camera), camera)
  }
}

/** 接滑鼠、觸控與滾輪；手勢由 cameraRig.js 的規則判斷。回傳解除監聽的函式 */
function bindPointer(state, renderer, camera) {
  const canvas = renderer.domElement
  const onPointer = (type) => (event) => {
    if (type === 'down') {
      canvas.setPointerCapture?.(event.pointerId) // 拖出畫布也收得到放開
    }
    const next = reduceGesture(state.gesture, {
      type,
      id: event.pointerId,
      x: event.clientX,
      y: event.clientY,
      button: event.pointerType === 'mouse' ? event.button : 0,
    })
    state.gesture = next.state
    applyGesture(state, next.effect, renderer, camera)
  }
  const onWheel = (event) => {
    event.preventDefault() // 滾輪用來縮放，不要捲動頁面
    const pixels = event.deltaMode === WHEEL_LINE_MODE ? WHEEL_LINE_PIXELS : 1
    state.rig = zoomByWheel(state.rig, event.deltaY * pixels)
  }
  const listeners = {
    pointerdown: onPointer('down'),
    pointermove: onPointer('move'),
    pointerup: onPointer('up'),
    pointercancel: onPointer('cancel'),
    wheel: onWheel,
  }

  Object.entries(listeners).forEach(([name, listener]) =>
    canvas.addEventListener(name, listener, { passive: false })
  )
  return () =>
    Object.entries(listeners).forEach(([name, listener]) =>
      canvas.removeEventListener(name, listener)
    )
}

/**
 * 接所有輸入。視窗失去焦點或分頁切換時清掉輸入與進行中的手勢，並呼叫 onLeaveView。
 * 回傳解除監聽的函式
 */
function bindInput(state, renderer, camera, onLeaveView) {
  const unbindKeys = bindKeys(state)
  const unbindPointer = bindPointer(state, renderer, camera)
  const onLeave = () => {
    state.input = clearInput()
    state.gesture = createGesture()
    onLeaveView()
  }

  window.addEventListener('blur', onLeave)
  document.addEventListener('visibilitychange', onLeave)
  return () => {
    unbindKeys()
    unbindPointer()
    window.removeEventListener('blur', onLeave)
    document.removeEventListener('visibilitychange', onLeave)
  }
}

/** 貼在地面上的圓圈；不被山擋住 */
function buildTapRing(color) {
  const geometry = new THREE.RingGeometry(TAP_RING.inner, TAP_RING.outer, TAP_RING.segments)
  geometry.rotateX(-Math.PI / 2)
  const material = new THREE.MeshBasicMaterial({
    color,
    transparent: true,
    opacity: TAP_RING.opacity,
    depthTest: false,
  })
  const ring = new THREE.Mesh(geometry, material)
  ring.renderOrder = 2
  ring.visible = false
  return ring
}

/** 圓圈放到 point；point 是 null 就藏起來 */
function placeTapRing(ring, point, scale) {
  ring.visible = point !== null
  if (point !== null) {
    ring.position.set(point.x, TAP_RING.level, point.z)
    ring.scale.setScalar(scale)
  }
}

/**
 * 點地面的提示：橘色圓圈在目的地，灰色圓圈在剛才點到的不能走的地方。
 * 回傳 { group, update(state, camera) }，每張畫面呼叫 update
 */
function createTapRings() {
  const destination = buildTapRing(COLORS.destination)
  const rejected = buildTapRing(COLORS.rejected)
  const group = new THREE.Group()
  group.add(destination, rejected)
  return {
    group,
    update(state, camera) {
      const range = rangeOf(camera, state.rig)
      // 以進入時的距離為基準，同一個鏡頭距離下大小和 #228 以前一樣
      const scale = markerScale(cameraDistance(state.rig, range), range.entry)
      placeTapRing(destination, destinationOf(state.input), scale)
      placeTapRing(rejected, rejectedPointAt(state.marker, state.elapsedSeconds), scale)
    },
  }
}

/**
 * 鏡頭在進入時的距離以內看著角色，拉遠時移向島的中心；角度與距離來自 state.rig。
 * 山擋住角色時，這張畫面的俯角抬高（#228 驗收條件 6），state.rig 不變
 */
function followPlayer(camera, state) {
  const range = rangeOf(camera, state.rig)
  const target = cameraTarget(state.rig, state.position, range, ISLAND_BOUNDS.center)
  const pitch = terrainClearPitch(state.rig, range, state.position, islandHeightField())
  const placement = cameraPlacement({ ...state.rig, pitch }, target, range)
  camera.position.set(placement.x, placement.y, placement.z)
  camera.lookAt(target.x, 0, target.z)
  camera.near = Math.max(CAMERA_MIN_NEAR, cameraDistance(state.rig, range) * CAMERA_NEAR_RATIO)
  camera.updateProjectionMatrix()
}

/** 每隔一小段時間回報一次目前畫質與每秒張數（給 ?debug=1 顯示） */
function createStatsReporter(onStats, governor) {
  let elapsedMs = 0
  let frames = 0
  return (frameMs) => {
    elapsedMs += frameMs
    frames += 1
    if (elapsedMs < STATS_INTERVAL_MS) {
      return
    }
    onStats({
      level: governor.level,
      framesPerSecond: Math.round((frames * MS_PER_SECOND) / elapsedMs),
    })
    elapsedMs = 0
    frames = 0
  }
}

/** 畫完一張之後：畫面變慢就降畫質，並回報目前的畫質與每秒張數 */
function trackQuality(renderer, governor, reportStats, now, frame) {
  const lowered = sampleFrame(governor, now, frame)
  if (lowered) {
    renderer.setPixelRatio(pixelRatioFor(lowered, window.devicePixelRatio))
  }
  if (!frame.interrupted) {
    reportStats(frame.frameMs)
  }
}

/** 場景裡的東西：整座島（terrain.js）、地標與家屋四件（landmarkMeshes.js）、家人（familyMeshes.js）、動物（animalMeshes.js）、玩家、點地面的提示 */
function buildWorld(spots) {
  const loader = new GLTFLoader()
  const scene = new THREE.Scene()
  scene.background = new THREE.Color(COLORS.sky)
  const player = createActor(loader, PLAYER_MODEL, COLORS.player)
  const terrain = buildTerrain(loader)
  const landmarks = buildLandmarks(loader)
  const tapRings = createTapRings()
  const family = createFamily(loader)
  const animals = createAnimals(loader, { field: islandHeightField() })
  const householdSigns = buildHouseholdSigns(spots)
  const households = buildHouseholds()
  // 擋住角色時變半透明的：家屋四件與每個地標（招牌不算，#228 驗收條件 5）
  const occlusion = createCameraOcclusion({
    households: households.children,
    landmarks: landmarks.group.children.filter((child) => child !== landmarks.signs),
  })
  buildLights(scene)
  scene.add(
    terrain.group,
    landmarks.group,
    households,
    occlusion.group,
    householdSigns,
    family.group,
    animals.group,
    player.group,
    tapRings.group
  )
  return {
    scene,
    player,
    family,
    animals,
    tapRings,
    occlusion,
    boats: terrain.boats,
    signs: [landmarks.signs, householdSigns],
    // 拉近時放大的名字標示（#228 驗收條件 4）
    labels: nameLabelsOf({ landmarks, householdSigns, animals }),
  }
}

function buildCamera(container) {
  return new THREE.PerspectiveCamera(
    CAMERA_FIELD_OF_VIEW,
    container.clientWidth / container.clientHeight,
    CAMERA_MIN_NEAR,
    CAMERA_FAR
  )
}

/** 角色走進或離開某一件的範圍時呼叫 onSpotChange（{ tribe, hex, slot } 或 null）（#201） */
function reportSpot(state, onSpotChange) {
  const spot = spotAt(state.position)
  const key = spotKey(spot)
  if (key !== state.spotKey) {
    state.spotKey = key
    onSpotChange(spot)
  }
}

/** 點家人：點到目前範圍那一件的家人就呼叫 onMemberTap 並回傳 true（不走過去），否則回傳 false */
function memberTapper(state, family, onMemberTap) {
  return (raycaster) => {
    const tapped = state.spotKey !== null && family.isTapped(raycaster, state.spotKey)
    if (tapped) {
      onMemberTap()
    }
    return tapped
  }
}

/** 每張畫面要更新的東西：玩家、家人、動物、船、鏡頭、標示與招牌、點地面的提示 */
function advanceWorld(world, state, camera, frame, { reducedMotion, onTribeChange, onSpotChange }) {
  const { family, animals, tapRings, occlusion, boats, signs, labels } = world
  state.elapsedSeconds = frame.elapsedSeconds
  movePlayer(world, state, frame.deltaSeconds, onTribeChange)
  reportSpot(state, onSpotChange)
  family.update(frame.elapsedSeconds, frame.deltaSeconds, {
    currentTribe: state.tribe,
    reducedMotion,
    spotKey: state.spotKey,
    player: state.position,
  })
  animals.update(frame.elapsedSeconds, frame.deltaSeconds, {
    player: state.position,
    reducedMotion,
  })
  if (!reducedMotion) {
    bobBoats(boats, frame.elapsedSeconds)
  }
  followPlayer(camera, state)
  occlusion.update({ ...state.position, y: CHEST_HEIGHT }, camera.position)
  const range = rangeOf(camera, state.rig)
  // 以進入時的距離為基準換算，同一個鏡頭距離下招牌顯示和 #228 以前一樣
  signs.forEach((group) => showSigns(group, zoomBeyondEntry(state.rig, range)))
  scaleNameLabels(labels, labelScale(state.rig, range))
  tapRings.update(state, camera)
}

/**
 * 玩家一開始的狀態：站在 startTribe 部落主格的中心、沒有輸入、鏡頭沒轉且在進入時的距離（aspect 是畫面寬高比）。
 * world 是這座島的走路規則（可走範圍＋找路）；tribe 是最後走進的部落；spotKey 是目前在哪一件的範圍裡；
 * marker 是點地面的提示；elapsedSeconds 是開始到現在經過的秒數（每張畫面更新）
 */
function createPlayerState(startTribe, aspect) {
  // 家屋四件擋路（#201）
  const walkable = createWalkable(ISLAND_MAP, householdObstacles())
  const { center } = ISLAND_MAP.tribes.find((tribe) => tribe.code === startTribe)
  return {
    input: createMovementInput(),
    position: { ...center },
    world: { ...walkable, findRoute: createRouter(ISLAND_MAP, walkable) },
    rig: createCameraRig({ aspect, viewRadius: ISLAND_BOUNDS.radius }),
    gesture: createGesture(),
    marker: createTapMarker(),
    elapsedSeconds: 0,
    tribe: startTribe,
    spotKey: null,
    // 點到目前範圍那一件的家人時回傳 true（開對話、不走過去）；在 createIslandScene 接上
    tapMember: () => false,
  }
}

/** 畫布跟著容器大小變；回傳停止監看的函式 */
function watchResize(container, renderer, camera) {
  const observer = new ResizeObserver(() => {
    renderer.setSize(container.clientWidth, container.clientHeight)
    camera.aspect = container.clientWidth / container.clientHeight
    camera.updateProjectionMatrix()
  })
  observer.observe(container)
  return () => observer.disconnect()
}

const stepBetween = (from, to) => ({ x: to.x - from.x, z: to.z - from.z })
const hasMoved = (step) => step.x !== 0 || step.z !== 0

/** 每張畫面依輸入移動玩家；走進另一個部落的格子時呼叫 onTribeChange(部落代碼) */
function movePlayer({ player, family }, state, deltaSeconds, onTribeChange) {
  const before = state.position
  const next = stepMovement(state.input, before, deltaSeconds, {
    speed: WALK_SPEED,
    heading: state.rig.heading,
    world: state.world,
  })
  state.input = next.input
  state.position = next.position
  const step = stepBetween(before, next.position)
  // 沒在走時，在某一件的範圍裡就轉向那位家人，兩個人面對面（#219 規格修訂 1）
  const facing = hasMoved(step) ? step : family.facingFor(state.spotKey, next.position)
  player.update(next.position, facing, isMoving(next.input), deltaSeconds)

  // 走在公路上（不在任何格子裡）時，所在部落不變
  const tribe = state.world.tribeAt(next.position)
  if (tribe !== null && tribe !== state.tribe) {
    state.tribe = tribe
    onTribeChange(tribe)
  }
}

/**
 * 在 container 裡畫出整座島並開始動。瀏覽器無法建立 3D 畫面時丟出例外。
 * options: {
 *   startTribe 角色一開始所在的部落代碼,
 *   reducedMotion 系統設定「減少動態效果」,
 *   onStats({ level, framesPerSecond }),
 *   onTribeChange(部落代碼) 角色走進另一個部落的格子時呼叫,
 *   onSpotChange({ tribe, hex, slot } 或 null) 角色走進或離開某一件的範圍時呼叫（#201）,
 *   onMemberTap() 點到目前範圍那一件的家人時呼叫（#201 規格修訂 2）,
 *   spots 後端給的地點清單（部落 → 12 個地點），四件上方招牌的名稱用它
 * }
 * 回傳 { dispose } 給離開頁面時停掉。
 */
export function createIslandScene(container, options) {
  const {
    startTribe,
    reducedMotion = false,
    onStats = () => {},
    onTribeChange = () => {},
    onSpotChange = () => {},
    onMemberTap = () => {},
    spots = {},
  } = options
  const level = initialQuality({ width: window.innerWidth, userAgent: navigator.userAgent })
  const renderer = buildRenderer(container, level)
  const governor = createQualityGovernor(level)
  const reportStats = createStatsReporter(onStats, governor)
  const camera = buildCamera(container)
  const world = buildWorld(spots)
  const state = createPlayerState(startTribe, camera.aspect)
  state.tapMember = memberTapper(state, world.family, onMemberTap)
  const unwatchResize = watchResize(container, renderer, camera)
  const clock = createFrameClock()
  // 失焦或切分頁時暫停時鐘：回來的第一張重新開始，不把離開的時間算進去
  const unbindInput = bindInput(state, renderer, camera, clock.pause)

  renderer.setAnimationLoop((now) => {
    const frame = clock.tick(now, document.hidden)
    if (frame === null) {
      return // 分頁在背景：不移動、不繪圖
    }
    advanceWorld(world, state, camera, frame, { reducedMotion, onTribeChange, onSpotChange })
    renderer.render(world.scene, camera)
    trackQuality(renderer, governor, reportStats, now, frame)
  })

  return {
    dispose() {
      renderer.setAnimationLoop(null)
      unwatchResize()
      unbindInput()
      renderer.dispose()
      renderer.domElement.remove()
    },
  }
}
