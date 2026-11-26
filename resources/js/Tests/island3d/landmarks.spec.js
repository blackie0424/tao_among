import { describe, expect, it } from 'vitest'
import { closestOnSegment, hexVertices } from '@/island3d/hexBounds'
import { ISLAND_DECOR, ISLAND_MAP } from '@/island3d/islandMap'
import {
  LANDMARKS,
  footprintCorners,
  footprintGapToPoint,
  footprintGapToSegment,
  footprintInside,
  footprintsOverlap,
  shapeParts,
  signsVisible,
} from '@/island3d/landmarks'
import { createWalkable } from '@/island3d/walkable'

const center = (code) => ISLAND_MAP.tribes.find((tribe) => tribe.code === code).center
const distanceBetween = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const edgesOf = (polygon) => polygon.map((from, i) => [from, polygon[(i + 1) % polygon.length]])
const walkable = createWalkable(ISLAND_MAP)
/** 離海岸線多遠 */
const coastGap = (point) =>
  Math.min(
    ...edgesOf(ISLAND_DECOR.coast).map(([a, b]) =>
      distanceBetween(point, closestOnSegment(point, a, b))
    )
  )
const roadSegments = ISLAND_MAP.roads.flatMap(({ points, closed }) => {
  const ends = closed ? [...points, points[0]] : points
  return ends.slice(1).map((to, i) => [ends[i], to])
})
const roadGap = (corners) =>
  Math.min(...roadSegments.map(([a, b]) => footprintGapToSegment(corners, a, b)))
const find = (tribe, sign) =>
  LANDMARKS.find((landmark) => landmark.tribe === tribe && landmark.sign === sign)
// 從部落中心看過去的方向：dx 往東為正、dz 往南為正
const offset = (landmark) => ({
  dx: landmark.x - center(landmark.tribe).x,
  dz: landmark.z - center(landmark.tribe).z,
})

describe('island3d/landmarks 佔地的判斷（#207 驗收條件 6，手建的小例子）', () => {
  const square = (x, z, heading = 0) => ({ x, z, width: 2, depth: 2, heading })

  it('佔地是以位置為中心、依朝向旋轉的長方形', () => {
    const corners = footprintCorners({ x: 10, z: 20, width: 4, depth: 2, heading: 0 })

    expect(corners).toHaveLength(4)
    expect(corners.map((c) => c.x).sort((a, b) => a - b)).toEqual([8, 8, 12, 12])
    expect(corners.map((c) => c.z).sort((a, b) => a - b)).toEqual([19, 19, 21, 21])
  })

  it('轉 90 度：寬與深對調', () => {
    const corners = footprintCorners({ x: 0, z: 0, width: 4, depth: 2, heading: Math.PI / 2 })

    expect(Math.max(...corners.map((c) => c.x))).toBeCloseTo(1, 9)
    expect(Math.max(...corners.map((c) => c.z))).toBeCloseTo(2, 9)
  })

  it('轉 45 度的方塊，一角伸到路上：離公路中線的距離小於半寬', () => {
    // 公路沿 z = 0，半寬 2；方塊中心在 z = 3，沒轉時最近的邊在 z = 2（剛好不壓）
    const road = [
      { x: -10, z: 0 },
      { x: 10, z: 0 },
    ]
    const straight = footprintCorners(square(0, 3.01))
    const turned = footprintCorners(square(0, 3.01, Math.PI / 4))

    expect(footprintGapToSegment(straight, ...road)).toBeGreaterThan(2)
    expect(footprintGapToSegment(turned, ...road)).toBeLessThan(2)
    expect(footprintGapToSegment(turned, ...road)).toBeCloseTo(3.01 - Math.SQRT2, 9)
  })

  it('公路穿過佔地：距離是 0', () => {
    const corners = footprintCorners(square(0, 0))

    expect(footprintGapToSegment(corners, { x: -10, z: 0 }, { x: 10, z: 0 })).toBe(0)
    expect(footprintGapToSegment(corners, { x: -0.5, z: 0 }, { x: 0.5, z: 0 })).toBe(0)
  })

  it('中心分開但邊角交疊的兩個長方形：重疊', () => {
    const a = footprintCorners(square(0, 0))
    const b = footprintCorners(square(2.2, 0, Math.PI / 4))

    expect(footprintsOverlap(a, b)).toBe(true)
  })

  it('相鄰但不交疊：不重疊', () => {
    const a = footprintCorners(square(0, 0))

    expect(footprintsOverlap(a, footprintCorners(square(2.01, 0)))).toBe(false)
    expect(footprintsOverlap(a, footprintCorners(square(2.5, 0, Math.PI / 4)))).toBe(false)
  })

  it('剛好共用一條邊或一個角（中間沒有空隙也沒有交疊）：不重疊', () => {
    const a = footprintCorners(square(0, 0))

    // 邊長 2、中心相距 2：共用 x = 1 那條邊
    expect(footprintsOverlap(a, footprintCorners(square(2, 0)))).toBe(false)
    expect(footprintsOverlap(footprintCorners(square(2, 0)), a)).toBe(false)
    // 只碰到 (1, 1) 這個角
    expect(footprintsOverlap(a, footprintCorners(square(2, 2)))).toBe(false)
    // 轉 45 度的方塊，一個角剛好頂在 a 的邊上
    expect(footprintsOverlap(a, footprintCorners(square(1 + Math.SQRT2, 0, Math.PI / 4)))).toBe(
      false
    )
  })

  it('只交疊一點點也算重疊', () => {
    const a = footprintCorners(square(0, 0))

    expect(footprintsOverlap(a, footprintCorners(square(1.99, 0)))).toBe(true)
    expect(footprintsOverlap(a, footprintCorners(square(1.99, 1.99)))).toBe(true)
  })

  it('一個完全包住另一個：重疊', () => {
    const big = footprintCorners({ x: 0, z: 0, width: 10, depth: 10, heading: 0 })

    expect(footprintsOverlap(big, footprintCorners(square(1, 1)))).toBe(true)
  })

  it('整個佔地在多邊形裡才算在裡面（凹的多邊形也是）', () => {
    // L 形：缺右上角
    const lShape = [
      { x: 0, z: 0 },
      { x: 10, z: 0 },
      { x: 10, z: 5 },
      { x: 5, z: 5 },
      { x: 5, z: 10 },
      { x: 0, z: 10 },
    ]

    expect(footprintInside(footprintCorners(square(2, 2)), lShape)).toBe(true)
    // 四個角都在 L 形裡，但中間跨過缺角
    const across = footprintCorners({ x: 5, z: 5, width: 8, depth: 8, heading: Math.PI / 4 })
    expect(footprintInside(across, lShape)).toBe(false)
    expect(footprintInside(footprintCorners(square(9.5, 2)), lShape)).toBe(false)
    expect(footprintInside(footprintCorners(square(8, 8)), lShape)).toBe(false)
  })

  it('離一個點的距離：點在佔地裡是 0', () => {
    const corners = footprintCorners(square(0, 0))

    expect(footprintGapToPoint(corners, { x: 0.5, z: 0.5 })).toBe(0)
    expect(footprintGapToPoint(corners, { x: 4, z: 0 })).toBeCloseTo(3, 9)
    expect(footprintGapToPoint(corners, { x: 4, z: 5 })).toBeCloseTo(5, 9)
  })
})

describe('island3d/landmarks 地標清單（#207 驗收條件 6）', () => {
  it('剛好是這 17 個地標', () => {
    expect(
      LANDMARKS.map(({ tribe, sign, placement }) => `${tribe}/${sign}/${placement}`).sort()
    ).toEqual(
      [
        'imowrod/郵局/in-hex',
        'imowrod/衛生所/in-hex',
        'imowrod/警察局/in-hex',
        'imowrod/學校/roadside',
        'iratay/文物館/in-hex',
        'iratay/機場/roadside',
        'yayo/農會/in-hex',
        'yayo/學校/roadside',
        'yayo/便利商店/roadside',
        'yayo/超市/roadside',
        'iraraley/學校/in-hex',
        'iraraley/路邊攤/in-hex',
        'iranmeilek/夜市/in-hex',
        'iranmeilek/便利商店/in-hex',
        'iranmeilek/警察局/in-hex',
        'iranmeilek/學校/in-hex',
        'ivalino/籃球場/in-hex',
      ].sort()
    )
  })

  it('每一項都有位置、朝向、大小、造型種類；模型檔這一步都不填', () => {
    LANDMARKS.forEach((landmark) => {
      ;['x', 'z', 'heading', 'width', 'depth'].forEach((field) =>
        expect(Number.isFinite(landmark[field])).toBe(true)
      )
      expect(landmark.width).toBeGreaterThan(0)
      expect(landmark.depth).toBeGreaterThan(0)
      expect(typeof landmark.kind).toBe('string')
      expect(landmark.model).toBeNull()
    })
  })

  it('招牌不用商標字樣', () => {
    const trademarks = ['7', 'Eleven', 'ELEVEN', '統一', '全家', 'Family', '萊爾富', 'OK']

    LANDMARKS.forEach(({ sign }) => {
      trademarks.forEach((word) => expect(sign).not.toContain(word))
    })
  })

  describe('格子裡的地標', () => {
    const inHex = LANDMARKS.filter((landmark) => landmark.placement === 'in-hex')

    it('整個佔地在所屬部落的格子內', () => {
      inHex.forEach((landmark) => {
        const hex = hexVertices(ISLAND_MAP.hexRadius, center(landmark.tribe))

        expect(footprintInside(footprintCorners(landmark), hex)).toBe(true)
      })
    })

    it('不壓到公路：整個佔地離每段公路的中線超過半寬', () => {
      inHex.forEach((landmark) => {
        expect(roadGap(footprintCorners(landmark))).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      })
    })

    it('靠邊放：佔地上最靠近格子中心的點離中心至少 7（中間留給 3 戶人家）', () => {
      inHex.forEach((landmark) => {
        const gap = footprintGapToPoint(footprintCorners(landmark), center(landmark.tribe))

        expect(gap).toBeGreaterThanOrEqual(7)
      })
    })

    it('紅頭：郵局、衛生所在東南（衛生所比郵局遠離中心）、警察局在西', () => {
      const post = offset(find('imowrod', '郵局'))
      const clinic = offset(find('imowrod', '衛生所'))
      const police = offset(find('imowrod', '警察局'))

      ;[post, clinic].forEach(({ dx, dz }) => {
        expect(dx).toBeGreaterThan(0)
        expect(dz).toBeGreaterThan(0)
      })
      expect(Math.hypot(clinic.dx, clinic.dz)).toBeGreaterThan(Math.hypot(post.dx, post.dz))
      expect(police.dx).toBeLessThan(0)
      expect(Math.abs(police.dz)).toBeLessThan(Math.abs(police.dx))
    })

    it('漁人：文物館在西南', () => {
      const { dx, dz } = offset(find('iratay', '文物館'))

      expect(dx).toBeLessThan(0)
      expect(dz).toBeGreaterThan(0)
    })

    it('椰油：農會在北（往朗島的那一側）', () => {
      const { dx, dz } = offset(find('yayo', '農會'))
      const toIraraley = {
        dx: center('iraraley').x - center('yayo').x,
        dz: center('iraraley').z - center('yayo').z,
      }

      expect(dz).toBeLessThan(0)
      expect(Math.abs(dx)).toBeLessThan(Math.abs(dz))
      expect(dx * toIraraley.dx + dz * toIraraley.dz).toBeGreaterThan(0)
    })

    it('朗島：學校在東北、路邊攤在北（靠海）', () => {
      const school = offset(find('iraraley', '學校'))
      const stall = offset(find('iraraley', '路邊攤'))

      expect(school.dx).toBeGreaterThan(0)
      expect(school.dz).toBeLessThan(0)
      expect(stall.dz).toBeLessThan(0)
      expect(Math.abs(stall.dx)).toBeLessThan(Math.abs(stall.dz))
    })

    it('東清：夜市、便利商店在東，警察局在東南，學校在南', () => {
      ;['夜市', '便利商店'].forEach((sign) => {
        const { dx, dz } = offset(find('iranmeilek', sign))
        expect(dx).toBeGreaterThan(0)
        expect(Math.abs(dz)).toBeLessThan(dx)
      })
      const police = offset(find('iranmeilek', '警察局'))
      expect(police.dx).toBeGreaterThan(0)
      expect(police.dz).toBeGreaterThan(0)
      const school = offset(find('iranmeilek', '學校'))
      expect(school.dz).toBeGreaterThan(0)
      expect(Math.abs(school.dx)).toBeLessThan(school.dz)
    })

    it('野銀：籃球場在靠山的那一側（比格子中心離海岸線更遠）', () => {
      expect(coastGap(find('ivalino', '籃球場'))).toBeGreaterThan(coastGap(center('ivalino')))
    })
  })

  describe('公路旁的地標', () => {
    const roadside = LANDMARKS.filter((landmark) => landmark.placement === 'roadside')
    const ring = ISLAND_MAP.roads[0].points
    const loop = [...ring, ring[0]]
    /** 沿環島公路（照公路的順序，從朗島出發）走到離 point 最近的地方有多遠 */
    const along = (point) => {
      let travelled = 0
      let best = { gap: Infinity, at: 0 }
      loop.slice(1).forEach((to, i) => {
        const onRoad = closestOnSegment(point, loop[i], to)
        const gap = distanceBetween(point, onRoad)
        if (gap < best.gap) {
          best = { gap, at: travelled + distanceBetween(loop[i], onRoad) }
        }
        travelled += distanceBetween(loop[i], to)
      })
      return best.at
    }

    it('整個佔地在可走範圍外、離路邊不超過 6、在所屬部落的格子外', () => {
      roadside.forEach((landmark) => {
        const corners = footprintCorners(landmark)
        const gap = roadGap(corners) - ISLAND_MAP.roadHalfWidth

        expect(gap).toBeGreaterThan(0)
        expect(gap).toBeLessThanOrEqual(6)
        ISLAND_MAP.tribes.forEach((tribe) => {
          expect(footprintsOverlap(corners, hexVertices(ISLAND_MAP.hexRadius, tribe.center))).toBe(
            false
          )
        })
        corners.forEach((corner) => expect(walkable.isWalkable(corner)).toBe(false))
      })
    })

    // 環島公路的順序：朗島 → 東清 → 野銀 →（島的東南端）→ 西端接點 → 紅頭 → 漁人 → 椰油 → 回朗島
    it('紅頭的學校：在紅頭往野銀（島的東南端）的那一段，橫貫公路接點之後', () => {
      const junction = ISLAND_MAP.roads[1].points[0]
      const school = along(find('imowrod', '學校'))

      expect(school).toBeGreaterThan(along(center('ivalino')))
      expect(school).toBeLessThan(along(junction))
    })

    it('機場：在漁人—椰油之間，靠漁人、靠海側', () => {
      const airport = find('iratay', '機場')
      const from = along(center('iratay'))
      const to = along(center('yayo'))
      const nearestOnRoad = loop
        .slice(1)
        .map((end, i) => closestOnSegment(airport, loop[i], end))
        .reduce((a, b) => (distanceBetween(b, airport) < distanceBetween(a, airport) ? b : a))

      expect(along(airport)).toBeGreaterThan(from)
      expect(along(airport)).toBeLessThan((from + to) / 2)
      expect(coastGap(airport)).toBeLessThan(coastGap(nearestOnRoad))
    })

    it('椰油的學校：在椰油—漁人之間，靠椰油', () => {
      const from = along(center('iratay'))
      const to = along(center('yayo'))

      expect(along(find('yayo', '學校'))).toBeGreaterThan((from + to) / 2)
      expect(along(find('yayo', '學校'))).toBeLessThan(to)
    })

    it('椰油的便利商店、超市：在椰油—朗島之間，超市比便利商店靠朗島', () => {
      const store = along(find('yayo', '便利商店'))
      const market = along(find('yayo', '超市'))

      expect(store).toBeGreaterThan(along(center('yayo')))
      expect(market).toBeGreaterThan(store)
    })
  })

  it('地標的佔地彼此不重疊', () => {
    LANDMARKS.forEach((a, i) => {
      LANDMARKS.slice(i + 1).forEach((b) => {
        expect(footprintsOverlap(footprintCorners(a), footprintCorners(b))).toBe(false)
      })
    })
  })

  it('地標不壓到山與水田', () => {
    LANDMARKS.forEach((landmark) => {
      const corners = footprintCorners(landmark)

      ISLAND_DECOR.mountains.forEach((mountain) => {
        expect(footprintGapToPoint(corners, mountain)).toBeGreaterThan(mountain.radius)
      })
      ISLAND_DECOR.paddies.forEach((paddy) => {
        expect(footprintsOverlap(corners, footprintCorners(paddy))).toBe(false)
      })
    })
  })

  it('整個佔地都在海岸線裡面', () => {
    LANDMARKS.forEach((landmark) => {
      expect(footprintInside(footprintCorners(landmark), ISLAND_DECOR.coast)).toBe(true)
    })
  })

  it('山腳離每個地標至少 2', () => {
    LANDMARKS.forEach((landmark) => {
      ISLAND_DECOR.mountains.forEach((mountain) => {
        expect(footprintGapToPoint(footprintCorners(landmark), mountain)).toBeGreaterThanOrEqual(
          mountain.radius + 2
        )
      })
    })
  })
})

describe('island3d/landmarks 程式拼的造型（#207 驗收條件 7）', () => {
  const kinds = [...new Set(LANDMARKS.map((landmark) => landmark.kind))]

  it('同一種招牌用同一種造型；不同招牌用不同造型', () => {
    const kindOf = {}
    LANDMARKS.forEach(({ sign, kind }) => {
      kindOf[sign] ??= kind
      expect(kind).toBe(kindOf[sign])
    })
    expect(new Set(Object.values(kindOf)).size).toBe(Object.keys(kindOf).length)
  })

  it('每一種造型都有零件，零件有形狀、大小、位置、顏色', () => {
    kinds.forEach((kind) => {
      const parts = shapeParts(kind, 4, 3)

      expect(parts.length).toBeGreaterThan(0)
      parts.forEach((part) => {
        expect(['box', 'roof', 'plane', 'post', 'ring']).toContain(part.shape)
        expect(part.color).toMatch(/^#[0-9a-f]{6}$/)
        ;['width', 'height', 'depth', 'x', 'y', 'z'].forEach((field) =>
          expect(Number.isFinite(part[field])).toBe(true)
        )
      })
    })
  })

  it('不同種的造型零件不一樣', () => {
    const signatures = kinds.map((kind) => JSON.stringify(shapeParts(kind, 4, 3)))

    expect(new Set(signatures).size).toBe(kinds.length)
  })

  it('所有零件的地面投影都在佔地內，底部不低於地面', () => {
    const sizes = [
      [4, 3],
      [3, 2.4],
      [10, 4],
    ]
    kinds.forEach((kind) => {
      sizes.forEach(([width, depth]) => {
        shapeParts(kind, width, depth).forEach((part) => {
          expect(Math.abs(part.x) + part.width / 2).toBeLessThanOrEqual(width / 2 + 1e-9)
          expect(Math.abs(part.z) + part.depth / 2).toBeLessThanOrEqual(depth / 2 + 1e-9)
          expect(part.y - part.height / 2).toBeGreaterThanOrEqual(-1e-9)
        })
      })
    })
  })

  it('籃球場：畫線的地面加兩個籃框（左右各一個，各有柱子、籃板、離地的框）', () => {
    const parts = shapeParts('court', 4, 2.6)
    const rims = parts.filter((part) => part.shape === 'ring')
    const posts = parts.filter((part) => part.shape === 'post')
    const ground = parts.filter((part) => part.shape === 'plane')

    expect(ground.length).toBeGreaterThanOrEqual(2)
    expect(rims).toHaveLength(2)
    expect(posts).toHaveLength(2)
    expect(rims.map((rim) => Math.sign(rim.x)).sort()).toEqual([-1, 1])
    rims.forEach((rim) => {
      // 框離地，而且比同一側的柱子靠球場中間（朝場內伸出去）
      const post = posts.find((candidate) => Math.sign(candidate.x) === Math.sign(rim.x))
      expect(rim.y).toBeGreaterThan(1)
      expect(Math.abs(rim.x)).toBeLessThan(Math.abs(post.x))
    })
  })

  it('沒有這種造型：丟出錯誤，不默默畫成別的', () => {
    expect(() => shapeParts('no-such-kind', 4, 3)).toThrow()
  })
})

describe('island3d/landmarks 招牌什麼時候顯示（#207 驗收條件 8）', () => {
  it('zoom 不超過 0.5 顯示，超過就隱藏', () => {
    expect(signsVisible(0)).toBe(true)
    expect(signsVisible(0.5)).toBe(true)
    expect(signsVisible(0.51)).toBe(false)
    expect(signsVisible(1)).toBe(false)
  })
})
