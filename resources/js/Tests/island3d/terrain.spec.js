import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { ISLAND_DECOR } from '@/island3d/islandMap'
import { footprintCorners, footprintGapToPoint } from '@/island3d/landmarks'
import { paddyShape } from '@/island3d/paddyShape'
import { buildPaddies } from '@/island3d/terrain'

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
