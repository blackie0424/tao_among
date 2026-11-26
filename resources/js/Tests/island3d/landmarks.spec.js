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
  signHeight,
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

describe('island3d/landmarks 6 個特色建築（#212 驗收條件 9、10）', () => {
  // 手寫的清單：[部落, 招牌, 位置 x, 位置 z, 佔地寬, 佔地深]
  const expected = [
    ['imowrod', '郵局', 31.9, 72.5, 8, 6],
    ['yayo', '農會', -131.5, -41.9, 8, 6],
    ['iranmeilek', '便利商店', 32.9, -39.9, 8, 6],
    ['iraraley', '路邊攤', -72.6, -133.3, 6, 3],
    ['ivalino', '籃球場', 39.7, 18.0, 12, 7],
    ['iratay', '機場', -98.0, 26.0, 20, 8],
  ]
  const allHexes = ISLAND_MAP.tribes.flatMap((tribe) =>
    tribe.hexes.map((at) => hexVertices(ISLAND_MAP.hexRadius, at))
  )
  /** 所有公路上離 point 最近的那一點 */
  const nearestOnRoad = (point) =>
    roadSegments
      .map(([a, b]) => closestOnSegment(point, a, b))
      .reduce((best, p) => (distanceBetween(p, point) < distanceBetween(best, point) ? p : best))
  /** 造型最高的地方離地多高 */
  const topOf = ({ kind, width, depth }) =>
    Math.max(...shapeParts(kind, width, depth).map((part) => part.y + part.height / 2))

  it('剛好是這 6 個：每個部落 1 個', () => {
    expect(LANDMARKS.map(({ tribe, sign }) => `${tribe}/${sign}`).sort()).toEqual(
      expected.map(([tribe, sign]) => `${tribe}/${sign}`).sort()
    )
    expect(new Set(LANDMARKS.map((landmark) => landmark.tribe)).size).toBe(6)
  })

  it('每一項都有位置、朝向、大小、造型種類；模型檔這一步都不填', () => {
    LANDMARKS.forEach((landmark) => {
      ;['x', 'z', 'heading', 'width', 'depth'].forEach((field) =>
        expect(Number.isFinite(landmark[field])).toBe(true)
      )
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

  it('靠招牌辨識：6 個的招牌文字各不相同，造型種類也各不相同', () => {
    expect(new Set(LANDMARKS.map((landmark) => landmark.sign)).size).toBe(6)
    expect(new Set(LANDMARKS.map((landmark) => landmark.kind)).size).toBe(6)
  })

  it('佔地大小照手寫的表', () => {
    expected.forEach(([tribe, sign, , , width, depth]) => {
      expect(find(tribe, sign).width).toBe(width)
      expect(find(tribe, sign).depth).toBe(depth)
    })
  })

  it('平房類（郵局、農會、便利商店）牆加屋頂共 3.4 高：人物（1.7）的 2 倍', () => {
    ;[
      ['imowrod', '郵局'],
      ['yayo', '農會'],
      ['iranmeilek', '便利商店'],
    ].forEach(([tribe, sign]) => expect(topOf(find(tribe, sign))).toBeCloseTo(3.4, 9))
  })

  it('路邊攤的棚頂 2.2 高、機場的塔台 4.8 高', () => {
    expect(topOf(find('iraraley', '路邊攤'))).toBeCloseTo(2.2, 9)
    expect(topOf(find('iratay', '機場'))).toBeCloseTo(4.8, 9)
  })

  it('籃球場的籃框離地 3.0', () => {
    const court = find('ivalino', '籃球場')
    const rims = shapeParts(court.kind, court.width, court.depth).filter(
      (part) => part.shape === 'ring'
    )

    expect(rims).toHaveLength(2)
    rims.forEach((rim) => expect(rim.y).toBeCloseTo(3.0, 9))
  })

  it('位置照 chung 標的位置：離手寫的表不超過 2', () => {
    expected.forEach(([tribe, sign, x, z]) => {
      expect(distanceBetween(find(tribe, sign), { x, z })).toBeLessThanOrEqual(2)
    })
  })

  it('整個佔地在可走範圍外：不壓公路、不壓 18 格的任何一格', () => {
    LANDMARKS.forEach((landmark) => {
      const corners = footprintCorners(landmark)

      expect(roadGap(corners)).toBeGreaterThan(ISLAND_MAP.roadHalfWidth)
      corners.forEach((corner) => expect(walkable.isWalkable(corner)).toBe(false))
      allHexes.forEach((hex) => expect(footprintsOverlap(corners, hex)).toBe(false))
    })
  })

  it('在公路邊：離路邊不超過 6', () => {
    LANDMARKS.forEach((landmark) => {
      const gap = roadGap(footprintCorners(landmark)) - ISLAND_MAP.roadHalfWidth

      expect(gap).toBeLessThanOrEqual(6)
    })
  })

  it('正面朝公路：正面的方向（朝向 0 是 +z）指向公路上最近的那一點，偏差不超過 15 度', () => {
    LANDMARKS.forEach((landmark) => {
      const toRoad = nearestOnRoad(landmark)
      const along = distanceBetween(toRoad, landmark)
      const facing =
        (Math.sin(landmark.heading) * (toRoad.x - landmark.x) +
          Math.cos(landmark.heading) * (toRoad.z - landmark.z)) /
        along

      expect(facing).toBeGreaterThan(Math.cos((15 * Math.PI) / 180))
    })
  })

  it('離所屬部落最近一格的中心不超過 34', () => {
    LANDMARKS.forEach((landmark) => {
      const hexes = ISLAND_MAP.tribes.find((tribe) => tribe.code === landmark.tribe).hexes
      const nearest = Math.min(...hexes.map((at) => distanceBetween(at, landmark)))

      expect(nearest).toBeLessThanOrEqual(34)
    })
  })

  it('機場在椰油往漁人的環島公路旁、靠海那一側（比公路上最近的點更靠近海岸線）', () => {
    const airport = find('iratay', '機場')

    expect(airport.x).toBeGreaterThan(center('yayo').x)
    expect(airport.x).toBeLessThan(center('iratay').x)
    expect(coastGap(airport)).toBeLessThan(coastGap(nearestOnRoad(airport)))
  })

  it('郵局在紅頭主格與第 2 格之間：離兩格的中心都比兩格之間的距離近', () => {
    const [main, second] = ISLAND_MAP.tribes.find((tribe) => tribe.code === 'imowrod').hexes
    const post = find('imowrod', '郵局')

    expect(distanceBetween(post, main)).toBeLessThan(distanceBetween(main, second))
    expect(distanceBetween(post, second)).toBeLessThan(distanceBetween(main, second))
  })

  it('彼此不重疊', () => {
    LANDMARKS.forEach((a, i) => {
      LANDMARKS.slice(i + 1).forEach((b) => {
        expect(footprintsOverlap(footprintCorners(a), footprintCorners(b))).toBe(false)
      })
    })
  })

  it('不壓到水芋田', () => {
    expect(ISLAND_DECOR.paddies.length).toBeGreaterThan(0)
    LANDMARKS.forEach((landmark) => {
      ISLAND_DECOR.paddies.forEach((paddy) => {
        expect(footprintsOverlap(footprintCorners(landmark), footprintCorners(paddy))).toBe(false)
      })
    })
  })

  it('整個佔地在沙灘以內的陸地上', () => {
    LANDMARKS.forEach((landmark) => {
      expect(footprintInside(footprintCorners(landmark), ISLAND_DECOR.inland)).toBe(true)
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
    // 6 個特色建築實際用到的 4 種佔地
    const sizes = [
      [8, 6],
      [6, 3],
      [12, 7],
      [20, 8],
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
    const parts = shapeParts('court', 12, 7)
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

  it('#212 拿掉的地標，造型也一起拿掉', () => {
    ;['clinic', 'police', 'school', 'museum', 'supermarket', 'night-market'].forEach((kind) => {
      expect(() => shapeParts(kind, 4, 3)).toThrow()
    })
  })
})

describe('island3d/landmarks 招牌的高度（#212 驗收條件 9）', () => {
  it('招牌在建物最高點的上方 1.2：平房 4.6、路邊攤 3.4、機場 6.0', () => {
    const heightOf = (sign) => signHeight(LANDMARKS.find((landmark) => landmark.sign === sign))

    expect(heightOf('郵局')).toBeCloseTo(4.6, 9)
    expect(heightOf('路邊攤')).toBeCloseTo(3.4, 9)
    expect(heightOf('機場')).toBeCloseTo(6.0, 9)
  })

  it('每一個地標的招牌都比造型的每個零件高', () => {
    LANDMARKS.forEach((landmark) => {
      const tops = shapeParts(landmark.kind, landmark.width, landmark.depth).map(
        (part) => part.y + part.height / 2
      )

      expect(signHeight(landmark)).toBeGreaterThan(Math.max(...tops))
    })
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
