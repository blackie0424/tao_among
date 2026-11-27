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
  const shape = paddyShape(paddies[0].width, paddies[0].depth)

  it('芋頭葉、莖、田埂石頭各一個 InstancedMesh，共 3 個', () => {
    expect(Object.keys(instanced).sort()).toEqual(['paddy-leaves', 'paddy-stems', 'paddy-stones'])
  })

  it('實例數是 14 塊田加起來的數量', () => {
    expect(paddies).toHaveLength(14)
    expect(instanced['paddy-stems'].count).toBe(
      paddies.length * shape.taro.length * LEAVES_PER_PLANT
    )
    expect(instanced['paddy-leaves'].count).toBe(
      paddies.length * shape.taro.length * LEAVES_PER_PLANT
    )
    expect(instanced['paddy-stones'].count).toBe(paddies.length * shape.stones.length)
  })

  it('每株芋頭的莖都落在它那塊田的佔地裡（田的位置與朝向有套用）', () => {
    const stems = instanced['paddy-stems']
    const perPaddy = shape.taro.length * LEAVES_PER_PLANT
    paddies.forEach((paddy, p) => {
      const corners = footprintCorners(paddy)
      for (let i = p * perPaddy; i < (p + 1) * perPaddy; i++) {
        const at = instancePosition(stems, i)
        expect(footprintGapToPoint(corners, { x: at.x, z: at.z })).toBe(0)
      }
    })
  })

  it('田埂石頭也落在它那塊田的佔地裡', () => {
    const stones = instanced['paddy-stones']
    paddies.forEach((paddy, p) => {
      const corners = footprintCorners(paddy)
      for (let i = p * shape.stones.length; i < (p + 1) * shape.stones.length; i++) {
        const at = instancePosition(stones, i)
        expect(footprintGapToPoint(corners, { x: at.x, z: at.z })).toBe(0)
      }
    })
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
    // 土：最底下一個高度加上 3 層的土頂；水：3 層的水面（14 塊田一樣大，高度相同）
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
