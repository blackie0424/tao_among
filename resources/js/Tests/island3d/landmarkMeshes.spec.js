import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { unitGeometry } from '@/island3d/landmarkMeshes'

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
