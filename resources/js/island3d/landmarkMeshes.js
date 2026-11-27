// 魚島 3D：把部落的地標與招牌畫出來（#207、#212）。地標先用程式拼的造型（landmarks.js 的零件），
// 清單裡有填模型檔的，載入成功後換上，失敗就留著程式拼的。全部是裝飾，不擋路、不擋點擊。
import * as THREE from 'three'
import { LANDMARKS, shapeParts, signHeight, signsVisible } from './landmarks'
import { INLAND_LEVEL, LABEL_ASPECT, fitModelToFootprint, flatMesh, labelTexture } from './terrain'

// 四角錐的底是邊長 1 的正方形：ConeGeometry 的半徑是中心到頂點
const PYRAMID_RADIUS = Math.SQRT1_2
const PYRAMID_SIDES = 4
// 招牌在畫面上這麼大（畫面高度的比例；比部落標示小）
const SIGN_SCREEN_HEIGHT = 0.03

// 平放的圓框的原型：外徑 1、高 1。管子的半徑是外徑的這個比例
const RING_TUBE = 0.1
const RING_TUBE_SIDES = 6
const RING_SEGMENTS = 16

/** 各種形狀邊長 1 的原型；沒列到的（盒子、平面、柱子）都是長方體 */
const UNIT_GEOMETRY = {
  roof: () => new THREE.ConeGeometry(PYRAMID_RADIUS, 1, PYRAMID_SIDES).rotateY(Math.PI / 4),
  // TorusGeometry 立在 x–y 平面、厚度是管子的直徑：先把厚度拉成 1，再放平
  ring: () =>
    new THREE.TorusGeometry(0.5 - RING_TUBE, RING_TUBE, RING_TUBE_SIDES, RING_SEGMENTS)
      .scale(1, 1, 1 / (RING_TUBE * 2))
      .rotateX(Math.PI / 2),
}

/** 一個零件：原型縮放成零件的寬、高、深 */
function buildPart({ shape, width, height, depth, x, y, z, color }) {
  const geometry = UNIT_GEOMETRY[shape]?.() ?? new THREE.BoxGeometry(1, 1, 1)
  const part = flatMesh(geometry, color)
  part.scale.set(width, height, depth)
  part.position.set(x, y, z)
  return part
}

/** 程式拼的造型，中心在原點、站在 y = 0 */
function buildShape({ kind, width, depth }) {
  const shape = new THREE.Group()
  shape.add(...shapeParts(kind, width, depth).map(buildPart))
  return shape
}

/** 地標：站在公路旁的陸地上。先放程式拼的造型；有模型檔就載入，成功後換上，失敗留著程式拼的 */
function buildLandmark(loader, landmark) {
  const group = new THREE.Group()
  group.position.set(landmark.x, INLAND_LEVEL, landmark.z)
  group.rotation.y = landmark.heading
  const shape = buildShape(landmark)
  group.add(shape)
  if (landmark.model) {
    loader.load(
      landmark.model,
      (gltf) => {
        fitModelToFootprint(gltf.scene, landmark.width, landmark.depth)
        group.remove(shape)
        group.add(gltf.scene)
      },
      undefined,
      (error) =>
        console.warn(`魚島 3D：「${landmark.sign}」的模型載入失敗，改用程式拼的造型`, error)
    )
  }
  return group
}

/** 招牌：地標最高點上方的文字，永遠正面朝鏡頭；畫在部落標示之前，所以不會蓋住部落標示 */
function buildSign(landmark) {
  const { sign, x, z } = landmark
  const material = new THREE.SpriteMaterial({
    map: labelTexture(sign),
    sizeAttenuation: false,
    depthTest: false,
  })
  const sprite = new THREE.Sprite(material)
  sprite.position.set(x, INLAND_LEVEL + signHeight(landmark), z)
  sprite.scale.set(SIGN_SCREEN_HEIGHT * LABEL_ASPECT, SIGN_SCREEN_HEIGHT, 1)
  return sprite
}

/** 鏡頭拉遠超過門檻時把招牌藏起來（zoom：0 最近、1 最遠） */
export function showSigns(signs, zoom) {
  signs.visible = signsVisible(zoom)
}

/**
 * 島上所有的地標。landmarks 預設是 landmarks.js 的清單。
 * 回傳 { group 要加進場景的東西, signs 招牌（給 showSigns 用） }
 */
export function buildLandmarks(loader, landmarks = LANDMARKS) {
  const group = new THREE.Group()
  const signs = new THREE.Group()
  signs.add(...landmarks.map(buildSign))
  group.add(...landmarks.map((landmark) => buildLandmark(loader, landmark)), signs)
  return { group, signs }
}
