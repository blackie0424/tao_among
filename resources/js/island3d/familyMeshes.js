// 魚島 3D：把 72 位家人畫出來（#201 驗收條件 5–8）——載入人物模型、換衣服配色、依 family.js 擺位置與播動作。
import * as THREE from 'three'
import { clone as cloneSkinned } from 'three/addons/utils/SkeletonUtils.js'
import { PALETTE_SWAPS, animationSeconds, familyMembers, memberPlacement } from './family'
import { ACTION_FADE_SECONDS, ACTOR_HEIGHT, placeholderFigure } from './figures'
import { fitModel } from './terrain'

const MODEL_URL = (model) => `/island3d/characters/character-${model}.glb`
// 貼圖的色塊：8 欄 × 4 列
const PALETTE_COLUMNS = 8
const PALETTE_ROWS = 4
const TRIANGLE_CORNERS = 3
// 簡單人形的顏色（模型載入前）
const PLACEHOLDER_COLOR = '#8a6d4b'
// 點家人的判斷範圍：比人物略大的看不見的圓柱，手機上比較好點
const HIT_RADIUS = 0.6
const HIT_HEIGHT = ACTOR_HEIGHT + 0.2
// 用到的動作：走動的家人 idle、walk；akay 的坐姿
const CLIPS = ['idle', 'walk', 'sit', 'wheelchair-look-left', 'wheelchair-look-right']

/**
 * 換衣服配色：把落在「原本的色塊」裡的三角形整個搬到「換成的色塊」（UV 平移整數個色塊）。
 * 先拆成每個三角形各自擁有頂點，搬一個三角形才不會拉到旁邊共用頂點的三角形。回傳新的形體，原本的不動。
 */
export function recolorGeometry(geometry, swaps) {
  const recolored = geometry.index ? geometry.toNonIndexed() : geometry.clone()
  const uv = recolored.getAttribute('uv')
  for (let first = 0; first < uv.count; first += TRIANGLE_CORNERS) {
    const corners = [first, first + 1, first + 2]
    const average = (read) => corners.reduce((sum, i) => sum + read(i), 0) / TRIANGLE_CORNERS
    const cell = [
      Math.floor(average((i) => uv.getX(i)) * PALETTE_COLUMNS),
      Math.floor(average((i) => uv.getY(i)) * PALETTE_ROWS),
    ]
    const swap = swaps.find(([from]) => from[0] === cell[0] && from[1] === cell[1])
    if (swap) {
      const du = (swap[1][0] - cell[0]) / PALETTE_COLUMNS
      const dv = (swap[1][1] - cell[1]) / PALETTE_ROWS
      corners.forEach((i) => uv.setXY(i, uv.getX(i) + du, uv.getY(i) + dv))
    }
  }
  uv.needsUpdate = true
  return recolored
}

/** 複製一份模型並換上配色；同一個形體＋配色只換一次（72 位共用） */
function dressedCopy(gltf, palette, cache) {
  const figure = cloneSkinned(gltf.scene)
  if (palette > 0) {
    figure.traverse((node) => {
      if (node.isMesh) {
        const key = `${node.geometry.uuid}:${palette}`
        if (!cache.has(key)) {
          cache.set(key, recolorGeometry(node.geometry, PALETTE_SWAPS[palette]))
        }
        node.geometry = cache.get(key)
      }
    })
  }
  return figure
}

/** 點家人用的圓柱：不畫出來，只給射線判斷 */
function hitArea() {
  const area = new THREE.Mesh(
    new THREE.CylinderGeometry(HIT_RADIUS, HIT_RADIUS, HIT_HEIGHT),
    new THREE.MeshBasicMaterial({ visible: false })
  )
  area.position.y = HIT_HEIGHT / 2
  return area
}

/** 一位家人：先放簡單人形，模型載入後換上；play(pose) 切換動作 */
function createMemberFigure() {
  const group = new THREE.Group()
  const figure = new THREE.Group()
  const hit = hitArea()
  figure.add(placeholderFigure(PLACEHOLDER_COLOR))
  group.add(figure, hit)
  let mixer = null
  let actions = null
  let current = null
  return {
    group,
    hit,
    /** 目前的動作（模型載入前是 null） */
    get pose() {
      return current
    },
    dress(model, animations) {
      figure.clear()
      figure.add(model)
      mixer = new THREE.AnimationMixer(model)
      actions = Object.fromEntries(
        CLIPS.map((name) => [
          name,
          mixer.clipAction(THREE.AnimationClip.findByName(animations, name)),
        ])
      )
      current = null
    },
    /** 換動作；回傳這次有沒有換 */
    play(pose) {
      if (!actions || current === pose) {
        return false
      }
      actions[current]?.fadeOut(ACTION_FADE_SECONDS)
      actions[pose].reset().fadeIn(ACTION_FADE_SECONDS).play()
      current = pose
      return true
    },
    advance(deltaSeconds) {
      mixer?.update(deltaSeconds)
    },
  }
}

/** 每款模型載入一次，載入後換給用這款的每一位家人（同一個形體＋配色共用） */
function loadModels(loader, members, figures) {
  const cache = new Map()
  const dressEveryone = (model, gltf) => {
    fitModel(gltf.scene, ACTOR_HEIGHT, 'y')
    members.forEach((member, i) => {
      if (member.look.model === model) {
        figures[i].dress(dressedCopy(gltf, member.look.palette, cache), gltf.animations)
      }
    })
  }
  new Set(members.map((member) => member.look.model)).forEach((model) =>
    loader.load(
      MODEL_URL(model),
      (gltf) => dressEveryone(model, gltf),
      undefined,
      (error) => console.warn(`魚島 3D：家人模型 ${model} 載入失敗，改用簡單人形`, error)
    )
  )
}

/**
 * 玩家在不在這位家人的範圍裡（#219）：在的話停下、面向玩家；akay 只停住坐姿、不轉身。
 * placement 是 memberPlacement 的結果；回傳要畫的 { position, heading, pose }。
 */
function facePlayer(member, placement, player) {
  if (member.seat) {
    return placement
  }
  const { position } = placement
  return {
    position,
    heading: Math.atan2(player.x - position.x, player.z - position.z),
    pose: 'idle',
  }
}

/**
 * 72 位家人。回傳 { group 要加進場景的東西, figures 每位家人的人形,
 *   update(seconds, deltaSeconds, { currentTribe, reducedMotion, spotKey, player }),
 *   isTapped(raycaster, spot) 點到的是不是 spot 那一件的家人（spot 是 spotKey）,
 *   facingFor(spot, from) 玩家停下時該朝哪位家人 }。
 * 只有所在部落的家人走動、換姿勢、更新動畫；其他部落的停在範圍中心（akay 正面坐），換完動作就不再更新。
 * spotKey 是玩家目前在哪一件的範圍裡、player 是玩家位置（#219）：那一件的家人停下面向玩家。
 * 每位家人有自己的走路時鐘：停下的那幾張畫面時鐘不前進，離開後從停下的地方接著走、不會跳。
 */
export function createFamily(loader, members = familyMembers()) {
  const group = new THREE.Group()
  const figures = members.map(() => createMemberFigure())
  group.add(...figures.map((figure) => figure.group))
  loadModels(loader, members, figures)
  const stopClocks = members.map(() => createStopClock())

  return {
    group,
    figures,
    isTapped(raycaster, spot) {
      const i = members.findIndex((member) => member.spot === spot)
      return i !== -1 && raycaster.intersectObject(figures[i].hit).length > 0
    },
    /** 玩家站在 from、在 spot 那一件的範圍裡時該朝的方向（指向那位家人）；不在範圍裡回傳 null（#219 規格修訂 1） */
    facingFor(spot, from) {
      const i = spot === null ? -1 : members.findIndex((member) => member.spot === spot)
      if (i === -1) {
        return null
      }
      const { position } = figures[i].group
      return { x: position.x - from.x, z: position.z - from.z }
    },
    update(seconds, deltaSeconds, { currentTribe, reducedMotion, spotKey = null, player }) {
      members.forEach((member, i) => {
        const active = member.tribe === currentTribe
        const stopped = active && spotKey !== null && member.spot === spotKey
        const stoppedSeconds = stopClocks[i](stopped, deltaSeconds)
        const placement = memberPlacement(member, seconds - stoppedSeconds, {
          active,
          reducedMotion,
        })
        showMember(
          figures[i],
          stopped ? facePlayer(member, placement, player) : placement,
          deltaSeconds,
          { active, reducedMotion }
        )
      })
    },
  }
}

/**
 * 一位家人的停下計時（#219）：回傳 tick(stopped, deltaSeconds) → 到目前為止停下過多少秒（走路時鐘＝seconds 減這個）。
 * 進入那一張與離開那一張都算停下，位置才會和停下時一樣。
 */
function createStopClock() {
  let stoppedSeconds = 0
  let wasStopped = false
  return (stopped, deltaSeconds) => {
    if (stopped || wasStopped) {
      stoppedSeconds += deltaSeconds
    }
    wasStopped = stopped
    return stoppedSeconds
  }
}

/** 把一位家人擺到 { position, heading, pose }，並把動畫往前推 */
function showMember(figure, { position, heading, pose }, deltaSeconds, { active, reducedMotion }) {
  figure.group.position.set(position.x, 0, position.z)
  figure.group.rotation.y = heading
  const changed = figure.play(pose)
  figure.advance(
    animationSeconds(deltaSeconds, {
      active,
      reducedMotion,
      changed,
      fadeSeconds: ACTION_FADE_SECONDS,
    })
  )
}
