// 魚島 3D：把一個部落格子畫出來（#199）。這個檔只管繪圖與接輸入；
// 走路、邊界、畫質、NPC 走動的規則在同資料夾的純函式模組裡。
import * as THREE from 'three'
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js'
import { createFrameClock } from './frameClock'
import { ISLAND_MAP, WALK_SPEED } from './islandMap'
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
import { createWalkable } from './walkable'
import { wanderPosition, wanderRoute } from './wander'

// 場景尺寸（單位是 three.js 的世界單位）
const HEX_RADIUS = ISLAND_MAP.hexRadius
const HEX_SIDES = 6
const HEX_THICKNESS = 0.6
const SEA_RADIUS = 80
const SEA_LEVEL = -0.35
// #200 第 1 步：整座島還沒畫出來，先把 #199 的單一格子擺在島上第一個部落的位置
const HOME_CENTER = ISLAND_MAP.tribes[0].center
const PLAYER_START = { x: HOME_CENTER.x, z: HOME_CENTER.z + 2 }
const ACTOR_HEIGHT = 1.7
const BOAT_LENGTH = 3
const BOAT_POSITION = { x: 4.5, z: 8.5 }
const BOAT_BOB_HEIGHT = 0.06
const BOAT_HEADING = Math.PI / 5
const SEA_SEGMENTS = 48
const MOUNTAIN_SIDES = 6
const MOUNTAIN_BASE_SIDES = 7
// 動作切換的淡入淡出秒數
const ACTION_FADE_SECONDS = 0.2
// 鏡頭在角色斜上方的位置
const CAMERA_OFFSET = { x: 0, y: 12, z: 13 }
// 鏡頭看向角色前方（畫面遠處）一點，讓後面的山進到畫面裡
const CAMERA_LOOK_AHEAD = 2
// 畫面比這個寬高比窄（直式手機）時鏡頭往後拉，讓整個格子的寬度進到畫面；最多拉這麼多倍
const CAMERA_FIT_ASPECT = 1
const CAMERA_MAX_PULLBACK = 2.4
const CAMERA_FIELD_OF_VIEW = 45
const CAMERA_NEAR = 0.1
const CAMERA_FAR = 300
// 山：格子後方的幾座圓錐（x、z 是位置，radius、height 是大小）
const MOUNTAINS = [
  { x: -3.5, z: -10.5, radius: 3.2, height: 5.5 },
  { x: 1.5, z: -11.5, radius: 4, height: 7.5 },
  { x: 6, z: -10, radius: 2.8, height: 4.5 },
]
// 山腳的陸地比部落格子低一點，兩塊重疊的地方才不會閃爍
const MOUNTAIN_BASE = { x: 1.5, z: -10.5, radius: 8, drop: 0.12 }

// 顏色沿用魚島的色票（tailwind.config.js 的 island）
const COLORS = {
  sky: '#e6f6fa',
  sea: '#8fd0e6',
  land: '#8fd19e',
  landEdge: '#5fae73',
  mountain: '#4f9a63',
  player: '#c2410c',
  npc: '#f2a7a0',
}

const MODELS = {
  player: '/island3d/characters/character-male-a.glb',
  npc: '/island3d/characters/character-female-a.glb',
  boat: '/island3d/boats/boat-fishing-small.glb',
}

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

function flatMesh(geometry, color) {
  return new THREE.Mesh(geometry, new THREE.MeshLambertMaterial({ color, flatShading: true }))
}

// CylinderGeometry 預設第一個頂點朝 +z；轉四分之一圈讓頂點朝 +x，和 hexBounds 一致
const HEX_VERTEX_TOWARD_X = Math.PI / 2

/** 部落格子：平頂六角柱，頂面在 y = 0，頂點方向和 hexBounds 一致（+x） */
function buildHex() {
  const geometry = new THREE.CylinderGeometry(
    HEX_RADIUS,
    HEX_RADIUS,
    HEX_THICKNESS,
    HEX_SIDES,
    1,
    false,
    HEX_VERTEX_TOWARD_X
  )
  const hex = new THREE.Mesh(geometry, [
    new THREE.MeshLambertMaterial({ color: COLORS.landEdge, flatShading: true }),
    new THREE.MeshLambertMaterial({ color: COLORS.land, flatShading: true }),
    new THREE.MeshLambertMaterial({ color: COLORS.landEdge, flatShading: true }),
  ])
  hex.position.y = -HEX_THICKNESS / 2
  return hex
}

function buildSea() {
  const sea = flatMesh(new THREE.CircleGeometry(SEA_RADIUS, SEA_SEGMENTS), COLORS.sea)
  sea.rotation.x = -Math.PI / 2
  sea.position.y = SEA_LEVEL
  return sea
}

/** 山：一塊陸地加幾座圓錐，只是裝飾 */
function buildMountains() {
  const group = new THREE.Group()
  const base = flatMesh(
    new THREE.CylinderGeometry(
      MOUNTAIN_BASE.radius,
      MOUNTAIN_BASE.radius,
      HEX_THICKNESS,
      MOUNTAIN_BASE_SIDES
    ),
    COLORS.landEdge
  )
  base.position.set(MOUNTAIN_BASE.x, -HEX_THICKNESS / 2 - MOUNTAIN_BASE.drop, MOUNTAIN_BASE.z)
  group.add(base)
  MOUNTAINS.forEach(({ x, z, radius, height }) => {
    const cone = flatMesh(new THREE.ConeGeometry(radius, height, MOUNTAIN_SIDES), COLORS.mountain)
    cone.position.set(x, height / 2, z)
    group.add(cone)
  })
  return group
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

/** 把模型縮放到指定大小：size 是目標值，axis 是量哪一邊（'y' 高度、'longest' 最長邊） */
function fitModel(model, size, axis) {
  const box = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3())
  const current = axis === 'y' ? box.y : Math.max(box.x, box.z)
  model.scale.setScalar(size / current)
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

function buildBoat(loader) {
  const boat = new THREE.Group()
  boat.position.set(BOAT_POSITION.x, SEA_LEVEL, BOAT_POSITION.z)
  boat.rotation.y = BOAT_HEADING
  loader.load(
    MODELS.boat,
    (gltf) => {
      fitModel(gltf.scene, BOAT_LENGTH, 'longest')
      boat.add(gltf.scene)
    },
    undefined,
    (error) => console.warn('魚島 3D：船的模型載入失敗', error)
  )
  return boat
}

/** 畫面上的一點（滑鼠或手指）對到地面（y = 0）的哪裡；沒對到地面時回傳 null */
function groundPointAt(event, renderer, camera) {
  const rect = renderer.domElement.getBoundingClientRect()
  const pointer = new THREE.Vector2(
    ((event.clientX - rect.left) / rect.width) * 2 - 1,
    -((event.clientY - rect.top) / rect.height) * 2 + 1
  )
  const raycaster = new THREE.Raycaster()
  raycaster.setFromCamera(pointer, camera)
  const hit = raycaster.ray.intersectPlane(
    new THREE.Plane(new THREE.Vector3(0, 1, 0), 0),
    new THREE.Vector3()
  )
  return hit ? { x: hit.x, z: hit.z } : null
}

/**
 * 接鍵盤、點擊與觸控；state.input 由 movement.js 的規則更新。
 * 視窗失去焦點或分頁切換時清掉輸入並呼叫 onLeave。回傳解除監聽的函式
 */
function bindInput(state, renderer, camera, onLeaveView) {
  const onKeyDown = (event) => {
    const next = pressKey(state.input, event.key)
    if (next !== state.input) {
      event.preventDefault() // 方向鍵不要捲動頁面
      state.input = next
    }
  }
  const onKeyUp = (event) => (state.input = releaseKey(state.input, event.key))
  const onPointerDown = (event) => {
    const point = groundPointAt(event, renderer, camera)
    if (point) {
      state.input = tapGround(state.input, point, state.position, state.world)
    }
  }
  const onLeave = () => {
    state.input = clearInput()
    onLeaveView()
  }

  window.addEventListener('keydown', onKeyDown)
  window.addEventListener('keyup', onKeyUp)
  window.addEventListener('blur', onLeave)
  document.addEventListener('visibilitychange', onLeave)
  renderer.domElement.addEventListener('pointerdown', onPointerDown)

  return () => {
    window.removeEventListener('keydown', onKeyDown)
    window.removeEventListener('keyup', onKeyUp)
    window.removeEventListener('blur', onLeave)
    document.removeEventListener('visibilitychange', onLeave)
    renderer.domElement.removeEventListener('pointerdown', onPointerDown)
  }
}

/** 船在海面上輕輕上下晃；「減少動態效果」時不動 */
function bobBoat(boat, elapsedSeconds, reducedMotion) {
  if (!reducedMotion) {
    boat.position.y = SEA_LEVEL + Math.sin(elapsedSeconds) * BOAT_BOB_HEIGHT
  }
}

/** 鏡頭固定在角色斜上方、跟著角色；畫面窄時拉遠 */
function followPlayer(camera, position) {
  const pullback = Math.min(CAMERA_MAX_PULLBACK, Math.max(1, CAMERA_FIT_ASPECT / camera.aspect))
  camera.position.set(
    position.x + CAMERA_OFFSET.x * pullback,
    CAMERA_OFFSET.y * pullback,
    position.z + CAMERA_OFFSET.z * pullback
  )
  camera.lookAt(position.x, 0, position.z - CAMERA_LOOK_AHEAD)
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

/** 場景裡的東西：海、部落格子、山、船、玩家與 NPC */
function buildWorld() {
  const loader = new GLTFLoader()
  const scene = new THREE.Scene()
  scene.background = new THREE.Color(COLORS.sky)
  const player = createActor(loader, MODELS.player, COLORS.player)
  const npc = createActor(loader, MODELS.npc, COLORS.npc)
  const boat = buildBoat(loader)
  buildLights(scene)
  // 不會動的東西與 NPC 都以部落格子的中心為原點
  const home = new THREE.Group()
  home.position.set(HOME_CENTER.x, 0, HOME_CENTER.z)
  home.add(buildSea(), buildHex(), buildMountains(), boat, npc.group)
  scene.add(home, player.group)
  return { scene, player, npc, boat }
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

/** 每張畫面依輸入移動玩家；state 是 { input, position, world } */
function movePlayer(player, state, deltaSeconds) {
  const before = state.position
  const next = stepMovement(state.input, before, deltaSeconds, {
    speed: WALK_SPEED,
    world: state.world,
  })
  state.input = next.input
  state.position = next.position
  const step = stepBetween(before, next.position)
  player.update(next.position, hasMoved(step) ? step : null, isMoving(next.input), deltaSeconds)
}

/** NPC 沿自己的路線來回走；回傳每張畫面呼叫的函式 */
function createNpcMover(npc, reducedMotion) {
  const route = wanderRoute(HEX_RADIUS)
  let position = wanderPosition(0, route, { reducedMotion })
  return (elapsedSeconds, deltaSeconds) => {
    const next = wanderPosition(elapsedSeconds, route, { reducedMotion })
    const step = stepBetween(position, next)
    position = next
    npc.update(next, hasMoved(step) ? step : null, hasMoved(step), deltaSeconds)
  }
}

/**
 * 在 container 裡畫出部落格子並開始動。瀏覽器無法建立 3D 畫面時丟出例外。
 * options: { reducedMotion 系統設定「減少動態效果」, onStats({ level, framesPerSecond }) }
 * 回傳 { dispose } 給離開頁面時停掉。
 */
export function createIslandScene(container, { reducedMotion = false, onStats = () => {} } = {}) {
  const level = initialQuality({ width: window.innerWidth, userAgent: navigator.userAgent })
  const renderer = buildRenderer(container, level)
  const governor = createQualityGovernor(level)
  const reportStats = createStatsReporter(onStats, governor)
  const camera = new THREE.PerspectiveCamera(
    CAMERA_FIELD_OF_VIEW,
    container.clientWidth / container.clientHeight,
    CAMERA_NEAR,
    CAMERA_FAR
  )
  const { scene, player, npc, boat } = buildWorld()
  const walkable = createWalkable(ISLAND_MAP)
  const world = { ...walkable, findRoute: createRouter(ISLAND_MAP, walkable) }
  const state = { input: createMovementInput(), position: { ...PLAYER_START }, world }
  const unwatchResize = watchResize(container, renderer, camera)
  const moveNpc = createNpcMover(npc, reducedMotion)
  const clock = createFrameClock()
  // 失焦或切分頁時暫停時鐘：回來的第一張重新開始，不把離開的時間算進去
  const unbindInput = bindInput(state, renderer, camera, clock.pause)

  renderer.setAnimationLoop((now) => {
    const frame = clock.tick(now, document.hidden)
    if (frame === null) {
      return // 分頁在背景：不移動、不繪圖
    }
    movePlayer(player, state, frame.deltaSeconds)
    moveNpc(frame.elapsedSeconds, frame.deltaSeconds)
    bobBoat(boat, frame.elapsedSeconds, reducedMotion)
    followPlayer(camera, state.position)
    renderer.render(scene, camera)

    const lowered = sampleFrame(governor, now, frame)
    if (lowered) {
      renderer.setPixelRatio(pixelRatioFor(lowered, window.devicePixelRatio))
    }
    if (!frame.interrupted) {
      reportStats(frame.frameMs)
    }
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
