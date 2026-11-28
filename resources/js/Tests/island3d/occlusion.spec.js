import { describe, expect, it } from 'vitest'
import { ISLAND_DECOR, ISLAND_MAP } from '@/island3d/islandMap'
import { isBlockedByTerrain, isTapBlocked, tapBlockers } from '@/island3d/occlusion'
import { heightField, islandHeightField } from '@/island3d/terrainHeight'
import { createWalkable } from '@/island3d/walkable'

// 一座山：山腳在原點、半徑 10、高 20。鏡頭在南邊 (0, 30, 40) 往北看。
const MOUNTAIN = { x: 0, z: 0, radius: 10, height: 20 }
const CAMERA = { x: 0, y: 30, z: 40 }

describe('island3d/occlusion 點到的是不是山或船（#200 驗收條件 4）', () => {
  it('地面那一點在山的正後方、畫面上被山擋住：點到的是山', () => {
    // 視線在山的中軸上方 8.2 高的地方穿過，那裡山的半徑還有 5.9
    expect(isTapBlocked(CAMERA, { x: 0, z: -15 }, [MOUNTAIN])).toBe(true)
  })

  it('地面那一點在山腳裡：點到的是山', () => {
    expect(isTapBlocked(CAMERA, { x: 3, z: 2 }, [MOUNTAIN])).toBe(true)
  })

  it('地面那一點在山的前面（鏡頭和山之間）：沒有被擋', () => {
    expect(isTapBlocked(CAMERA, { x: 0, z: 25 }, [MOUNTAIN])).toBe(false)
  })

  it('地面那一點在山的旁邊：沒有被擋', () => {
    expect(isTapBlocked(CAMERA, { x: 30, z: -15 }, [MOUNTAIN])).toBe(false)
  })

  it('地面那一點在山後面很遠、視線從山頂上方越過：沒有被擋', () => {
    // 視線經過山的中軸時高 21.4，比山頂（20）高
    expect(isTapBlocked(CAMERA, { x: 0, z: -100 }, [MOUNTAIN])).toBe(false)
  })

  it('視線擦過山的側面：看山在那個高度的半徑，擦到就算擋住、差一點就不算', () => {
    // 視線經過山的中軸旁邊時高約 8.2，那裡山的半徑是 5.9
    expect(isTapBlocked(CAMERA, { x: 7, z: -15 }, [MOUNTAIN])).toBe(true)
    expect(isTapBlocked(CAMERA, { x: 12, z: -15 }, [MOUNTAIN])).toBe(false)
  })

  it('鏡頭在山的正上方往下看山腳外面：沒有被擋', () => {
    expect(isTapBlocked({ x: 0, y: 100, z: 0.1 }, { x: 14, z: 0 }, [MOUNTAIN])).toBe(false)
  })

  it('有好幾座山時，被其中一座擋住就算', () => {
    const far = { x: 200, z: 200, radius: 10, height: 20 }

    expect(isTapBlocked(CAMERA, { x: 0, z: -15 }, [far, MOUNTAIN])).toBe(true)
    expect(isTapBlocked(CAMERA, { x: 0, z: -15 }, [far])).toBe(false)
  })

  it('沒有山也沒有船：都不擋', () => {
    expect(isTapBlocked(CAMERA, { x: 0, z: -15 }, [])).toBe(false)
  })
})

describe('island3d/occlusion 魚島上的船', () => {
  const walkable = createWalkable(ISLAND_MAP)
  const blockers = tapBlockers(ISLAND_DECOR)

  it('會擋點擊的是每艘船（山在 #223 改成地形，另外判定）', () => {
    expect(blockers).toHaveLength(ISLAND_DECOR.boats.length)
  })

  it('點在船上（船在海上）：算被擋', () => {
    const [boat] = ISLAND_DECOR.boats

    expect(isTapBlocked({ x: boat.x, y: 30, z: boat.z + 30 }, boat, blockers)).toBe(true)
  })
})

// ── 地形擋住點擊（#223 驗收條件 5）──

// 很大的陸地：手建的高度表都在裡面，沒有海邊收坡
const BIG_LAND = [
  { x: -1000, z: -1000 },
  { x: 1000, z: -1000 },
  { x: 1000, z: 1000 },
  { x: -1000, z: 1000 },
]
// 1 公尺換成的世界單位（0.03375 × 1.5），手建高度表用公尺寫
const UNITS_PER_METER = 0.03375 * 1.5

/** 手建的高度表：從 (-40, -40) 起每 2 單位一點、41 × 41；metersAt(x, z) 決定每點的公尺數 */
function field(metersAt) {
  const meters = []
  for (let row = 0; row < 41; row += 1) {
    for (let col = 0; col < 41; col += 1) {
      meters.push(metersAt(-40 + col * 2, -40 + row * 2))
    }
  }
  return heightField(
    { x0: -40, z0: -40, step: 2, cols: 41, rows: 41, meters },
    { zones: [], inland: BIG_LAND }
  )
}

describe('island3d/occlusion 地形擋住點擊（#223 驗收條件 5）', () => {
  // 一道南北走向的山脊：只有 x = 0 那一排網格點高 H，兩旁是 0，所以 z = 0 這條線上高度是 H × (1 − |x| / 2)
  const RIDGE_METERS = 200
  const H = RIDGE_METERS * UNITS_PER_METER
  const ridge = field((x) => (x === 0 ? RIDGE_METERS : 0))
  // 鏡頭在西邊 (-20, y, 0)，看向東邊地面 (20, 0, 0)：直線在 x = 0 的高度是 y / 2
  const cameraAt = (heightAtRidge) => ({ x: -20, y: heightAtRidge * 2, z: 0 })
  const ground = { x: 20, z: 0 }

  it('短交會：直線只在山脊頂下 0.05 穿過（鑽進地形的那段不到 0.1 單位寬），也算擋住', () => {
    expect(isBlockedByTerrain(cameraAt(H - 0.05), ground, ridge)).toBe(true)
  })

  it('擦邊：直線剛好碰到山脊頂（差不到 0.01）不算擋住', () => {
    expect(isBlockedByTerrain(cameraAt(H - 0.005), ground, ridge)).toBe(false)
    expect(isBlockedByTerrain(cameraAt(H), ground, ridge)).toBe(false)
  })

  it('看得到的平地：鏡頭和地面那一點之間沒有山，不擋', () => {
    expect(isBlockedByTerrain({ x: -20, y: 30, z: 0 }, { x: -5, z: 0 }, ridge)).toBe(false)
  })

  it('被山擋住的平地：山後面的地面點，鏡頭低時擋住、拉高越過山頂就不擋', () => {
    const hill = field((x, z) => Math.max(0, 400 - 40 * Math.hypot(x, z)))
    expect(isBlockedByTerrain({ x: 0, y: 10, z: 30 }, { x: 0, z: -20 }, hill)).toBe(true)
    expect(isBlockedByTerrain({ x: 0, y: 200, z: 30 }, { x: 0, z: -20 }, hill)).toBe(false)
  })

  it('終點可以離地（#228 角色胸口）：同一個鏡頭，看地面被山脊擋住，看離地 2.4 的那一點剛好越過', () => {
    // 直線在 x = 0 的高度是（鏡頭高 + 終點高）÷ 2：看地面時比山脊頂低 1.2，看離地 2.4 時剛好碰到山脊頂
    const camera = { x: -20, y: H * 2 - 2.4, z: 0 }

    expect(isBlockedByTerrain(camera, { x: 20, z: 0 }, ridge)).toBe(true)
    expect(isBlockedByTerrain(camera, { x: 20, y: 2.4, z: 0 }, ridge)).toBe(false)
    expect(isBlockedByTerrain(camera, { x: 20, y: 2.3, z: 0 }, ridge)).toBe(true)
  })

  it('點在山坡上：對到的地面點在山的底下，算擋住', () => {
    const hill = field((x, z) => Math.max(0, 400 - 40 * Math.hypot(x, z)))
    expect(isBlockedByTerrain({ x: 0, y: 30, z: 30 }, { x: 0, z: 2 }, hill)).toBe(true)
  })
})

describe('island3d/occlusion 魚島的地形（#223 驗收條件 5）', () => {
  const walkable = createWalkable(ISLAND_MAP)
  const island = islandHeightField()
  // 橫貫公路上的一點 (30.75, 23.55)；從西北邊低處看過去，中間隔著山田山（約 (24.5, -0.8)）
  const onCrossRoad = { x: 30.75, z: 23.55 }

  it('從低處看，山田山遮住後面的橫貫公路：點那裡沒有反應', () => {
    expect(walkable.isWalkable(onCrossRoad)).toBe(true)
    expect(isBlockedByTerrain({ x: 13.95, y: 18, z: -41.25 }, onCrossRoad, island)).toBe(true)
  })

  it('同一個位置把鏡頭拉高、視線越過山頂：點得到橫貫公路', () => {
    expect(isBlockedByTerrain({ x: 13.95, y: 105, z: -41.25 }, onCrossRoad, island)).toBe(false)
  })

  it('部落格子的中心從正上方看：不擋', () => {
    ISLAND_MAP.tribes.forEach(({ center }) => {
      expect(isBlockedByTerrain({ x: center.x, y: 60, z: center.z + 1 }, center, island)).toBe(
        false
      )
    })
  })
})
