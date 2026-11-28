import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { createCameraOcclusion } from '@/island3d/cameraOcclusion'

// 角色胸口在原點上方 1.2；鏡頭在 +z 那一側同樣高度，直線沿著 z 軸
const CHEST = { x: 0, y: 1.2, z: 0 }
const cameraAt = (z) => ({ x: 0, y: 1.2, z })
// 2 × 2 × 2 的方塊，站在地上（中心離地 1），胸口的直線穿過它
const box = () => new THREE.BoxGeometry(2, 2, 2)

/** 一種家屋：InstancedMesh，第 i 份放在 positions[i]（{ x, z }） */
function householdKind(positions) {
  const mesh = new THREE.InstancedMesh(box(), new THREE.MeshLambertMaterial(), positions.length)
  const placement = new THREE.Object3D()
  positions.forEach(({ x, z }, i) => {
    placement.position.set(x, 1, z)
    placement.updateMatrix()
    mesh.setMatrixAt(i, placement.matrix)
  })
  mesh.updateMatrixWorld(true)
  return mesh
}

/** 一個地標：群組裡一個網格，放在 (x, 1, z) */
function landmarkAt({ x, z }, material) {
  const group = new THREE.Group()
  const mesh = new THREE.Mesh(box(), material)
  mesh.position.set(x, 1, z)
  group.add(mesh)
  group.updateMatrixWorld(true)
  return { group, mesh }
}

const matrixOf = (mesh, i) => {
  const matrix = new THREE.Matrix4()
  mesh.getMatrixAt(i, matrix)
  return matrix
}

describe('island3d/cameraOcclusion 家屋以戶為單位變半透明（#228 驗收條件 5）', () => {
  it('同一種家屋兩戶，只有一戶擋住：只有那一戶藏起來、換成 25% 的替身，另一戶不變', () => {
    const kind = householdKind([
      { x: 0, z: 5 },
      { x: 10, z: 5 },
    ])
    const otherBefore = matrixOf(kind, 1).clone()
    const occlusion = createCameraOcclusion({ households: [kind], landmarks: [] })

    occlusion.update(CHEST, cameraAt(10))

    expect(matrixOf(kind, 0).determinant()).toBe(0)
    expect(matrixOf(kind, 1).equals(otherBefore)).toBe(true)
    expect(occlusion.group.children).toHaveLength(1)
    const [proxy] = occlusion.group.children
    expect(proxy.material.opacity).toBe(0.25)
    expect(proxy.material.transparent).toBe(true)
    expect(proxy.geometry).toBe(kind.geometry)
    expect(new THREE.Vector3().setFromMatrixPosition(proxy.matrix)).toEqual(new THREE.Vector3(0, 1, 5))
    expect(kind.material.opacity).toBe(1)
  })

  it('不再擋住的那一張畫面：替身拿掉、那一戶放回原位', () => {
    const kind = householdKind([{ x: 0, z: 5 }])
    const before = matrixOf(kind, 0).clone()
    const occlusion = createCameraOcclusion({ households: [kind], landmarks: [] })

    occlusion.update(CHEST, cameraAt(10))
    occlusion.update(CHEST, cameraAt(-10))

    expect(matrixOf(kind, 0).equals(before)).toBe(true)
    expect(occlusion.group.children).toHaveLength(0)
  })

  it('還在擋住時連續幾張畫面都維持半透明（藏起來的那一戶仍照原位判斷）', () => {
    const kind = householdKind([{ x: 0, z: 5 }])
    const occlusion = createCameraOcclusion({ households: [kind], landmarks: [] })

    occlusion.update(CHEST, cameraAt(10))
    occlusion.update(CHEST, cameraAt(10))
    occlusion.update(CHEST, cameraAt(9))

    expect(matrixOf(kind, 0).determinant()).toBe(0)
    expect(occlusion.group.children).toHaveLength(1)
  })

  it('兩件同時擋住都變半透明；其中一件不再擋住時只有它恢復', () => {
    const near = householdKind([{ x: 0, z: 4 }])
    const far = householdKind([{ x: 0, z: 8 }])
    const occlusion = createCameraOcclusion({ households: [near, far], landmarks: [] })

    occlusion.update(CHEST, cameraAt(12))
    expect(occlusion.group.children).toHaveLength(2)

    occlusion.update(CHEST, cameraAt(6))
    expect(matrixOf(near, 0).determinant()).toBe(0)
    expect(matrixOf(far, 0).determinant()).not.toBe(0)
    expect(occlusion.group.children).toHaveLength(1)
  })
})

describe('island3d/cameraOcclusion 地標變半透明（#228 驗收條件 5）', () => {
  it('兩個地標共用材質，只有一個擋住：那一個 25%，另一個仍是原本的材質、不透明', () => {
    const shared = new THREE.MeshLambertMaterial()
    const blocking = landmarkAt({ x: 0, z: 5 }, shared)
    const aside = landmarkAt({ x: 10, z: 5 }, shared)
    const occlusion = createCameraOcclusion({
      households: [],
      landmarks: [blocking.group, aside.group],
    })

    occlusion.update(CHEST, cameraAt(10))

    expect(blocking.mesh.material).not.toBe(shared)
    expect(blocking.mesh.material.opacity).toBe(0.25)
    expect(blocking.mesh.material.transparent).toBe(true)
    expect(aside.mesh.material).toBe(shared)
    expect(shared.opacity).toBe(1)
  })

  it('不再擋住時換回原本的材質', () => {
    const shared = new THREE.MeshLambertMaterial()
    const blocking = landmarkAt({ x: 0, z: 5 }, shared)
    const occlusion = createCameraOcclusion({ households: [], landmarks: [blocking.group] })

    occlusion.update(CHEST, cameraAt(10))
    occlusion.update(CHEST, cameraAt(-10))

    expect(blocking.mesh.material).toBe(shared)
  })

  it('還沒載入模型的地標（空群組）：不會被判定擋住，也不會出錯', () => {
    const empty = new THREE.Group()
    const occlusion = createCameraOcclusion({ households: [], landmarks: [empty] })

    expect(() => occlusion.update(CHEST, cameraAt(10))).not.toThrow()
    expect(occlusion.group.children).toHaveLength(0)
  })
})

describe('island3d/cameraOcclusion 不在名單上的東西不變透明（#228 驗收條件 5）', () => {
  it('角色、家人等沒交給它的網格擋在中間：材質不變', () => {
    const material = new THREE.MeshLambertMaterial()
    const player = landmarkAt({ x: 0, z: 5 }, material)
    const house = householdKind([{ x: 10, z: 5 }])
    const occlusion = createCameraOcclusion({ households: [house], landmarks: [] })

    occlusion.update(CHEST, cameraAt(10))

    expect(player.mesh.material).toBe(material)
    expect(material.opacity).toBe(1)
    expect(occlusion.group.children).toHaveLength(0)
  })
})
