// 魚島 3D：把整座島不會動的部分畫出來（#200、#207）——海、沙灘、陸地、部落格子與標示、公路、山、水田、船。
// 位置都讀 islandMap.js；這個檔只管繪圖。
import * as THREE from 'three'
import { islandTribeLabel } from '@/constants/island'
import { ISLAND_DECOR, ISLAND_MAP, mountainColor, roadSegments } from './islandMap'

const HEX_SIDES = 6
const HEX_THICKNESS = 0.6
// 海很大：鏡頭拉到最遠也看不到邊
const SEA_RADIUS = 3000
const SEA_SEGMENTS = 64
const SEA_LEVEL = -0.35
// 陸地比部落格子低一點、公路比格子高一點，重疊的地方才不會閃爍
const LAND_LEVEL = -0.12
const LAND_THICKNESS = 0.6
// 沙灘以內的陸地蓋在沙灘上面一點點；公路旁的地標站在這個高度
export const INLAND_LEVEL = LAND_LEVEL + 0.02
// 水田再高一點點，才不會和陸地疊在同一層
const PADDY_LEVEL = INLAND_LEVEL + 0.03
// 水田四周留這麼寬的田埂
const PADDY_RIDGE = 0.3
// 水面比田埂高這麼一點點，才不會疊在同一層
const PADDY_WATER_LIFT = 0.01
const ROAD_LEVEL = 0.05
const ROAD_JOINT_SEGMENTS = 16
const MOUNTAIN_SIDES = 6
const BOAT_LENGTH = 3
const BOAT_BOB_HEIGHT = 0.06
const BOAT_MODEL = '/island3d/boats/boat-fishing-small.glb'

// 部落標示：畫在一張小畫布上貼成看板，永遠正面朝鏡頭、大小不隨鏡頭遠近改變
const LABEL_CANVAS = { width: 512, height: 128 }
// 標示的寬高比（招牌沿用同一張貼圖的比例）
export const LABEL_ASPECT = LABEL_CANVAS.width / LABEL_CANVAS.height
const LABEL_FONT = '800 56px system-ui, "PingFang TC", "Noto Sans TC", sans-serif'
const LABEL_CORNER = 28
// 看板高度佔畫面高度的比例大約是這個值 ÷ 0.83
const LABEL_SCREEN_HEIGHT = 0.045
const LABEL_HEIGHT_ABOVE_GROUND = 4.5
// 鏡頭拉到最遠時看板縮成這個比例：整座島在畫面裡變小，看板太大會互相蓋住
const LABEL_FAR_SCALE = 0.75

// 顏色沿用魚島的色票（tailwind.config.js 的 island）；公路是沙色
const COLORS = {
  sea: '#8fd0e6',
  hex: '#8fd19e',
  land: '#5fae73',
  beach: '#ecebe4',
  paddyRidge: '#3d7f52',
  paddyWater: '#6fb7c9',
  road: '#ead9ae',
  labelBackground: '#ffffff',
  labelText: '#12394a',
}

export function flatMesh(geometry, color) {
  return new THREE.Mesh(geometry, new THREE.MeshLambertMaterial({ color, flatShading: true }))
}

/** 把模型縮放到指定大小：size 是目標值，axis 是量哪一邊（'y' 高度、'longest' 最長邊） */
export function fitModel(model, size, axis) {
  const box = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3())
  const current = axis === 'y' ? box.y : Math.max(box.x, box.z)
  model.scale.setScalar(size / current)
}

/** 把模型等比例縮放到寬（x）不超過 width、深（z）不超過 depth */
export function fitModelToFootprint(model, width, depth) {
  const box = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3())
  model.scale.setScalar(Math.min(width / box.x, depth / box.z))
}

function buildSea() {
  const sea = flatMesh(new THREE.CircleGeometry(SEA_RADIUS, SEA_SEGMENTS), COLORS.sea)
  sea.rotation.x = -Math.PI / 2
  sea.position.y = SEA_LEVEL
  return sea
}

/** 一圈頂點（x、z）圍成的形狀 */
function outlineShape([first, ...rest]) {
  const outline = new THREE.Shape()
  outline.moveTo(first.x, first.z)
  rest.forEach(({ x, z }) => outline.lineTo(x, z))
  return outline
}

/** 沙灘：海岸線圍起來的一塊厚板，頂面在 LAND_LEVEL；露出來的只有沿岸一圈 */
function buildBeach() {
  const geometry = new THREE.ExtrudeGeometry(outlineShape(ISLAND_DECOR.coast), {
    depth: LAND_THICKNESS,
    bevelEnabled: false,
  })
  // 形狀畫在 x–y 平面、往 +z 加厚；轉成躺在 x–z 平面、往下加厚
  geometry.rotateX(Math.PI / 2)
  const beach = flatMesh(geometry, COLORS.beach)
  beach.material.side = THREE.DoubleSide
  beach.position.y = LAND_LEVEL
  return beach
}

/** 沙灘以內的陸地：蓋在沙灘上面的一片 */
function buildInland() {
  const geometry = new THREE.ShapeGeometry(outlineShape(ISLAND_DECOR.inland))
  geometry.rotateX(Math.PI / 2)
  const inland = flatMesh(geometry, COLORS.land)
  inland.material.side = THREE.DoubleSide
  inland.position.y = INLAND_LEVEL
  return inland
}

/** 躺在地上的長方形，中心在原點 */
function flatRectangle(width, depth, color, level) {
  const rectangle = flatMesh(new THREE.PlaneGeometry(width, depth), color)
  rectangle.rotation.x = -Math.PI / 2
  rectangle.position.y = level
  return rectangle
}

/** 水田：一塊田埂色的長方形，裡面是水的顏色 */
function buildPaddy({ x, z, width, depth, heading }) {
  const paddy = new THREE.Group()
  paddy.add(
    flatRectangle(width, depth, COLORS.paddyRidge, 0),
    flatRectangle(
      width - PADDY_RIDGE * 2,
      depth - PADDY_RIDGE * 2,
      COLORS.paddyWater,
      PADDY_WATER_LIFT
    )
  )
  paddy.position.set(x, PADDY_LEVEL, z)
  paddy.rotation.y = heading
  return paddy
}

// CylinderGeometry 預設第一個頂點朝 +z；轉四分之一圈讓頂點朝 +x，和 hexBounds 一致
const HEX_VERTEX_TOWARD_X = Math.PI / 2

/** 部落格子：平頂六角柱，頂面在 y = 0，頂點方向和 hexBounds 一致（+x） */
function buildHex({ x, z }) {
  const radius = ISLAND_MAP.hexRadius
  const hex = flatMesh(
    new THREE.CylinderGeometry(
      radius,
      radius,
      HEX_THICKNESS,
      HEX_SIDES,
      1,
      false,
      HEX_VERTEX_TOWARD_X
    ),
    COLORS.hex
  )
  hex.position.set(x, -HEX_THICKNESS / 2, z)
  return hex
}

/** 標示的貼圖：白底圓角、深色字（部落標示與地標的招牌共用） */
export function labelTexture(text) {
  const canvas = document.createElement('canvas')
  canvas.width = LABEL_CANVAS.width
  canvas.height = LABEL_CANVAS.height
  const context = canvas.getContext('2d')
  context.fillStyle = COLORS.labelBackground
  context.beginPath()
  context.roundRect(0, 0, canvas.width, canvas.height, LABEL_CORNER)
  context.fill()
  context.fillStyle = COLORS.labelText
  context.font = LABEL_FONT
  context.textAlign = 'center'
  context.textBaseline = 'middle'
  context.fillText(text, canvas.width / 2, canvas.height / 2, canvas.width - LABEL_CORNER * 2)
  const texture = new THREE.CanvasTexture(canvas)
  texture.colorSpace = THREE.SRGBColorSpace
  return texture
}

/** 部落格子上方的標示「族語（中文）」：不被山擋住 */
function buildTribeLabel({ code, center }) {
  const material = new THREE.SpriteMaterial({
    map: labelTexture(islandTribeLabel(code)),
    sizeAttenuation: false,
    depthTest: false,
  })
  const label = new THREE.Sprite(material)
  label.position.set(center.x, LABEL_HEIGHT_ABOVE_GROUND, center.z)
  label.renderOrder = 1
  return label
}

/** 看板大小跟著鏡頭遠近：zoom 0（最近）是原本大小，1（最遠）縮到 LABEL_FAR_SCALE */
export function sizeTribeLabels(labels, zoom) {
  const height = LABEL_SCREEN_HEIGHT * (1 - (1 - LABEL_FAR_SCALE) * zoom)
  labels.forEach((label) => label.scale.set(height * LABEL_ASPECT, height, 1))
}

/** 公路：每一段是一塊長方形，折點補一個圓，形狀和可走範圍一致 */
function buildRoads() {
  const group = new THREE.Group()
  const width = ISLAND_MAP.roadHalfWidth * 2
  roadSegments(ISLAND_MAP).forEach(({ from, to }) => {
    const length = Math.hypot(to.x - from.x, to.z - from.z)
    const strip = flatMesh(new THREE.PlaneGeometry(length, width), COLORS.road)
    strip.rotation.set(-Math.PI / 2, 0, -Math.atan2(to.z - from.z, to.x - from.x))
    strip.position.set((from.x + to.x) / 2, ROAD_LEVEL, (from.z + to.z) / 2)
    const joint = flatMesh(
      new THREE.CircleGeometry(ISLAND_MAP.roadHalfWidth, ROAD_JOINT_SEGMENTS),
      COLORS.road
    )
    joint.rotation.x = -Math.PI / 2
    joint.position.set(from.x, ROAD_LEVEL, from.z)
    group.add(strip, joint)
  })
  return group
}

/** 山：島中間的幾座圓錐，只是裝飾；越高顏色越深 */
function buildMountains() {
  const group = new THREE.Group()
  ISLAND_DECOR.mountains.forEach(({ x, z, radius, height }) => {
    const cone = flatMesh(
      new THREE.ConeGeometry(radius, height, MOUNTAIN_SIDES),
      mountainColor(height)
    )
    cone.position.set(x, LAND_LEVEL + height / 2, z)
    group.add(cone)
  })
  return group
}

function buildBoat(loader, { x, z, heading }) {
  const boat = new THREE.Group()
  boat.position.set(x, SEA_LEVEL, z)
  boat.rotation.y = heading
  loader.load(
    BOAT_MODEL,
    (gltf) => {
      fitModel(gltf.scene, BOAT_LENGTH, 'longest')
      boat.add(gltf.scene)
    },
    undefined,
    (error) => console.warn('魚島 3D：船的模型載入失敗', error)
  )
  return boat
}

/** 船在海面上輕輕上下晃，每艘錯開一點 */
export function bobBoats(boats, elapsedSeconds) {
  boats.forEach((boat, i) => {
    boat.position.y = SEA_LEVEL + Math.sin(elapsedSeconds + i) * BOAT_BOB_HEIGHT
  })
}

/**
 * 整座島不會動的部分。回傳 { group 要加進場景的東西, boats 船（給 bobBoats 用）,
 * labels 部落標示（給 sizeTribeLabels 用） }
 */
export function buildTerrain(loader) {
  const group = new THREE.Group()
  const boats = ISLAND_DECOR.boats.map((boat) => buildBoat(loader, boat))
  const labels = ISLAND_MAP.tribes.map(buildTribeLabel)
  group.add(buildSea(), buildBeach(), buildInland(), buildRoads(), buildMountains())
  group.add(...ISLAND_DECOR.paddies.map(buildPaddy), ...boats, ...labels)
  ISLAND_MAP.tribes.forEach((tribe) => group.add(buildHex(tribe.center)))
  sizeTribeLabels(labels, 0)
  return { group, boats, labels }
}
