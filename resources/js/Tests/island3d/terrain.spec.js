import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { hexVertices } from '@/island3d/hexBounds'
import { ISLAND_DECOR, ISLAND_MAP, tribeHexes } from '@/island3d/islandMap'
import { LANDMARKS, footprintCorners, footprintGapToPoint } from '@/island3d/landmarks'
import { paddyShape } from '@/island3d/paddyShape'
import { INLAND_LEVEL, buildPaddies, buildRivers, buildTerrainMesh } from '@/island3d/terrain'
import {
  heightAt,
  islandHeightField,
  islandRiverRuns,
  terrainColor,
} from '@/island3d/terrainHeight'

// 一株芋頭有 4 片葉子、4 根莖
const LEAVES_PER_PLANT = 4

/** group 裡所有的 InstancedMesh，依名稱 */
function instancedByName(group) {
  const found = {}
  group.traverse((object) => {
    if (object.isInstancedMesh) {
      found[object.name] = object
    }
  })
  return found
}

/** 第 i 個實例的世界座標 */
function instancePosition(mesh, i) {
  const matrix = new THREE.Matrix4()
  mesh.getMatrixAt(i, matrix)
  matrix.premultiply(mesh.matrixWorld)
  return new THREE.Vector3().setFromMatrixPosition(matrix)
}

describe('island3d/terrain 水芋田合在一起畫（#211 驗收條件 13）', () => {
  const paddies = ISLAND_DECOR.paddies
  const group = buildPaddies(paddies)
  group.updateMatrixWorld(true)
  const instanced = instancedByName(group)
  const shapes = paddies.map((paddy) => paddyShape(paddy.layout))
  const stemsOf = (shape) => shape.taro.length * LEAVES_PER_PLANT
  const stonesOf = (shape) => shape.stones.length
  const total = (count) => shapes.reduce((sum, shape) => sum + count(shape), 0)
  /** 第 p 處的實例：[開始, 結束) */
  const rangeOf = (p, count) => {
    const start = shapes.slice(0, p).reduce((sum, shape) => sum + count(shape), 0)
    return [start, start + count(shapes[p])]
  }
  /** mesh 第 p 處的實例都落在那一處的佔地裡 */
  const expectInsideEach = (mesh, count) => {
    paddies.forEach((paddy, p) => {
      const corners = footprintCorners(paddy)
      const [start, end] = rangeOf(p, count)
      for (let i = start; i < end; i++) {
        const at = instancePosition(mesh, i)
        expect(footprintGapToPoint(corners, { x: at.x, z: at.z })).toBe(0)
      }
    })
  }

  it('芋頭葉、莖、田埂石頭各一個 InstancedMesh，共 3 個', () => {
    expect(Object.keys(instanced).sort()).toEqual(['paddy-leaves', 'paddy-stems', 'paddy-stones'])
  })

  it('實例數是 14 處加起來的數量', () => {
    expect(paddies).toHaveLength(14)
    expect(instanced['paddy-stems'].count).toBe(total(stemsOf))
    expect(instanced['paddy-leaves'].count).toBe(total(stemsOf))
    expect(instanced['paddy-stones'].count).toBe(total(stonesOf))
  })

  it('每株芋頭的莖都落在它那一處的佔地裡（位置與朝向有套用）', () => {
    expectInsideEach(instanced['paddy-stems'], stemsOf)
  })

  it('田埂石頭也落在它那一處的佔地裡', () => {
    expectInsideEach(instanced['paddy-stones'], stonesOf)
  })

  it('畫出來的水面高過同一層的土頂、低過上一層的土頂（#211 驗收條件 11，審查 1）', () => {
    const heightsOf = (name) => {
      const mesh = group.getObjectByName(name)
      const positions = mesh.geometry.getAttribute('position')
      const ys = new Set()
      for (let i = 0; i < positions.count; i++) {
        ys.add(positions.getY(i).toFixed(6))
      }
      return [...ys].map(Number).sort((a, b) => a - b)
    }
    // 土：最底下一個高度加上 3 排的土頂；水：3 排的水面（最多 3 排，各處同一排的高度一樣）
    const [, ...soilTops] = heightsOf('paddy-soil')
    const waters = heightsOf('paddy-water')

    expect(soilTops).toHaveLength(3)
    expect(waters).toHaveLength(3)
    waters.forEach((water, i) => {
      expect(water).toBeGreaterThan(soilTops[i])
      if (i + 1 < soilTops.length) {
        expect(water).toBeLessThan(soilTops[i + 1])
      }
    })
  })

  it('沒有水田時不出錯，實例數是 0', () => {
    const empty = instancedByName(buildPaddies([]))
    Object.values(empty).forEach((mesh) => expect(mesh.count).toBe(0))
  })
})

describe('island3d/terrain 地形網格（#223 驗收條件 1、2、3）', () => {
  const field = islandHeightField()
  const mesh = buildTerrainMesh(field)
  const position = mesh.geometry.attributes.position
  const color = mesh.geometry.attributes.color
  const vertexAt = (i) => ({ x: position.getX(i), y: position.getY(i), z: position.getZ(i) })

  it('一整片同一個網格：一個 Mesh、網格點共用頂點（有 index，頂點數不超過高度表的點數）', () => {
    expect(mesh.isMesh).toBe(true)
    expect(mesh.name).toBe('terrain')
    expect(mesh.geometry.index.count).toBeGreaterThan(0)
    expect(position.count).toBeLessThanOrEqual(field.cols * field.rows)
  })

  it('每個頂點畫在陸地高度＋0.005＋那一點的地形高度', () => {
    for (let i = 0; i < position.count; i += 1) {
      const vertex = vertexAt(i)
      expect(vertex.y).toBeCloseTo(INLAND_LEVEL + 0.005 + heightAt(field, vertex), 4)
    }
  })

  it('三角形跟「某點的高度」是同一組：每個三角形重心的高度＝heightAt', () => {
    const index = mesh.geometry.index
    for (let k = 0; k < index.count; k += 3 * 97) {
      const [a, b, c] = [0, 1, 2].map((j) => vertexAt(index.getX(k + j)))
      const centroid = { x: (a.x + b.x + c.x) / 3, z: (a.z + b.z + c.z) / 3 }
      expect((a.y + b.y + c.y) / 3).toBeCloseTo(INLAND_LEVEL + 0.005 + heightAt(field, centroid), 5)
    }
  })

  it('三角形朝上（法線 y > 0），從上面看得到', () => {
    const index = mesh.geometry.index
    for (let k = 0; k < index.count; k += 3 * 53) {
      const [a, b, c] = [0, 1, 2].map((j) => new THREE.Vector3().copy(vertexAt(index.getX(k + j))))
      const normal = new THREE.Vector3().crossVectors(b.clone().sub(a), c.clone().sub(a))
      expect(normal.y).toBeGreaterThan(0)
    }
  })

  it('頂點顏色照高度（terrainColor），平滑著色、照光線算明暗', () => {
    for (let i = 0; i < position.count; i += 41) {
      const expected = new THREE.Color(terrainColor(heightAt(field, vertexAt(i))))
      expect(color.getX(i)).toBeCloseTo(expected.r, 5)
      expect(color.getY(i)).toBeCloseTo(expected.g, 5)
      expect(color.getZ(i)).toBeCloseTo(expected.b, 5)
    }
    expect(mesh.material.vertexColors).toBe(true)
    expect(mesh.material.flatShading).toBe(false)
    expect(mesh.material.isMeshLambertMaterial).toBe(true)
  })
})

describe('island3d/terrain 溪流（#223 驗收條件 4）', () => {
  const field = islandHeightField()
  const rivers = buildRivers(islandRiverRuns(field), field)
  const position = rivers.geometry.attributes.position
  const vertexAt = (i) => ({ x: position.getX(i), y: position.getY(i), z: position.getZ(i) })
  const polygons = [
    ...ISLAND_MAP.tribes
      .flatMap(tribeHexes)
      .map((center) => hexVertices(ISLAND_MAP.hexRadius, center)),
    ...[...ISLAND_DECOR.paddies, ...LANDMARKS].map(footprintCorners),
  ]

  it('所有的溪合成一個網格，水藍色 #5fb4d6', () => {
    expect(rivers.isMesh).toBe(true)
    expect(rivers.name).toBe('rivers')
    expect(`#${rivers.material.color.getHexString()}`).toBe('#5fb4d6')
  })

  it('溪的每個頂點比那一點的地形網格高 0.115（＝陸地高度＋地形高度＋0.12）', () => {
    for (let i = 0; i < position.count; i += 1) {
      const vertex = vertexAt(i)
      expect(vertex.y).toBeCloseTo(INLAND_LEVEL + heightAt(field, vertex) + 0.12, 4)
    }
  })

  it('沒有頂點落在格子、水田、特色建築往外 1 單位內', () => {
    for (let i = 0; i < position.count; i += 1) {
      const vertex = vertexAt(i)
      polygons.forEach((polygon) =>
        expect(footprintGapToPoint(polygon, vertex)).toBeGreaterThanOrEqual(1 - 1e-6)
      )
    }
  })

  it('平地上的溪面（0.02）低於公路（0.05），公路蓋在溪上', () => {
    const onFlat = Array.from({ length: position.count }, (_, i) => vertexAt(i)).filter(
      (vertex) => heightAt(field, vertex) === 0
    )
    expect(onFlat.length).toBeGreaterThan(0)
    onFlat.forEach((vertex) => expect(vertex.y).toBeLessThan(0.05))
  })

  it('寬 1.6：每一段的兩個邊相距 1.6', () => {
    for (let i = 0; i < Math.min(position.count, 400); i += 2) {
      const [left, right] = [vertexAt(i), vertexAt(i + 1)]
      expect(Math.hypot(left.x - right.x, left.z - right.z)).toBeCloseTo(1.6, 5)
    }
  })
})
