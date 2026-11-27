// 魚島 3D：把部落的地標與招牌畫出來（#207、#212）。地標先用程式拼的造型（landmarks.js 的零件），
// 清單裡有填模型檔的，載入成功後換上，失敗就留著程式拼的。全部是裝飾，不擋路、不擋點擊。
import * as THREE from 'three'
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js'
import { HOUSEHOLD_KIND_ORDER, HOUSEHOLD_KINDS, HOUSEHOLDS, householdSigns } from './households'
import { LANDMARKS, shapeParts, signHeight, signsVisible } from './landmarks'
import { INLAND_LEVEL, LABEL_ASPECT, fitModelToFootprint, labelTexture } from './terrain'

// 四角錐的底是邊長 1 的正方形：ConeGeometry 的半徑是中心到頂點
const PYRAMID_RADIUS = Math.SQRT1_2
const PYRAMID_SIDES = 4
// 招牌在畫面上這麼大（畫面高度的比例）
const SIGN_SCREEN_HEIGHT = 0.03

// 平放的圓框：管子的半徑是外徑的這個比例
const RING_TUBE = 0.1
const RING_TUBE_SIDES = 6
const RING_SEGMENTS = 16
// 圓柱、半球繞一圈分這麼多段（4 的倍數，寬與深才會剛好是 1）
const ROUND_SEGMENTS = 12
const DOME_RINGS = 4
// 陽傘：一圈 8 瓣，兩組各 4 瓣交錯
const UMBRELLA_PANELS = 8

/** 把形體平移、縮放到剛好填滿邊長 1、中心在原點的方塊 */
function fitUnitCube(geometry) {
  geometry.computeBoundingBox()
  const { min, max } = geometry.boundingBox
  const size = max.clone().sub(min)
  const center = max.clone().add(min).multiplyScalar(0.5)
  return geometry
    .translate(-center.x, -center.y, -center.z)
    .scale(1 / size.x, 1 / size.y, 1 / size.z)
}

/** 陽傘的一組瓣（first 是 0 或 1：從第幾瓣開始，每隔一瓣取一瓣），尖端朝上 */
function umbrellaPanels(first) {
  const panelAngle = (Math.PI * 2) / UMBRELLA_PANELS
  const panels = Array.from(
    { length: UMBRELLA_PANELS / 2 },
    (_, i) => new THREE.ConeGeometry(0.5, 1, 1, 1, true, (first + i * 2) * panelAngle, panelAngle)
  )
  return fitUnitCube(mergeGeometries(panels))
}

/** 各種形狀邊長 1 的原型；沒列到的（盒子、平面、柱子）都是長方體 */
const UNIT_GEOMETRY = {
  roof: () => new THREE.ConeGeometry(PYRAMID_RADIUS, 1, PYRAMID_SIDES).rotateY(Math.PI / 4),
  // TorusGeometry 立在 x–y 平面：放平後拉成剛好邊長 1（管子是六角形，不拉的話厚度只有約 0.87）
  ring: () =>
    fitUnitCube(
      new THREE.TorusGeometry(0.5 - RING_TUBE, RING_TUBE, RING_TUBE_SIDES, RING_SEGMENTS).rotateX(
        Math.PI / 2
      )
    ),
  cylinder: () => new THREE.CylinderGeometry(0.5, 0.5, 1, ROUND_SEGMENTS),
  dome: () =>
    fitUnitCube(
      new THREE.SphereGeometry(0.5, ROUND_SEGMENTS, DOME_RINGS, 0, Math.PI * 2, 0, Math.PI / 2)
    ),
  // 三角柱：CylinderGeometry 的三角截面頂點朝 +z；先把軸轉到 x，再繞 x 轉讓頂點朝上，屋脊就在正中間
  gable: () =>
    fitUnitCube(
      new THREE.CylinderGeometry(0.5, 0.5, 1, 3).rotateZ(Math.PI / 2).rotateX(-Math.PI / 2)
    ),
  umbrella: () => umbrellaPanels(0),
  'umbrella-alt': () => umbrellaPanels(1),
}

// 圓柱的軸從 y 轉到 x 或 z
const TURN_AXIS = {
  x: (geometry) => geometry.rotateZ(Math.PI / 2),
  y: (geometry) => geometry,
  z: (geometry) => geometry.rotateX(Math.PI / 2),
}

/** 這種形狀邊長 1、中心在原點的原型；axis 是圓柱的軸朝哪一邊（預設 y） */
export function unitGeometry(shape, axis = 'y') {
  const geometry = UNIT_GEOMETRY[shape]?.() ?? new THREE.BoxGeometry(1, 1, 1)
  return TURN_AXIS[axis](geometry)
}

// 陽傘是沒有底的錐面，從下面看也要畫得出來
const DOUBLE_SIDED = new Set(['umbrella', 'umbrella-alt'])

/** 零件的材質：有 opacity 的是半透明（玻璃） */
function partMaterial({ shape, color, opacity }) {
  return new THREE.MeshLambertMaterial({
    color,
    flatShading: true,
    side: DOUBLE_SIDED.has(shape) ? THREE.DoubleSide : THREE.FrontSide,
    ...(opacity === undefined ? {} : { transparent: true, opacity }),
  })
}

/** 一個零件：原型縮放成零件的寬、高、深 */
function buildPart(part) {
  const { shape, axis, width, height, depth, x, y, z } = part
  const mesh = new THREE.Mesh(unitGeometry(shape, axis), partMaterial(part))
  mesh.scale.set(width, height, depth)
  mesh.position.set(x, y, z)
  return mesh
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

/** 招牌的材質：文字貼圖，永遠正面朝鏡頭、大小不隨遠近改變、不被建物擋住 */
const signMaterial = (text) =>
  new THREE.SpriteMaterial({ map: labelTexture(text), sizeAttenuation: false, depthTest: false })

/** 一塊招牌：放在 (x, 離地 height, z) */
function signSprite(material, x, height, z) {
  const sprite = new THREE.Sprite(material)
  sprite.position.set(x, INLAND_LEVEL + height, z)
  sprite.scale.set(SIGN_SCREEN_HEIGHT * LABEL_ASPECT, SIGN_SCREEN_HEIGHT, 1)
  return sprite
}

/** 招牌：地標最高點上方的文字 */
function buildSign(landmark) {
  return signSprite(signMaterial(landmark.sign), landmark.x, signHeight(landmark), landmark.z)
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

// ── 家屋四件（#201）──
// 同一種東西有 18 份：零件先合成一個網格（每個頂點帶零件的顏色），再用 InstancedMesh 一次畫完

/** 一個零件的形體：原型縮放、移到位置，帶上顏色；只留位置、法線、顏色，合併時才對得上 */
function coloredPart({ shape, axis, width, height, depth, x, y, z, color }) {
  const unit = unitGeometry(shape, axis)
  const geometry = (unit.index ? unit.toNonIndexed() : unit).scale(width, height, depth)
  geometry.translate(x, y, z)
  Object.keys(geometry.attributes)
    .filter((name) => name !== 'position' && name !== 'normal')
    .forEach((name) => geometry.deleteAttribute(name))
  const { r, g, b } = new THREE.Color(color)
  const count = geometry.getAttribute('position').count
  const colors = Float32Array.from({ length: count * 3 }, (_, i) => [r, g, b][i % 3])
  geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3))
  return geometry
}

/** 一種東西的原型：中心在原點、站在 y = 0、正面朝 +z */
function householdGeometry(kind) {
  const { width, depth } = HOUSEHOLD_KINDS[kind]
  return mergeGeometries(shapeParts(kind, width, depth).map(coloredPart))
}

/** 18 戶的家屋四件：每種東西一個 InstancedMesh（名稱 household-種類），第 i 份是第 i 戶的那一件 */
export function buildHouseholds(households = HOUSEHOLDS) {
  const group = new THREE.Group()
  const placement = new THREE.Object3D()
  HOUSEHOLD_KIND_ORDER.forEach((kind, k) => {
    const mesh = new THREE.InstancedMesh(
      householdGeometry(kind),
      new THREE.MeshLambertMaterial({ vertexColors: true, flatShading: true }),
      households.length
    )
    mesh.name = `household-${kind}`
    households.forEach(({ items }, i) => {
      const item = items[k]
      placement.position.set(item.x, INLAND_LEVEL, item.z)
      placement.rotation.set(0, item.heading, 0)
      placement.updateMatrix()
      mesh.setMatrixAt(i, placement.matrix)
    })
    group.add(mesh)
  })
  return group
}

/**
 * 四件上方的名稱招牌（#201 驗收條件 15）：名稱用後端給的地點清單 spots，做法和地標的招牌一樣，
 * 同一個名稱共用一份材質。回傳招牌的群組（給 showSigns 用）
 */
export function buildHouseholdSigns(spots, households = HOUSEHOLDS) {
  const materials = new Map()
  const materialOf = (text) => {
    if (!materials.has(text)) {
      materials.set(text, signMaterial(text))
    }
    return materials.get(text)
  }
  const signs = new THREE.Group()
  householdSigns(spots, households).forEach(({ text, x, z, height }) =>
    signs.add(signSprite(materialOf(text), x, height, z))
  )
  return signs
}
