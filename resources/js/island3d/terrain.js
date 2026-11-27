// 魚島 3D：把整座島不會動的部分畫出來（#200、#207、#212）——海、海邊的岩岸、陸地、部落格子與標示、
// 公路、山（實際地形）、溪流、水田、船。
// 位置都讀 islandMap.js；這個檔只管繪圖。
import * as THREE from 'three'
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js'
import { ISLAND_DECOR, ISLAND_MAP, roadSegments, tribeHexes } from './islandMap'
import { PADDY_COLORS, paddyShape } from './paddyShape'
import {
  RIVER_HALF_WIDTH,
  heightAt,
  islandHeightField,
  islandRiverRuns,
  terrainColor,
} from './terrainHeight'

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
// 水芋田小塊之間的田埂（整片底下的一層草色），比最前一排的土頂低
const PADDY_RIDGE_THICKNESS = 0.02
// 一株芋頭：4 片心形葉子斜插在短莖上（樣品的比例，大小再乘上每株的 scale）
const TARO_LEAVES = 4
const LEAF_OFFSET = 0.18
const LEAF_HEIGHT = 0.72
const LEAF_TILT = -1.1
const STEM_OFFSET = 0.08
const STEM_LENGTH = 0.7
const STEM_WIDTH = 0.03
const STEM_TILT = 0.25
const ROAD_LEVEL = 0.05
// 地形網格比陸地高一點點；四個角都低於 TERRAIN_DRAW_MIN 的格子不畫，露出下面的陸地（#223）
const TERRAIN_LIFT = 0.005
const TERRAIN_DRAW_MIN = 0.03
// 溪面比陸地高這麼多（加上地形高度）：比地形網格高 0.115、平地上 0.02 低於公路，公路蓋在溪上
const RIVER_LIFT = 0.12
const ROAD_JOINT_SEGMENTS = 16
const BOAT_LENGTH = 3
const BOAT_BOB_HEIGHT = 0.06
const BOAT_MODEL = '/island3d/boats/boat-fishing-small.glb'

// 地標的招牌：畫在一張小畫布上貼成看板（部落標示在 #201 拿掉，所在部落改看左上角）
const LABEL_CANVAS = { width: 512, height: 128 }
// 標示的寬高比（招牌沿用同一張貼圖的比例）
export const LABEL_ASPECT = LABEL_CANVAS.width / LABEL_CANVAS.height
const LABEL_FONT = '800 56px system-ui, "PingFang TC", "Noto Sans TC", sans-serif'
const LABEL_CORNER = 28

// 顏色沿用魚島的色票（tailwind.config.js 的 island）；公路是沙色；
// 海邊那一圈是岩岸的灰色（蘭嶼的海邊多是岩岸，不是白沙；也和公路的沙色分得開，#212）
const COLORS = {
  sea: '#8fd0e6',
  hex: '#8fd19e',
  land: '#5fae73',
  beach: '#8c9196',
  road: '#ead9ae',
  river: '#5fb4d6',
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

/** 海邊的岩岸：海岸線圍起來的一塊厚板，頂面在 LAND_LEVEL；露出來的只有沿岸一圈 */
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

// ── 水芋田（#211）：小塊田拼成一片、石頭田埂、芋頭。14 處的田埂底、土、水各合成一個網格，
// 芋頭葉、莖、田埂石頭各用一個 InstancedMesh，不是每株一個 ──

const UP = new THREE.Vector3(0, 1, 0)
const UNIT_SCALE = new THREE.Vector3(1, 1, 1)

/** 位置、旋轉（Euler）、縮放組成的矩陣 */
function transform(position, rotation = new THREE.Euler(), scale = UNIT_SCALE) {
  return new THREE.Matrix4().compose(position, new THREE.Quaternion().setFromEuler(rotation), scale)
}

/** 一塊田：從田的座標（中心為原點、正面朝 +z）換到世界座標 */
function paddyMatrix({ x, z, heading }) {
  return new THREE.Matrix4().compose(
    new THREE.Vector3(x, PADDY_LEVEL, z),
    new THREE.Quaternion().setFromAxisAngle(UP, heading),
    UNIT_SCALE
  )
}

/** 心形葉子：尖端在原點、往 +y 長，長約 0.6 */
function leafGeometry() {
  const leaf = new THREE.Shape()
  leaf.moveTo(0, 0)
  leaf.bezierCurveTo(0.35, 0.05, 0.4, 0.45, 0.12, 0.6)
  leaf.lineTo(0, 0.48)
  leaf.lineTo(-0.12, 0.6)
  leaf.bezierCurveTo(-0.4, 0.45, -0.35, 0.05, 0, 0)
  return new THREE.ShapeGeometry(leaf)
}

/** 一株芋頭的 4 片葉子與 4 根莖的矩陣（世界座標） */
function taroMatrices(paddy, plant) {
  const base = paddy
    .clone()
    .multiply(
      transform(
        new THREE.Vector3(plant.x, plant.level, plant.z),
        new THREE.Euler(0, plant.turn, 0),
        new THREE.Vector3().setScalar(plant.scale)
      )
    )
  return Array.from({ length: TARO_LEAVES }, (_, i) => {
    const angle = (i / TARO_LEAVES) * Math.PI * 2
    const [sin, cos] = [Math.sin(angle), Math.cos(angle)]
    const leaf = transform(
      new THREE.Vector3(sin * LEAF_OFFSET, LEAF_HEIGHT, cos * LEAF_OFFSET),
      new THREE.Euler(LEAF_TILT, angle + Math.PI, 0, 'YXZ')
    )
    const stem = transform(
      new THREE.Vector3(sin * STEM_OFFSET, STEM_LENGTH / 2, cos * STEM_OFFSET),
      new THREE.Euler(cos * STEM_TILT, 0, -sin * STEM_TILT),
      new THREE.Vector3(STEM_WIDTH, STEM_LENGTH, STEM_WIDTH)
    )
    return { leaf: base.clone().multiply(leaf), stem: base.clone().multiply(stem), color: i % 2 }
  })
}

/** 一片水田的田埂底、土、水（世界座標的形體）、石頭與芋頭（矩陣） */
function paddyPieces(paddy) {
  const matrix = paddyMatrix(paddy)
  const { fields, stones, taro } = paddyShape(paddy.layout)
  const ridge = new THREE.BoxGeometry(paddy.width, PADDY_RIDGE_THICKNESS, paddy.depth)
    .translate(0, PADDY_RIDGE_THICKNESS / 2, 0)
    .applyMatrix4(matrix)
  const soil = fields.map(({ x, z, width, depth, soilTop }) =>
    new THREE.BoxGeometry(width, soilTop, depth).translate(x, soilTop / 2, z).applyMatrix4(matrix)
  )
  const water = fields.map(({ water: surface }) =>
    new THREE.PlaneGeometry(surface.width, surface.depth)
      .rotateX(-Math.PI / 2)
      .translate(surface.x, surface.level, surface.z)
      .applyMatrix4(matrix)
  )
  const stoneMatrices = stones.map((stone) => ({
    matrix: matrix
      .clone()
      .multiply(
        transform(
          new THREE.Vector3(stone.x, stone.level + stone.height / 2, stone.z),
          undefined,
          new THREE.Vector3(stone.width, stone.height, stone.depth)
        )
      ),
    color: stone.color,
  }))
  return {
    ridge: [ridge],
    soil,
    water,
    stones: stoneMatrices,
    taro: taro.flatMap((plant) => taroMatrices(matrix, plant)),
  }
}

/** 同一個形體、同一種材質畫很多份：每份一個矩陣，可以各有顏色 */
function instanced(name, geometry, material, items, colorOf) {
  const mesh = new THREE.InstancedMesh(geometry, material, items.length)
  mesh.name = name
  items.forEach((item, i) => {
    mesh.setMatrixAt(i, item.matrix)
    if (colorOf) {
      mesh.setColorAt(i, new THREE.Color(colorOf(item)))
    }
  })
  return mesh
}

/** 很多個形體合成一個網格；沒有形體就不畫 */
function mergedMesh(name, geometries, color) {
  if (!geometries.length) {
    return []
  }
  const mesh = flatMesh(mergeGeometries(geometries), color)
  mesh.name = name
  return [mesh]
}

/** 所有的水芋田（預設是 islandMap.js 的 14 塊），回傳一個 group */
export function buildPaddies(paddies = ISLAND_DECOR.paddies) {
  const pieces = paddies.map(paddyPieces)
  const all = (key) => pieces.flatMap((piece) => piece[key])
  const taro = all('taro')
  const lambert = (options) => new THREE.MeshLambertMaterial({ flatShading: true, ...options })
  const group = new THREE.Group()
  group.add(
    ...mergedMesh('paddy-ridge', all('ridge'), PADDY_COLORS.ridge),
    ...mergedMesh('paddy-soil', all('soil'), PADDY_COLORS.soil),
    ...mergedMesh('paddy-water', all('water'), PADDY_COLORS.water),
    instanced(
      'paddy-stones',
      new THREE.BoxGeometry(1, 1, 1),
      lambert(),
      all('stones'),
      (stone) => stone.color
    ),
    instanced(
      'paddy-leaves',
      leafGeometry(),
      lambert({ side: THREE.DoubleSide }),
      taro.map(({ leaf, color }) => ({ matrix: leaf, color })),
      (leaf) => PADDY_COLORS.leaves[leaf.color]
    ),
    instanced(
      'paddy-stems',
      new THREE.BoxGeometry(1, 1, 1),
      lambert({ color: PADDY_COLORS.stem }),
      taro.map(({ stem }) => ({ matrix: stem }))
    )
  )
  return group
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

/** 招牌的貼圖：白底圓角、深色字 */
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

/**
 * 山：整片地形網格（#223）。每個網格點一個頂點，畫在陸地高度＋TERRAIN_LIFT＋地形高度，顏色照高度；
 * 每格沿 (行, 列) → (行＋1, 列＋1) 的對角線切兩個三角形，跟 heightAt 用的同一組。
 */
export function buildTerrainMesh(field) {
  const { x0, z0, step, cols, rows, heights } = field
  const vertexOf = new Map()
  const positions = []
  const colors = []
  const indices = []
  const vertex = (col, row) => {
    const key = row * cols + col
    if (!vertexOf.has(key)) {
      const height = heights[key]
      const color = new THREE.Color(terrainColor(height))
      vertexOf.set(key, positions.length / 3)
      positions.push(x0 + col * step, INLAND_LEVEL + TERRAIN_LIFT + height, z0 + row * step)
      colors.push(color.r, color.g, color.b)
    }
    return vertexOf.get(key)
  }
  for (let row = 0; row < rows - 1; row += 1) {
    for (let col = 0; col < cols - 1; col += 1) {
      const corners = [
        [col, row],
        [col + 1, row],
        [col + 1, row + 1],
        [col, row + 1],
      ]
      if (corners.every(([c, r]) => heights[r * cols + c] < TERRAIN_DRAW_MIN)) {
        continue
      }
      const [a, b, c, d] = corners.map(([cc, rr]) => vertex(cc, rr))
      // 逆時針（從上面看）才朝上：上三角形 a c b、下三角形 a d c
      indices.push(a, c, b, a, d, c)
    }
  }
  const geometry = new THREE.BufferGeometry()
  geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3))
  geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3))
  geometry.setIndex(indices)
  geometry.computeVertexNormals()
  const mesh = new THREE.Mesh(geometry, new THREE.MeshLambertMaterial({ vertexColors: true }))
  mesh.name = 'terrain'
  return mesh
}

/** 溪的一點往左右各 RIVER_HALF_WIDTH 的兩個點：方向取前後兩段的平均 */
function riverEdges(run, i) {
  const before = run[Math.max(0, i - 1)]
  const after = run[Math.min(run.length - 1, i + 1)]
  const length = Math.hypot(after.x - before.x, after.z - before.z)
  const across = { x: -(after.z - before.z) / length, z: (after.x - before.x) / length }
  return [1, -1].map((side) => ({
    x: run[i].x + across.x * RIVER_HALF_WIDTH * side,
    z: run[i].z + across.z * RIVER_HALF_WIDTH * side,
  }))
}

/**
 * 溪流：所有的溪合成一個網格（#223）。runs 是 islandRiverRuns 的結果；每個頂點的高度是
 * 陸地高度＋那一點的地形高度（field）＋RIVER_LIFT。
 */
export function buildRivers(runs, field) {
  const positions = []
  const indices = []
  runs.forEach((run) => {
    const first = positions.length / 3
    run.forEach((_, i) => {
      riverEdges(run, i).forEach((edge) =>
        positions.push(edge.x, INLAND_LEVEL + heightAt(field, edge) + RIVER_LIFT, edge.z)
      )
      if (i > 0) {
        const [left, right] = [first + (i - 1) * 2, first + (i - 1) * 2 + 1]
        indices.push(left, right, left + 2, left + 2, right, right + 2)
      }
    })
  })
  const geometry = new THREE.BufferGeometry()
  geometry.setAttribute('position', new THREE.Float32BufferAttribute(positions, 3))
  geometry.setIndex(indices)
  geometry.computeVertexNormals()
  const mesh = new THREE.Mesh(
    geometry,
    new THREE.MeshLambertMaterial({ color: COLORS.river, side: THREE.DoubleSide })
  )
  mesh.name = 'rivers'
  return mesh
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

/** 整座島不會動的部分。回傳 { group 要加進場景的東西, boats 船（給 bobBoats 用） } */
export function buildTerrain(loader) {
  const group = new THREE.Group()
  const boats = ISLAND_DECOR.boats.map((boat) => buildBoat(loader, boat))
  const field = islandHeightField()
  group.add(buildSea(), buildBeach(), buildInland(), buildRoads())
  group.add(buildTerrainMesh(field), buildRivers(islandRiverRuns(field), field))
  group.add(buildPaddies(), ...boats)
  group.add(...ISLAND_MAP.tribes.flatMap(tribeHexes).map(buildHex))
  return { group, boats }
}
