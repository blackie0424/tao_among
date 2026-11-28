import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

// GLB：12 位元組的檔頭，接著第一個區塊是 glTF 的 JSON（區塊長度在第 12 位元組、內容從第 20 位元組開始）
const HEADER_BYTES = 12
const CHUNK_HEADER_BYTES = 8
const FLOAT_BYTES = 4
// 軌道每一格有幾個數：位置、縮放 3 個，旋轉（四元數）4 個
const COMPONENTS = { VEC3: 3, VEC4: 4 }
const MOVE_EPSILON = 1e-4

function readGlb(model) {
  const bytes = readFileSync(resolve(process.cwd(), `public/island3d/animals/${model}.glb`))
  const jsonLength = bytes.readUInt32LE(HEADER_BYTES)
  const jsonStart = HEADER_BYTES + CHUNK_HEADER_BYTES
  const gltf = JSON.parse(bytes.subarray(jsonStart, jsonStart + jsonLength).toString('utf8'))
  // 二進位區塊緊接在 JSON 區塊之後（JSON 區塊長度已補齊到 4 的倍數）
  const binStart = jsonStart + jsonLength + CHUNK_HEADER_BYTES
  return { gltf, bin: bytes.subarray(binStart) }
}

/** 骨架的節點：關節，加上關節往上的每一層（骨架本身） */
function skeletonNodes(gltf) {
  const parentOf = new Map()
  gltf.nodes.forEach((node, i) => (node.children ?? []).forEach((child) => parentOf.set(child, i)))
  const nodes = new Set()
  gltf.skins[0].joints.forEach((joint) => {
    for (let node = joint; node !== undefined; node = parentOf.get(node)) {
      nodes.add(node)
    }
  })
  return nodes
}

/** 一段動作的長度：所有軌道時間軸（sampler.input）最大值裡最大的那個 */
function clipSeconds({ gltf }, animation) {
  return Math.max(...animation.samplers.map(({ input }) => gltf.accessors[input].max[0]))
}

describe('island3d 動物模型檔（#222 驗收條件 3）', () => {
  ;['ino', 'cito', 'kois', 'kagling'].forEach((model) => {
    it(`${model}.glb 有 idle、walk 兩個動作，長度大於 0，軌道都指向骨架的節點`, () => {
      const glb = readGlb(model)
      const { gltf } = glb
      expect(gltf.animations.map((animation) => animation.name).sort()).toEqual(['idle', 'walk'])
      expect(gltf.skins).toHaveLength(1)
      const skeleton = skeletonNodes(gltf)
      gltf.animations.forEach((animation) => {
        expect(clipSeconds(glb, animation)).toBeGreaterThan(0)
        expect(animation.channels.length).toBeGreaterThan(0)
        animation.channels.forEach(({ target }) => expect(skeleton.has(target.node)).toBe(true))
      })
    })

    it(`${model}.glb 的走路動作真的會動：至少一條軌道前後的值不一樣`, () => {
      const glb = readGlb(model)
      const { gltf, bin } = glb
      const walk = gltf.animations.find((animation) => animation.name === 'walk')
      const changes = walk.samplers.some(({ output }) => {
        const accessor = gltf.accessors[output]
        const width = COMPONENTS[accessor.type]
        const start = bin.byteOffset + (gltf.bufferViews[accessor.bufferView].byteOffset ?? 0)
        const from = start + (accessor.byteOffset ?? 0)
        const values = new Float32Array(
          bin.buffer.slice(from, from + accessor.count * width * FLOAT_BYTES)
        )
        // 和第一格的同一個分量比，有差就是會動
        return values.some((value, i) => Math.abs(value - values[i % width]) > MOVE_EPSILON)
      })
      expect(changes).toBe(true)
    })
  })

  it('附授權檔：寫明 CC0 與四個檔的來源', () => {
    const license = readFileSync(
      resolve(process.cwd(), 'public/island3d/animals/License.txt'),
      'utf8'
    )
    expect(license).toContain('CC0')
    ;['ino.glb', 'cito.glb', 'kois.glb', 'kagling.glb', 'Quaternius', 'tomek'].forEach((text) =>
      expect(license).toContain(text)
    )
  })
})
