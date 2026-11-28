import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as THREE from 'three'
import {
  buildHouseholdSigns,
  buildHouseholds,
  buildLandmarks,
  showSigns,
  unitGeometry,
} from '@/island3d/landmarkMeshes'
import { HOUSEHOLD_KINDS, HOUSEHOLD_KIND_ORDER, HOUSEHOLDS } from '@/island3d/households'

// 零件的形狀（和 landmarks.spec.js 的 PART_SHAPES 一樣）
const SHAPES = [
  'box',
  'roof',
  'plane',
  'post',
  'ring',
  'cylinder',
  'dome',
  'gable',
  'umbrella',
  'umbrella-alt',
]
const EPSILON = 1e-6

/** 形體的包圍範圍：{ min, max, size } */
function boundsOf(geometry) {
  geometry.computeBoundingBox()
  const { min, max } = geometry.boundingBox
  return { min, max, size: max.clone().sub(min) }
}

describe('island3d/landmarkMeshes 零件形狀的原型（#211 驗收條件 7）', () => {
  it('每種形狀都剛好填在邊長 1、中心在原點的方塊裡（寬高深都是 1）', () => {
    SHAPES.forEach((shape) => {
      const { min, max, size } = boundsOf(unitGeometry(shape))
      ;['x', 'y', 'z'].forEach((axis) => {
        expect(min[axis]).toBeGreaterThanOrEqual(-0.5 - EPSILON)
        expect(max[axis]).toBeLessThanOrEqual(0.5 + EPSILON)
        expect(size[axis]).toBeCloseTo(1, 6)
      })
    })
  })

  it('圓柱的軸朝 x、y、z：都還是填在同一個方塊裡，圓的那一面跟著軸轉', () => {
    ;['x', 'y', 'z'].forEach((axis) => {
      const geometry = unitGeometry('cylinder', axis)
      const { size } = boundsOf(geometry)
      ;['x', 'y', 'z'].forEach((side) => expect(size[side]).toBeCloseTo(1, 6))
      // 沿著軸的方向，頂點只在兩端（±0.5）
      const positions = geometry.getAttribute('position')
      const along = new Set()
      for (let i = 0; i < positions.count; i++) {
        along.add(Math.abs(new THREE.Vector3().fromBufferAttribute(positions, i)[axis]).toFixed(6))
      }
      expect([...along].every((value) => value === '0.500000' || value === '0.000000')).toBe(true)
    })
  })

  it('兩面坡屋頂：屋脊在正中間的最高處、沿著 x；底部的兩邊等高、在前後兩側', () => {
    const positions = unitGeometry('gable').getAttribute('position')
    const points = Array.from({ length: positions.count }, (_, i) =>
      new THREE.Vector3().fromBufferAttribute(positions, i)
    )
    const ridge = points.filter((point) => point.y > 0.5 - EPSILON)
    const eaves = points.filter((point) => point.y < -0.5 + EPSILON)

    expect(ridge.length).toBeGreaterThan(0)
    ridge.forEach((point) => expect(point.z).toBeCloseTo(0, 6))
    expect(new Set(ridge.map((point) => point.x.toFixed(6)))).toEqual(
      new Set(['-0.500000', '0.500000'])
    )
    // 底部只有前後兩條邊（z = ±0.5），沒有在中間的點
    eaves.forEach((point) => expect(Math.abs(point.z)).toBeCloseTo(0.5, 6))
    expect(new Set(eaves.map((point) => Math.sign(point.z)))).toEqual(new Set([-1, 1]))
  })

  it('不認得的形狀當成盒子', () => {
    expect(unitGeometry('no-such-shape').type).toBe('BoxGeometry')
  })
})

describe('island3d/landmarkMeshes 家屋四件（#201 驗收條件 1）', () => {
  const group = buildHouseholds()
  const meshOf = (kind) => group.children.find((child) => child.name === `household-${kind}`)

  it('每一種東西合成一個 InstancedMesh，18 戶各一份', () => {
    HOUSEHOLD_KIND_ORDER.forEach((kind) => {
      const mesh = meshOf(kind)

      expect(mesh).toBeInstanceOf(THREE.InstancedMesh)
      expect(mesh.count).toBe(HOUSEHOLDS.length)
      expect(mesh.geometry.getAttribute('color')).toBeDefined()
    })
  })

  it('原型的大小在佔地裡、站在地上；第 i 份放在第 i 戶那一件的位置與朝向', () => {
    const matrix = new THREE.Matrix4()
    const position = new THREE.Vector3()
    const rotation = new THREE.Quaternion()
    const scale = new THREE.Vector3()
    HOUSEHOLD_KIND_ORDER.forEach((kind, k) => {
      const mesh = meshOf(kind)
      const { width, depth } = HOUSEHOLD_KINDS[kind]
      const { min, max } = boundsOf(mesh.geometry)

      expect(Math.max(-min.x, max.x)).toBeLessThanOrEqual(width / 2 + EPSILON)
      expect(Math.max(-min.z, max.z)).toBeLessThanOrEqual(depth / 2 + EPSILON)
      expect(min.y).toBeGreaterThanOrEqual(-EPSILON)
      HOUSEHOLDS.forEach((household, i) => {
        const item = household.items[k]
        mesh.getMatrixAt(i, matrix)
        matrix.decompose(position, rotation, scale)
        // 矩陣是 32 位元浮點數，比到小數第 4 位
        const turned = new THREE.Euler().setFromQuaternion(rotation, 'YXZ')

        expect(position.x).toBeCloseTo(item.x, 4)
        expect(position.z).toBeCloseTo(item.z, 4)
        expect(Math.cos(turned.y)).toBeCloseTo(Math.cos(item.heading), 4)
        expect(Math.sin(turned.y)).toBeCloseTo(Math.sin(item.heading), 4)
      })
    })
  })
})

describe('island3d/landmarkMeshes 每一塊招牌照所在格與距離顯示（#226 驗收條件 1、2、4）', () => {
  // jsdom 沒有 canvas 的 2D 繪圖：給一個什麼都不畫的假的，招牌貼圖照樣做得出來
  const quietContext = new Proxy({}, { get: () => () => {}, set: () => true })
  const idleLoader = { load() {} }
  const spots = Object.fromEntries(
    HOUSEHOLDS.map(({ tribe }) => [
      tribe,
      Array.from({ length: 12 }, (_, i) => ({ name: `${tribe}-${i}` })),
    ])
  )
  beforeEach(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(quietContext)
  })
  afterEach(() => vi.restoreAllMocks())

  const shownTexts = (group) =>
    group.children.filter((sign) => sign.visible).map((sign) => sign.userData.sign.text)
  // 站在農會（椰油，x -131.5、z -41.9）旁邊，所在格是椰油第 2 格
  const nearFarmers = { player: { x: -131.5, z: -45 }, hex: { tribe: 'yayo', hex: 1 } }

  it('家屋招牌：只有顯示格那一戶的 4 件顯示', () => {
    const signs = buildHouseholdSigns(spots)

    showSigns(signs, { ...nearFarmers, zoom: 0 })

    expect(shownTexts(signs)).toEqual(['yayo-4', 'yayo-5', 'yayo-6', 'yayo-7'])
  })

  it('特色建築招牌：只有 30 以內的顯示', () => {
    const { signs } = buildLandmarks(idleLoader)

    showSigns(signs, { ...nearFarmers, zoom: 0 })

    expect(shownTexts(signs)).toEqual(['農會'])
  })

  it('拉遠超過一半：每一塊都隱藏；拉回來再照所在格顯示', () => {
    const signs = buildHouseholdSigns(spots)

    showSigns(signs, { ...nearFarmers, zoom: 0.9 })
    expect(shownTexts(signs)).toEqual([])

    showSigns(signs, { ...nearFarmers, zoom: 0.5 })
    expect(shownTexts(signs)).toEqual(['yayo-4', 'yayo-5', 'yayo-6', 'yayo-7'])
  })
})
