import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { recolorGeometry } from '@/island3d/familyMeshes'

/** 兩個三角形：第一個在色塊 [0, 1]（衣服），第二個在 [1, 2]（皮膚） */
function twoTriangles() {
  const geometry = new THREE.BufferGeometry()
  geometry.setAttribute('position', new THREE.Float32BufferAttribute(new Array(18).fill(0), 3))
  const cell = ([column, row], [du, dv]) => [(column + du) / 8, (row + dv) / 4]
  const uv = [
    ...[
      [0.2, 0.2],
      [0.8, 0.2],
      [0.5, 0.8],
    ].flatMap((offset) => cell([0, 1], offset)),
    ...[
      [0.2, 0.2],
      [0.8, 0.2],
      [0.5, 0.8],
    ].flatMap((offset) => cell([1, 2], offset)),
  ]
  geometry.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2))
  return geometry
}

const cellsOf = (geometry) => {
  const uv = geometry.getAttribute('uv')
  return Array.from({ length: uv.count }, (_, i) => [
    Math.floor(uv.getX(i) * 8),
    Math.floor(uv.getY(i) * 4),
  ])
}

describe('island3d/familyMeshes 換衣服配色（#201 驗收條件 5）', () => {
  it('衣服色塊裡的三角形整個搬到換成的色塊，皮膚色塊的三角形不動，原本的形體不變', () => {
    const original = twoTriangles()
    const recolored = recolorGeometry(original, [
      [
        [0, 1],
        [4, 2],
      ],
    ])

    expect(cellsOf(recolored)).toEqual([
      [4, 2],
      [4, 2],
      [4, 2],
      [1, 2],
      [1, 2],
      [1, 2],
    ])
    expect(cellsOf(original).slice(0, 3)).toEqual([
      [0, 1],
      [0, 1],
      [0, 1],
    ])
  })

  it('沒有要換的色塊：UV 不變', () => {
    expect(cellsOf(recolorGeometry(twoTriangles(), []))).toEqual(cellsOf(twoTriangles()))
  })
})
