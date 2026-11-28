// 魚島 3D：家屋或地標擋在角色胸口和鏡頭之間時，那一件變半透明（#228 驗收條件 5）。
// 家屋四件是每種一個 InstancedMesh（18 戶共用材質），擋住的那一戶在 InstancedMesh 裡縮成 0 藏起來，
// 同位置放一個共用形體、半透明材質的替身；地標是各自的群組，擋住時把群組裡網格的材質換成半透明的複本。
import * as THREE from 'three'

// 擋住時的不透明度
const SEE_THROUGH_OPACITY = 0.25
// 藏起來的那一戶：縮成 0
const HIDDEN = new THREE.Matrix4().makeScale(0, 0, 0)

/** 材質的半透明複本（不改原本的材質，共用它的其他件不受影響） */
function seeThrough(material) {
  if (Array.isArray(material)) {
    return material.map(seeThrough)
  }
  const copy = material.clone()
  copy.transparent = true
  copy.opacity = SEE_THROUGH_OPACITY
  return copy
}

function disposeMaterial(material) {
  ;[material].flat().forEach((each) => each.dispose())
}

const ignoreRaycast = () => {}

/** 群組裡所有的網格（不含招牌之類的 Sprite） */
function meshesOf(object) {
  const meshes = []
  object.traverse((child) => {
    if (child.isMesh) {
      meshes.push(child)
    }
  })
  return meshes
}

/**
 * 一種家屋：hidden 是藏起來的戶（第幾份 → { original 原本的位置矩陣, proxy 替身 }）。
 * 判斷前先把藏起來的戶放回原位，這樣仍照原位判斷；判斷完再依結果藏起來或恢復
 */
function createHouseholdKind(mesh, proxies) {
  const material = seeThrough(mesh.material)
  const hidden = new Map()

  const hide = (instanceId) => {
    const original = new THREE.Matrix4()
    mesh.getMatrixAt(instanceId, original)
    const proxy = new THREE.Mesh(mesh.geometry, material)
    proxy.matrixAutoUpdate = false
    proxy.matrix.multiplyMatrices(mesh.matrixWorld, original)
    proxy.raycast = ignoreRaycast
    proxies.add(proxy)
    hidden.set(instanceId, { original, proxy })
  }

  const show = (instanceId) => {
    proxies.remove(hidden.get(instanceId).proxy)
    hidden.delete(instanceId)
  }

  return {
    mesh,
    putBack() {
      hidden.forEach(({ original }, instanceId) => mesh.setMatrixAt(instanceId, original))
    },
    apply(blocked) {
      const changed =
        blocked.size !== hidden.size || [...blocked].some((instanceId) => !hidden.has(instanceId))
      ;[...hidden.keys()].filter((instanceId) => !blocked.has(instanceId)).forEach(show)
      ;[...blocked].filter((instanceId) => !hidden.has(instanceId)).forEach(hide)
      hidden.forEach((_, instanceId) => mesh.setMatrixAt(instanceId, HIDDEN))
      if (changed) {
        mesh.instanceMatrix.needsUpdate = true
      }
    },
  }
}

/** 地標：faded 是變半透明的群組 → （網格 → 原本的材質） */
function createLandmarkFader() {
  const faded = new Map()

  // 模型可能在半透明期間才載入：每次都補上群組裡還沒換的網格
  const fade = (landmark) => {
    const originals = faded.get(landmark) ?? new Map()
    faded.set(landmark, originals)
    meshesOf(landmark)
      .filter((mesh) => !originals.has(mesh))
      .forEach((mesh) => {
        originals.set(mesh, mesh.material)
        mesh.material = seeThrough(mesh.material)
      })
  }

  const restore = (landmark) => {
    faded.get(landmark).forEach((original, mesh) => {
      disposeMaterial(mesh.material)
      mesh.material = original
    })
    faded.delete(landmark)
  }

  return (blocked) => {
    ;[...faded.keys()].filter((landmark) => !blocked.has(landmark)).forEach(restore)
    blocked.forEach(fade)
  }
}

/**
 * households 是家屋四件的 InstancedMesh（landmarkMeshes.js 的 buildHouseholds 的每一種），
 * landmarks 是每個地標的群組。回傳 { group 替身（要加進場景）, update(chest, camera) 每張畫面呼叫 }：
 * chest 是角色胸口、camera 是鏡頭的位置（都是 { x, y, z }）
 */
export function createCameraOcclusion({ households, landmarks }) {
  const group = new THREE.Group()
  const raycaster = new THREE.Raycaster()
  const kinds = households.map((mesh) => createHouseholdKind(mesh, group))
  const fadeLandmarks = createLandmarkFader()
  const from = new THREE.Vector3()
  const toward = new THREE.Vector3()

  return {
    group,
    update(chest, camera) {
      from.set(chest.x, chest.y, chest.z)
      toward.set(camera.x, camera.y, camera.z).sub(from)
      raycaster.far = toward.length()
      raycaster.set(from, toward.normalize())

      kinds.forEach((kind) => kind.putBack())
      kinds.forEach((kind) => {
        const hits = raycaster.intersectObject(kind.mesh, false)
        kind.apply(new Set(hits.map((hit) => hit.instanceId)))
      })

      const blocked = landmarks.filter(
        (landmark) => raycaster.intersectObjects(meshesOf(landmark), false).length > 0
      )
      fadeLandmarks(new Set(blocked))
    },
  }
}
