import { describe, expect, it } from 'vitest'
import { ISLAND_DECOR, ISLAND_MAP } from '@/island3d/islandMap'
import { isTapBlocked, tapBlockers } from '@/island3d/occlusion'
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
