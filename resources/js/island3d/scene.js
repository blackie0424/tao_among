// 魚島 3D：把整座島畫出來並接上輸入（#199、#200）。這個檔只管繪圖與接輸入；
// 島的資料、可走範圍、找路、走路、鏡頭、手勢、畫質的規則在同資料夾的純函式模組裡，
// 不會動的地形在 terrain.js。
import * as THREE from 'three'
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js'
import {
  CAMERA_FIELD_OF_VIEW,
  cameraDistance,
  cameraPlacement,
  boundingCircle,
  cameraRange,
  cameraTarget,
  createCameraRig,
  createGesture,
  reduceGesture,
  rotateCamera,
  zoomByPinch,
  zoomByWheel,
} from './cameraRig'
import { createFrameClock } from './frameClock'
import { ISLAND_DECOR, ISLAND_MAP, WALK_SPEED } from './islandMap'
import {
  clearInput,
  createMovementInput,
  isMoving,
  pressKey,
  releaseKey,
  stepMovement,
  tapGround,
} from './movement'
import { createQualityGovernor, initialQuality, pixelRatioFor, sampleFrame } from './quality'
import { createRouter } from './route'
import { bobBoats, buildTerrain, fitModel, flatMesh, sizeTribeLabels } from './terrain'
import { createWalkable } from './walkable'

const ACTOR_HEIGHT = 1.7
// 動作切換的淡入淡出秒數
const ACTION_FADE_SECONDS = 0.2
// 整座島的範圍（海岸線以內）：鏡頭拉到最遠時對準它的中心、整個看得到
const ISLAND_BOUNDS = boundingCircle(ISLAND_DECOR.coast)
// 鏡頭看得到的最近、最遠距離：最近的隨鏡頭遠近調整（拉遠時重疊的地面才不會閃爍）
const CAMERA_NEAR_RATIO = 0.02
const CAMERA_MIN_NEAR = 0.1
const CAMERA_FAR = 8000
// 滾輪以「行」為單位回報時（部分瀏覽器），一行當成這麼多像素
const WHEEL_LINE_MODE = 1
const WHEEL_LINE_PIXELS = 33

const COLORS = { sky: '#e6f6fa', sea: '#8fd0e6', player: '#c2410c' }
const PLAYER_MODEL = '/island3d/characters/character-male-a.glb'

// 燈光
const SKY_LIGHT_INTENSITY = 1.6
const SUN_INTENSITY = 1.8
const SUN_POSITION = { x: 6, y: 12, z: 8 }
// 簡單人形的半徑與身體長度，各佔人物高度的比例
const PLACEHOLDER_RADIUS_RATIO = 0.22
const PLACEHOLDER_BODY_RATIO = 0.5

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

/** 模型還沒載入（或載入失敗）時先用的簡單人形，走路照常 */
function placeholderFigure(color) {
  const figure = new THREE.Group()
  const body = flatMesh(
    new THREE.CapsuleGeometry(
      ACTOR_HEIGHT * PLACEHOLDER_RADIUS_RATIO,
      ACTOR_HEIGHT * PLACEHOLDER_BODY_RATIO
    ),
    color
  )
  body.position.y = ACTOR_HEIGHT / 2
  figure.add(body)
  return figure
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

/** 畫面上的一點（clientX、clientY）對到地面（y = 0）的哪裡；沒對到地面時回傳 null */
function groundPointAt({ x, y }, renderer, camera) {
  const rect = renderer.domElement.getBoundingClientRect()
  const pointer = new THREE.Vector2(
    ((x - rect.left) / rect.width) * 2 - 1,
    -((y - rect.top) / rect.height) * 2 + 1
  )
  const raycaster = new THREE.Raycaster()
  raycaster.setFromCamera(pointer, camera)
  const hit = raycaster.ray.intersectPlane(
    new THREE.Plane(new THREE.Vector3(0, 1, 0), 0),
    new THREE.Vector3()
  )
  return hit ? { x: hit.x, z: hit.z } : null
}

const rangeOf = (camera) => cameraRange({ aspect: camera.aspect, viewRadius: ISLAND_BOUNDS.radius })

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

/** 手勢判斷出來的結果：輕點＝走過去、拖曳＝轉鏡頭、雙指＝縮放 */
function applyGesture(state, effect, renderer, camera) {
  if (effect === null) {
    return
  }
  if (effect.type === 'rotate') {
    state.rig = rotateCamera(state.rig, effect.deltaX)
  } else if (effect.type === 'zoom') {
    state.rig = zoomByPinch(state.rig, effect.scale, rangeOf(camera))
  } else {
    const point = groundPointAt(effect, renderer, camera)
    if (point) {
      state.input = tapGround(state.input, point, state.position, state.world)
    }
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

/** 鏡頭最近時看著角色，拉遠時移向島的中心；角度與距離來自 state.rig */
function followPlayer(camera, state) {
  const range = rangeOf(camera)
  const target = cameraTarget(state.rig, state.position, range, ISLAND_BOUNDS.center)
  const placement = cameraPlacement(state.rig, target, range)
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

/** 場景裡的東西：整座島（terrain.js）與玩家 */
function buildWorld() {
  const loader = new GLTFLoader()
  const scene = new THREE.Scene()
  scene.background = new THREE.Color(COLORS.sky)
  const player = createActor(loader, PLAYER_MODEL, COLORS.player)
  const terrain = buildTerrain(loader)
  buildLights(scene)
  scene.add(terrain.group, player.group)
  return { scene, player, boats: terrain.boats, labels: terrain.labels }
}

function buildCamera(container) {
  return new THREE.PerspectiveCamera(
    CAMERA_FIELD_OF_VIEW,
    container.clientWidth / container.clientHeight,
    CAMERA_MIN_NEAR,
    CAMERA_FAR
  )
}

/**
 * 玩家一開始的狀態：站在 startTribe 部落格子的中心、沒有輸入、鏡頭沒轉且在最近的距離。
 * world 是這座島的走路規則（可走範圍＋找路）；tribe 是最後走進的部落
 */
function createPlayerState(startTribe) {
  const walkable = createWalkable(ISLAND_MAP)
  const { center } = ISLAND_MAP.tribes.find((tribe) => tribe.code === startTribe)
  return {
    input: createMovementInput(),
    position: { ...center },
    world: { ...walkable, findRoute: createRouter(ISLAND_MAP, walkable) },
    rig: createCameraRig(),
    gesture: createGesture(),
    tribe: startTribe,
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
function movePlayer(player, state, deltaSeconds, onTribeChange) {
  const before = state.position
  const next = stepMovement(state.input, before, deltaSeconds, {
    speed: WALK_SPEED,
    heading: state.rig.heading,
    world: state.world,
  })
  state.input = next.input
  state.position = next.position
  const step = stepBetween(before, next.position)
  player.update(next.position, hasMoved(step) ? step : null, isMoving(next.input), deltaSeconds)

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
 *   onTribeChange(部落代碼) 角色走進另一個部落的格子時呼叫
 * }
 * 回傳 { dispose } 給離開頁面時停掉。
 */
export function createIslandScene(container, options) {
  const {
    startTribe,
    reducedMotion = false,
    onStats = () => {},
    onTribeChange = () => {},
  } = options
  const level = initialQuality({ width: window.innerWidth, userAgent: navigator.userAgent })
  const renderer = buildRenderer(container, level)
  const governor = createQualityGovernor(level)
  const reportStats = createStatsReporter(onStats, governor)
  const camera = buildCamera(container)
  const { scene, player, boats, labels } = buildWorld()
  const state = createPlayerState(startTribe)
  const unwatchResize = watchResize(container, renderer, camera)
  const clock = createFrameClock()
  // 失焦或切分頁時暫停時鐘：回來的第一張重新開始，不把離開的時間算進去
  const unbindInput = bindInput(state, renderer, camera, clock.pause)

  renderer.setAnimationLoop((now) => {
    const frame = clock.tick(now, document.hidden)
    if (frame === null) {
      return // 分頁在背景：不移動、不繪圖
    }
    movePlayer(player, state, frame.deltaSeconds, onTribeChange)
    if (!reducedMotion) {
      bobBoats(boats, frame.elapsedSeconds)
    }
    followPlayer(camera, state)
    sizeTribeLabels(labels, state.rig.zoom)
    renderer.render(scene, camera)
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
