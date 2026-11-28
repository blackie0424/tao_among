import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { createFamily, recolorGeometry } from '@/island3d/familyMeshes'

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

// ── 走近家人時他停下來面向玩家（#219） ──

const CLIP_NAMES = ['idle', 'walk', 'sit', 'wheelchair-look-left', 'wheelchair-look-right']

/** 馬上載入完成的模型：一個方塊＋每個動作各一段空的動畫 */
const instantLoader = {
  load(url, onLoad) {
    const scene = new THREE.Group()
    scene.add(new THREE.Mesh(new THREE.BoxGeometry(0.5, 1, 0.5)))
    onLoad({ scene, animations: CLIP_NAMES.map((name) => new THREE.AnimationClip(name, 1, [])) })
  },
}

const look = { model: 'a', palette: 0 }

/**
 * 手建的走動家人：在 x 軸上從 x0 - 3 走到 x0 + 3，每秒 1，兩端各停 2 秒。
 * 例：0～2 秒停在 x0 - 3，3 秒在 x0 - 2，3.5 秒在 x0 - 1.5。
 */
const walker = (spot, x0) => ({
  tribe: 'iraraley',
  hex: 0,
  spot,
  look,
  zone: { x: x0, z: 0, radius: 5 },
  heading: 0,
  index: 0,
  route: { from: { x: x0 - 3, z: 0 }, to: { x: x0 + 3, z: 0 }, speed: 1, pauseSeconds: 2 },
})

/** 手建的 akay：坐在 (20, 0)，第 0 格的戶；0～8 秒 sit、8～16 秒 wheelchair-look-left */
const akay = {
  tribe: 'iraraley',
  hex: 0,
  spot: 'akay',
  look,
  zone: { x: 20, z: 0, radius: 2 },
  heading: 0,
  index: 2,
  seat: { x: 20, z: 0 },
}

function familyOf(members) {
  const family = createFamily(instantLoader, members)
  const at = (i) => {
    const { position, rotation } = family.figures[i].group
    return { x: position.x, z: position.z, heading: rotation.y, pose: family.figures[i].pose }
  }
  /** 從 from 秒到 to 秒，每 0.5 秒一張畫面 */
  const run = (from, to, options) => {
    for (let t = from; t <= to + 1e-9; t += 0.5) {
      family.update(t, 0.5, {
        currentTribe: 'iraraley',
        reducedMotion: false,
        spotKey: null,
        player: { x: 0, z: 0 },
        ...options,
      })
    }
  }
  return { at, run }
}

describe('island3d/familyMeshes 走近家人時他停下來面向玩家（#219 驗收條件 1）', () => {
  it('玩家走進 A 的範圍：A 停在原地、站著、面向玩家；B 照舊走', () => {
    const { at, run } = familyOf([walker('A', 0), walker('B', 10)])
    run(0, 3)
    expect(at(0)).toMatchObject({ x: -2, z: 0, pose: 'walk' })

    run(3.5, 3.5, { spotKey: 'A', player: { x: -2, z: -2 } })
    expect(at(0).x).toBeCloseTo(-2)
    expect(at(0).z).toBeCloseTo(0)
    expect(at(0).pose).toBe('idle')
    expect(at(0).heading).toBeCloseTo(Math.PI)
    expect(at(1)).toMatchObject({ x: 8.5, z: 0, pose: 'walk' })

    run(4, 6, { spotKey: 'A', player: { x: 0, z: 0 } })
    expect(at(0).x).toBeCloseTo(-2)
    expect(at(0).heading).toBeCloseTo(Math.PI / 2)
    expect(at(1).x).toBeCloseTo(11)
  })
})

describe('island3d/familyMeshes 離開後從停下的位置接著走（#219 驗收條件 2）', () => {
  it('離開那一刻的位置等於停下時的位置，之後照原本的路線走', () => {
    const { at, run } = familyOf([walker('A', 0)])
    run(0, 3)
    run(3.5, 6, { spotKey: 'A', player: { x: -2, z: -2 } })

    run(6.5, 6.5)
    expect(at(0)).toMatchObject({ x: -2, z: 0 })
    run(7, 7)
    expect(at(0)).toMatchObject({ x: -1.5, z: 0, pose: 'walk', heading: Math.PI / 2 })
    run(7.5, 8)
    expect(at(0).x).toBeCloseTo(-0.5)
  })
})

describe('island3d/familyMeshes akay 在範圍裡不換坐姿（#219 驗收條件 3）', () => {
  it('進入那一刻是 sit：待到原本該換姿勢的時間還是 sit；離開後照每 8 秒換一次', () => {
    const { at, run } = familyOf([akay])
    run(0, 7.5)
    expect(at(0).pose).toBe('sit')

    run(8, 12, { spotKey: 'akay', player: { x: 20, z: 2 } })
    expect(at(0).pose).toBe('sit')
    expect(at(0)).toMatchObject({ x: 20, z: 0, heading: 0 })

    run(12.5, 12.5)
    expect(at(0).pose).toBe('sit')
    run(13, 13)
    expect(at(0).pose).toBe('wheelchair-look-left')
  })

  it('減少動態效果：玩家進出範圍都一樣固定 sit', () => {
    const { at, run } = familyOf([akay])
    run(0, 12, { reducedMotion: true, spotKey: 'akay', player: { x: 20, z: 2 } })
    expect(at(0).pose).toBe('sit')
    run(12.5, 20, { reducedMotion: true })
    expect(at(0).pose).toBe('sit')
  })
})

describe('island3d/familyMeshes 玩家停下時轉向家人（#219 規格修訂 1，驗收條件 4）', () => {
  it('在 A 的範圍裡：玩家該朝的方向＝玩家位置指向 A 停下的位置', () => {
    const family = createFamily(instantLoader, [walker('A', 0), walker('B', 10)])
    const frame = (seconds, spotKey) =>
      family.update(seconds, 0.5, {
        currentTribe: 'iraraley',
        reducedMotion: false,
        spotKey,
        player: { x: -2, z: -2 },
      })
    frame(3, null)
    frame(3.5, 'A')
    expect(family.facingFor('A', { x: -2, z: -2 })).toEqual({ x: 0, z: 2 })
    expect(family.facingFor('A', { x: 1, z: 0 })).toEqual({ x: -3, z: 0 })
  })

  it('akay 也一樣：指向 akay 坐的位置', () => {
    const family = createFamily(instantLoader, [akay])
    family.update(1, 0.5, {
      currentTribe: 'iraraley',
      reducedMotion: false,
      spotKey: 'akay',
      player: { x: 20, z: 2 },
    })
    expect(family.facingFor('akay', { x: 20, z: 2 })).toEqual({ x: 0, z: -2 })
  })

  it('不在任何範圍裡、或範圍裡沒有這位家人：沒有指定方向（玩家維持最後走的方向）', () => {
    const family = createFamily(instantLoader, [walker('A', 0)])
    family.update(3, 0.5, {
      currentTribe: 'iraraley',
      reducedMotion: false,
      spotKey: null,
      player: { x: 0, z: 0 },
    })
    expect(family.facingFor(null, { x: 0, z: 0 })).toBeNull()
    expect(family.facingFor('nobody', { x: 0, z: 0 })).toBeNull()
  })
})
