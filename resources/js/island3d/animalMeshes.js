// 魚島 3D：把島上的動物畫出來（#222 驗收條件 3–6）——載入模型、換毛色、依高度縮放、依 animals.js 擺位置與播動作，
// 玩家走近時頭上顯示族語名稱。動物不擋路、點不到（射線一律穿過）。
import * as THREE from 'three'
import { clone as cloneSkinned } from 'three/addons/utils/SkeletonUtils.js'
import { animalPlacement, islandAnimals, isNameVisible } from './animals'
import { ACTION_FADE_SECONDS } from './figures'
import { signMaterial } from './landmarkMeshes'
import { LABEL_ASPECT } from './terrain'

const MODEL_URL = (model) => `/island3d/animals/${model}.glb`
// 模型檔本來的正面：狗、貓朝 +x，豬、山羊朝 +z；轉成都朝 +z（和人物一樣，heading 0 是朝 +z）
const MODEL_TURN = { ino: -Math.PI / 2, cito: -Math.PI / 2, kois: 0, kagling: 0 }
const CLIPS = ['idle', 'walk']
// 量動作全程的最高、最低點時，每個動作取這麼多格
const EXTENT_SAMPLES = 24
// 名稱在動物最高點上方多高；名稱在畫面上的高度（比建築招牌小一點）
const LABEL_GAP = 0.3
const LABEL_SCREEN_HEIGHT = 0.024
// 離玩家超過這個距離的動物不更新動作（位置照算），省手機效能
const ACTIVE_DISTANCE = 40

/** 點不到：射線判斷一律略過 */
const ignoreRaycast = () => {}

/** 播 animations 裡的站著、走路兩個動作，各取多格，量整個模型（含骨架變形）的最低、最高點 */
function animatedExtent(model, animations) {
  const mixer = new THREE.AnimationMixer(model)
  const box = new THREE.Box3()
  CLIPS.forEach((name) => {
    const clip = THREE.AnimationClip.findByName(animations, name)
    const action = mixer.clipAction(clip).play()
    for (let i = 0; i <= EXTENT_SAMPLES; i++) {
      mixer.setTime((clip.duration * i) / EXTENT_SAMPLES)
      model.updateMatrixWorld(true)
      box.union(new THREE.Box3().setFromObject(model, true))
    }
    action.stop()
  })
  mixer.uncacheRoot(model)
  return { bottom: box.min.y, top: box.max.y }
}

/** 把模型縮放到兩個動作全程的最高點剛好是 height，最低點在地面（y = 0） */
export function fitAnimal(model, animations, height) {
  model.scale.setScalar(1)
  model.position.y = 0
  const { bottom, top } = animatedExtent(model, animations)
  const scale = height / (top - bottom)
  model.scale.setScalar(scale)
  model.position.y = -bottom * scale
}

/** 換毛色：coat 是材質名稱 → 顏色；同一個材質＋顏色只做一份 */
function dressCoat(model, coat, cache) {
  model.traverse((node) => {
    if (!node.isMesh || !(node.material.name in coat)) {
      return
    }
    const color = coat[node.material.name]
    const key = `${node.material.uuid}:${color}`
    if (!cache.has(key)) {
      const material = node.material.clone()
      material.color = new THREE.Color(color)
      cache.set(key, material)
    }
    node.material = cache.get(key)
  })
}

/** 一隻動物：body 放模型（載入前是空的、不顯示）、label 頭上的名稱；play 換動作、advance 推進動畫 */
function createFigure(animal, labelMaterial) {
  const group = new THREE.Group()
  const body = new THREE.Group()
  body.visible = false
  const label = new THREE.Sprite(labelMaterial(animal.name))
  label.scale.set(LABEL_SCREEN_HEIGHT * LABEL_ASPECT, LABEL_SCREEN_HEIGHT, 1)
  label.position.y = animal.height + LABEL_GAP
  label.visible = false
  label.raycast = ignoreRaycast
  group.add(body, label)
  let mixer = null
  let actions = null
  let current = null
  return {
    group,
    body,
    label,
    dress(model, animations) {
      model.traverse((node) => {
        node.raycast = ignoreRaycast
      })
      body.add(model)
      body.visible = true
      mixer = new THREE.AnimationMixer(model)
      actions = Object.fromEntries(
        CLIPS.map((name) => [
          name,
          mixer.clipAction(THREE.AnimationClip.findByName(animations, name)),
        ])
      )
    },
    play(pose) {
      if (!actions || current === pose) {
        return
      }
      actions[current]?.fadeOut(ACTION_FADE_SECONDS)
      actions[pose].reset().fadeIn(ACTION_FADE_SECONDS).play()
      current = pose
    },
    advance(deltaSeconds) {
      mixer?.update(deltaSeconds)
    },
  }
}

/** 每種模型載入一次；載入成功後依每隻的高度縮放、換毛色，複製給用這款的每一隻；失敗就不畫那一種 */
function loadModels(loader, animals, figures) {
  const cache = new Map()
  new Set(animals.map((animal) => animal.model)).forEach((model) =>
    loader.load(
      MODEL_URL(model),
      (gltf) =>
        animals.forEach((animal, i) => {
          if (animal.model === model) {
            const copy = cloneSkinned(gltf.scene)
            fitAnimal(copy, gltf.animations, animal.height)
            copy.rotation.y = MODEL_TURN[model]
            dressCoat(copy, animal.coat, cache)
            figures[i].dress(copy, gltf.animations)
          }
        }),
      undefined,
      (error) => console.warn(`魚島 3D：動物模型 ${model} 載入失敗，不畫這一種`, error)
    )
  )
}

/**
 * 島上的動物。field 是地形高度表；labelMaterial(name) 做名稱的材質（預設同建築招牌）。
 * 回傳 { group 要加進場景的東西, update(seconds, deltaSeconds, { player, reducedMotion }), figures 每一隻的 { body, label } }。
 * 名稱只在那隻動物已經畫出來、而且玩家在 4 以內時顯示。
 */
export function createAnimals(
  loader,
  { animals = islandAnimals(), field, labelMaterial = signMaterial } = {}
) {
  const group = new THREE.Group()
  // 同一個名稱共用一份材質（4 種名稱，不必每隻各畫一張字）
  const materials = new Map()
  const sharedLabel = (name) => {
    if (!materials.has(name)) {
      materials.set(name, labelMaterial(name))
    }
    return materials.get(name)
  }
  const figures = animals.map((animal) => createFigure(animal, sharedLabel))
  group.add(...figures.map((figure) => figure.group))
  loadModels(loader, animals, figures)

  return {
    group,
    figures,
    update(seconds, deltaSeconds, { player, reducedMotion = false }) {
      animals.forEach((animal, i) => {
        const figure = figures[i]
        const { position, heading, pose } = animalPlacement(animal, seconds, {
          field,
          reducedMotion,
        })
        figure.group.position.set(position.x, position.y, position.z)
        figure.body.rotation.y = heading
        figure.label.visible = figure.body.visible && isNameVisible(position, player)
        figure.play(pose)
        if (Math.hypot(position.x - player.x, position.z - player.z) <= ACTIVE_DISTANCE) {
          figure.advance(deltaSeconds)
        }
      })
    },
  }
}
