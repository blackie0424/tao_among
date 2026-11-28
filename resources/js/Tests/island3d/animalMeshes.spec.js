import { describe, expect, it } from 'vitest'
import * as THREE from 'three'
import { createAnimals, fitAnimal } from '@/island3d/animalMeshes'

const flat = { x0: -500, z0: -500, step: 1000, cols: 2, rows: 2, heights: new Float32Array(4) }

/**
 * 手建的小模型：高 1 的方塊，全部頂點綁在子骨頭上。
 * idle 不動；walk 在 0.5 秒時把子骨頭抬高 0.5（最高點從 1 變 1.5），1 秒時回來。
 */
function riggedModel() {
  const geometry = new THREE.BoxGeometry(0.6, 1, 0.4)
  geometry.translate(0, 0.5, 0)
  const count = geometry.getAttribute('position').count
  geometry.setAttribute(
    'skinIndex',
    new THREE.Uint16BufferAttribute(
      new Array(count * 4).fill(0).map((_, i) => (i % 4 === 0 ? 1 : 0)),
      4
    )
  )
  geometry.setAttribute(
    'skinWeight',
    new THREE.Float32BufferAttribute(
      new Array(count * 4).fill(0).map((_, i) => (i % 4 === 0 ? 1 : 0)),
      4
    )
  )
  const root = new THREE.Bone()
  root.name = 'root'
  const lift = new THREE.Bone()
  lift.name = 'lift'
  root.add(lift)
  const mesh = new THREE.SkinnedMesh(geometry, new THREE.MeshLambertMaterial({ name: 'body' }))
  mesh.add(root)
  mesh.bind(new THREE.Skeleton([root, lift]))
  const scene = new THREE.Group()
  scene.add(mesh)
  const animations = [
    new THREE.AnimationClip('idle', 1, [
      new THREE.VectorKeyframeTrack('lift.position', [0, 1], [0, 0, 0, 0, 0, 0]),
    ]),
    new THREE.AnimationClip('walk', 1, [
      new THREE.VectorKeyframeTrack('lift.position', [0, 0.5, 1], [0, 0, 0, 0, 0.5, 0, 0, 0, 0]),
    ]),
  ]
  return { scene, animations }
}

/** 自己量：把 model 的某個動作播到 seconds 秒，量整個模型最高、最低點（含骨架變形） */
function measure(model, animations, name, seconds) {
  const mixer = new THREE.AnimationMixer(model)
  mixer.clipAction(THREE.AnimationClip.findByName(animations, name)).play()
  mixer.setTime(seconds)
  model.updateMatrixWorld(true)
  const box = new THREE.Box3().setFromObject(model, true)
  mixer.stopAllAction()
  return box
}

/** 假的載入器：記下每個網址的成功、失敗回呼，測試自己決定什麼時候載入完 */
function fakeLoader() {
  const pending = new Map()
  return {
    load(url, onLoad, _onProgress, onError) {
      pending.set(url, { onLoad, onError })
    },
    succeed(url, gltf) {
      pending.get(url).onLoad(gltf)
    },
    fail(url) {
      pending.get(url).onError(new Error('404'))
    },
    urls: () => [...pending.keys()],
  }
}

const animal = (model, x, z) => ({
  kind: model,
  name: model,
  model,
  height: 0.5,
  coat: { body: '#2b2724' },
  place: 'test',
  group: 0,
  route: { from: { x, z }, to: { x: x + 2, z }, speed: 0.5, pauseSeconds: 3 },
  startSeconds: 0,
})

const quietLabel = () => new THREE.SpriteMaterial()

describe('island3d/animalMeshes 比人小（#222 驗收條件 3）', () => {
  it('縮放以站著、走路兩個動作全程的最高點為準：每一刻都不超過設定高度（容差 0.01），最高那一刻等於設定高度', () => {
    const { scene, animations } = riggedModel()
    fitAnimal(scene, animations, 0.7)
    const moments = [0, 0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9, 1]
    const tops = ['idle', 'walk'].flatMap((name) =>
      moments.map((t) => measure(scene, animations, name, t).max.y)
    )
    tops.forEach((top) => expect(top).toBeLessThanOrEqual(0.7 + 0.01))
    expect(Math.max(...tops)).toBeCloseTo(0.7, 2)
  })

  it('站著時腳在地面上（最低點 0）', () => {
    const { scene, animations } = riggedModel()
    fitAnimal(scene, animations, 0.7)
    expect(measure(scene, animations, 'idle', 0).min.y).toBeCloseTo(0, 2)
  })
})

describe('island3d/animalMeshes 名稱跟著動物出現（#222 驗收條件 5）', () => {
  const setup = () => {
    const loader = fakeLoader()
    const animals = [animal('kois', 0, 0), animal('ino', 50, 0)]
    const world = createAnimals(loader, { animals, field: flat, labelMaterial: quietLabel })
    return { loader, world }
  }
  const nextTo = (x) => ({ player: { x: x + 1, z: 0 }, reducedMotion: true })

  it('模型載入中：動物不畫、名稱也不顯示（玩家就在旁邊也一樣）', () => {
    const { loader, world } = setup()
    expect(loader.urls().sort()).toEqual([
      '/island3d/animals/ino.glb',
      '/island3d/animals/kois.glb',
    ])
    world.update(0, 0.016, nextTo(0))
    expect(world.figures[0].body.visible).toBe(false)
    expect(world.figures[0].label.visible).toBe(false)
  })

  it('載入成功：動物畫出來，名稱 4 以內才顯示、走遠就不顯示', () => {
    const { loader, world } = setup()
    loader.succeed('/island3d/animals/kois.glb', riggedModel())
    world.update(0, 0.016, nextTo(0))
    expect(world.figures[0].body.visible).toBe(true)
    expect(world.figures[0].label.visible).toBe(true)
    world.update(0.016, 0.016, { player: { x: 0, z: 4.5 }, reducedMotion: true })
    expect(world.figures[0].label.visible).toBe(false)
  })

  it('載入失敗：那一種動物不畫、名稱不顯示；另一種照常', () => {
    const { loader, world } = setup()
    loader.fail('/island3d/animals/ino.glb')
    loader.succeed('/island3d/animals/kois.glb', riggedModel())
    world.update(0, 0.016, nextTo(50))
    expect(world.figures[1].body.visible).toBe(false)
    expect(world.figures[1].label.visible).toBe(false)
    world.update(0.016, 0.016, nextTo(0))
    expect(world.figures[0].body.visible).toBe(true)
    expect(world.figures[0].label.visible).toBe(true)
  })

  it('名稱在動物頭上：比設定高度高', () => {
    const { loader, world } = setup()
    loader.succeed('/island3d/animals/kois.glb', riggedModel())
    world.update(0, 0.016, nextTo(0))
    const label = new THREE.Vector3()
    world.figures[0].label.getWorldPosition(label)
    expect(label.y).toBeGreaterThan(0.5)
  })
})

describe('island3d/animalMeshes 不擋路、不能點（#222 驗收條件 6）', () => {
  it('點擊的射線穿過動物和名稱時，什麼都點不到', () => {
    const loader = fakeLoader()
    const world = createAnimals(loader, {
      animals: [animal('kois', 0, 0)],
      field: flat,
      labelMaterial: quietLabel,
    })
    loader.succeed('/island3d/animals/kois.glb', riggedModel())
    world.update(0, 0.016, { player: { x: 1, z: 0 }, reducedMotion: true })
    world.group.updateMatrixWorld(true)
    const raycaster = new THREE.Raycaster(new THREE.Vector3(0, 10, 0), new THREE.Vector3(0, -1, 0))
    raycaster.camera = new THREE.PerspectiveCamera()
    expect(raycaster.intersectObject(world.group, true)).toEqual([])
  })
})
