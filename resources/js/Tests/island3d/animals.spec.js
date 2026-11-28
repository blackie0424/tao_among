import { describe, expect, it } from 'vitest'
import { ANIMAL_KINDS, animalPlacement, islandAnimals, isNameVisible } from '@/island3d/animals'
import { ACTOR_HEIGHT } from '@/island3d/figures'
import { closestOnSegment, hexVertices } from '@/island3d/hexBounds'
import { householdObstacles } from '@/island3d/households'
import { ISLAND_DECOR, ISLAND_MAP, roadSegments, tribeHexes } from '@/island3d/islandMap'
import { LANDMARKS, footprintCorners } from '@/island3d/landmarks'
import { spanInPolygon } from '@/island3d/walkable'

const ANIMALS = islandAnimals()
const TOLERANCE = 1e-6
// 只擦過邊、長度這麼短的重疊不算
const TOUCH = 1e-9
const TRIBES = ['iraraley', 'iranmeilek', 'ivalino', 'imowrod', 'iratay', 'yayo']
const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const ofKind = (list, kind) => list.filter((animal) => animal.kind === kind)
const ofPlace = (place) => ANIMALS.filter((animal) => animal.place === place)
const groupsOf = (list) => Object.values(Object.groupBy(list, (animal) => animal.group))
// 看 60 秒，每 0.25 秒看一次
const MOMENTS = Array.from({ length: 241 }, (_, i) => i * 0.25)

// ── 判定用的幾何（照規格：整段直線，不取樣） ──
const hexesOf = (code) =>
  tribeHexes(ISLAND_MAP.tribes.find((tribe) => tribe.code === code)).map((center) =>
    hexVertices(ISLAND_MAP.hexRadius, center)
  )
const ALL_HEXES = TRIBES.flatMap(hexesOf)
const OBSTACLES = [
  ...householdObstacles(),
  ...LANDMARKS.map(footprintCorners),
  ...ISLAND_DECOR.paddies.map(footprintCorners),
]
const insideConvex = (point, polygon) => spanInPolygon(point, point, polygon) !== null
const overlaps = ({ from, to }, polygon) => {
  const span = spanInPolygon(from, to, polygon, -TOUCH)
  return span !== null && span[1] - span[0] > TOUCH
}
/** 點在（不一定凸的）多邊形裡：射線法 */
function insidePolygon(point, polygon) {
  let inside = false
  polygon.forEach((a, i) => {
    const b = polygon[(i + 1) % polygon.length]
    if (a.z > point.z !== b.z > point.z) {
      const x = a.x + ((point.z - a.z) / (b.z - a.z)) * (b.x - a.x)
      if (point.x < x) {
        inside = !inside
      }
    }
  })
  return inside
}
const cross = (o, a, b) => (a.x - o.x) * (b.z - o.z) - (a.z - o.z) * (b.x - o.x)
const segmentsCross = (p, q, a, b) =>
  cross(p, q, a) * cross(p, q, b) < 0 && cross(a, b, p) * cross(a, b, q) < 0
const crossesCoast = ({ from, to }) =>
  ISLAND_DECOR.coast.some((a, i) =>
    segmentsCross(from, to, a, ISLAND_DECOR.coast[(i + 1) % ISLAND_DECOR.coast.length])
  )
const gapToRoad = (point, { from, to }) => distance(point, closestOnSegment(point, from, to))
const RING_SEGMENTS = roadSegments({ ...ISLAND_MAP, roads: [ISLAND_MAP.roads[0]] })
const CROSS_SEGMENTS = roadSegments({ ...ISLAND_MAP, roads: [ISLAND_MAP.roads[1]] })
const withinOneSegment = ({ from, to }, segments, reach) =>
  segments.some((segment) => gapToRoad(from, segment) <= reach && gapToRoad(to, segment) <= reach)

describe('island3d/animals 出現地點與數量（#222 驗收條件 1）', () => {
  it('狗：6 個部落各 2 隻；貓：6 個部落各 1 隻', () => {
    TRIBES.forEach((code) => {
      expect(ofKind(ofPlace(code), 'ino')).toHaveLength(2)
      expect(ofKind(ofPlace(code), 'cito')).toHaveLength(1)
    })
    expect(ofKind(ANIMALS, 'ino')).toHaveLength(12)
    expect(ofKind(ANIMALS, 'cito')).toHaveLength(6)
  })

  it('豬：紅頭、野銀各 1～2 隻大豬、各自單獨；其他部落沒有豬', () => {
    ;['imowrod', 'ivalino'].forEach((code) => {
      const pigs = ofPlace(code).filter((animal) => animal.name === 'kois')
      expect(pigs.length).toBeGreaterThanOrEqual(1)
      expect(pigs.length).toBeLessThanOrEqual(2)
      pigs.forEach((pig) => expect(pig.kind).toBe('kois'))
      groupsOf(pigs).forEach((group) => expect(group).toHaveLength(1))
    })
    ;['iraraley', 'iranmeilek', 'iratay', 'yayo'].forEach((code) =>
      expect(ofPlace(code).filter((animal) => animal.name === 'kois')).toEqual([])
    )
  })

  it('豬：橫貫公路上 1 群，大豬 1 隻＋小豬 2～4 隻', () => {
    const groups = groupsOf(ofPlace('cross-road'))
    expect(groups).toHaveLength(1)
    const [group] = groups
    expect(ofKind(group, 'kois')).toHaveLength(1)
    expect(ofKind(group, 'piglet').length).toBeGreaterThanOrEqual(2)
    expect(ofKind(group, 'piglet').length).toBeLessThanOrEqual(4)
    expect(group).toHaveLength(ofKind(group, 'kois').length + ofKind(group, 'piglet').length)
  })

  it('山羊：5 群、每群 3～5 隻，都在環島公路旁', () => {
    const goats = ofKind(ANIMALS, 'kagling')
    goats.forEach((goat) => expect(goat.place).toBe('ring-road'))
    const groups = groupsOf(goats)
    expect(groups).toHaveLength(5)
    groups.forEach((group) => {
      expect(group.length).toBeGreaterThanOrEqual(3)
      expect(group.length).toBeLessThanOrEqual(5)
    })
    expect(ofPlace('ring-road')).toEqual(goats)
  })

  it('只有這四種（大豬與小豬都是豬）', () => {
    ANIMALS.forEach((animal) =>
      expect(['ino', 'cito', 'kois', 'piglet', 'kagling']).toContain(animal.kind)
    )
  })
})

describe('island3d/animals 不穿過東西（#222 驗收條件 2）', () => {
  it('每隻動物的整段路徑不和家屋四件、特色建築、水田的佔地相交', () => {
    ANIMALS.forEach((animal) =>
      OBSTACLES.forEach((polygon) => expect(overlaps(animal.route, polygon)).toBe(false))
    )
  })

  it('每隻動物路徑兩端都在海岸線以內，整段不和海岸線相交', () => {
    ANIMALS.forEach(({ route }) => {
      expect(insidePolygon(route.from, ISLAND_DECOR.coast)).toBe(true)
      expect(insidePolygon(route.to, ISLAND_DECOR.coast)).toBe(true)
      expect(crossesCoast(route)).toBe(false)
    })
  })

  it('狗、貓、部落裡的豬：路徑兩端在自己部落的同一個格子裡', () => {
    ANIMALS.filter((animal) => TRIBES.includes(animal.place)).forEach(({ place, route }) => {
      const home = hexesOf(place).filter(
        (hex) => insideConvex(route.from, hex) && insideConvex(route.to, hex)
      )
      expect(home.length).toBeGreaterThan(0)
    })
  })

  it('橫貫公路的豬群：路徑兩端在橫貫公路同一小段的路面上，不進任何部落的格子', () => {
    ofPlace('cross-road').forEach(({ route }) => {
      expect(withinOneSegment(route, CROSS_SEGMENTS, ISLAND_MAP.roadHalfWidth)).toBe(true)
      ALL_HEXES.forEach((hex) => expect(overlaps(route, hex)).toBe(false))
    })
  })

  it('山羊：路徑兩端在同一小段環島公路的「路面加路邊外 5 公尺」範圍裡，不進任何部落的格子', () => {
    ofPlace('ring-road').forEach(({ route }) => {
      expect(withinOneSegment(route, RING_SEGMENTS, ISLAND_MAP.roadHalfWidth + 5)).toBe(true)
      ALL_HEXES.forEach((hex) => expect(overlaps(route, hex)).toBe(false))
    })
  })
})

describe('island3d/animals 比人小、外型（#222 驗收條件 3）', () => {
  it('高度：山羊 0.85、狗 0.7、大豬 0.5、小豬 0.25、貓 0.4，都不超過角色身高的一半', () => {
    expect(ACTOR_HEIGHT).toBe(1.7)
    expect(ANIMAL_KINDS.kagling.height).toBe(0.85)
    expect(ANIMAL_KINDS.ino.height).toBe(0.7)
    expect(ANIMAL_KINDS.kois.height).toBe(0.5)
    expect(ANIMAL_KINDS.piglet.height).toBe(0.25)
    expect(ANIMAL_KINDS.cito.height).toBe(0.4)
    Object.values(ANIMAL_KINDS).forEach((kind) => expect(kind.height).toBeLessThanOrEqual(0.85))
  })

  it('模型：狗 ino、貓 cito、豬與小豬 kois、山羊 kagling', () => {
    expect(ANIMAL_KINDS.ino.model).toBe('ino')
    expect(ANIMAL_KINDS.cito.model).toBe('cito')
    expect(ANIMAL_KINDS.kois.model).toBe('kois')
    expect(ANIMAL_KINDS.piglet.model).toBe('kois')
    expect(ANIMAL_KINDS.kagling.model).toBe('kagling')
  })

  it('毛色：豬全黑；山羊黑、黑褐；狗土黃、黑；貓橘、黑白——同一種兩種毛色交替分配', () => {
    const coatsOf = (kind) => ofKind(ANIMALS, kind).map((animal) => animal.coat)
    const PIG = { body: '#2b2724', detail: '#1c1917' }
    coatsOf('kois').forEach((coat) => expect(coat).toEqual(PIG))
    coatsOf('piglet').forEach((coat) => expect(coat).toEqual(PIG))
    const alternates = (coats, [first, second]) =>
      coats.forEach((coat, i) => expect(coat).toEqual(i % 2 === 0 ? first : second))
    alternates(coatsOf('kagling'), [{ body: '#2a2420' }, { body: '#5a3e2b' }])
    alternates(coatsOf('ino'), [{ body: '#e6bd7a' }, { body: '#2a2624' }])
    alternates(coatsOf('cito'), [
      { body: '#f0a24e', belly: '#fbf3e6' },
      { body: '#262322', belly: '#f4f1ec' },
    ])
  })
})

describe('island3d/animals 走動（#222 驗收條件 4）', () => {
  const flat = { x0: -500, z0: -500, step: 1000, cols: 2, rows: 2, heights: new Float32Array(4) }

  it('在自己的路線上來回走：走的時候 walk、面向前進方向；停下時 idle', () => {
    ANIMALS.forEach((animal) => {
      const poses = new Set()
      MOMENTS.forEach((t) => {
        const now = animalPlacement(animal, t, { field: flat })
        const next = animalPlacement(animal, t + 0.05, { field: flat })
        poses.add(now.pose)
        const { from, to } = animal.route
        const along = closestOnSegment(now.position, from, to)
        expect(distance(now.position, along)).toBeLessThan(TOLERANCE)
        if (now.pose === 'walk' && next.pose === 'walk') {
          const heading = Math.atan2(
            next.position.x - now.position.x,
            next.position.z - now.position.z
          )
          expect(Math.cos(now.heading - heading)).toBeGreaterThan(0.99)
        }
        if (now.pose === 'idle' && next.pose === 'idle') {
          expect(distance(now.position, next.position)).toBeLessThan(TOLERANCE)
        }
      })
      expect(poses).toEqual(new Set(['walk', 'idle']))
    })
  })

  it('同一群一起走、彼此錯開：每一刻群裡任兩隻都不在同一點，且都在走或都停著', () => {
    groupsOf(ANIMALS)
      .filter((group) => group.length > 1)
      .forEach((group) =>
        MOMENTS.forEach((t) => {
          const placements = group.map((animal) => animalPlacement(animal, t, { field: flat }))
          expect(new Set(placements.map((p) => p.pose)).size).toBe(1)
          placements.forEach((a, i) =>
            placements
              .slice(i + 1)
              .forEach((b) => expect(distance(a.position, b.position)).toBeGreaterThan(0.3))
          )
        })
      )
  })

  it('腳貼在地面上：高度等於那一點的地形高度', () => {
    // 往 +x 每 1 單位升高 0.5 的斜坡
    const slope = {
      x0: -200,
      z0: -200,
      step: 400,
      cols: 2,
      rows: 2,
      heights: new Float32Array([0, 200, 0, 200]),
    }
    const [animal] = ANIMALS
    ;[0, 3.3, 7.9].forEach((t) => {
      const { position } = animalPlacement(animal, t, { field: slope })
      expect(position.y).toBeCloseTo((position.x + 200) * 0.5, 6)
    })
  })

  it('減少動態效果：站在路線起點、idle、一直不動', () => {
    ANIMALS.forEach((animal) =>
      [0, 5.5, 31].forEach((t) => {
        const { position, pose } = animalPlacement(animal, t, { field: flat, reducedMotion: true })
        expect(pose).toBe('idle')
        expect(distance(position, animal.route.from)).toBeLessThan(TOLERANCE)
      })
    )
  })
})

describe('island3d/animals 族語名稱（#222 驗收條件 5）', () => {
  it('名稱：豬 kois（小豬也是）、狗 ino、貓 cito、山羊 kagling', () => {
    expect(ANIMAL_KINDS.kois.name).toBe('kois')
    expect(ANIMAL_KINDS.piglet.name).toBe('kois')
    expect(ANIMAL_KINDS.ino.name).toBe('ino')
    expect(ANIMAL_KINDS.cito.name).toBe('cito')
    expect(ANIMAL_KINDS.kagling.name).toBe('kagling')
    ANIMALS.forEach((animal) => expect(animal.name).toBe(ANIMAL_KINDS[animal.kind].name))
  })

  it('玩家離動物 4 以內才顯示（剛好 4 也顯示），超過就不顯示；只看水平距離', () => {
    const animal = { x: 10, y: 3, z: 20 }
    expect(isNameVisible(animal, { x: 10, z: 20 })).toBe(true)
    expect(isNameVisible(animal, { x: 12.4, z: 23.2 })).toBe(true)
    expect(isNameVisible(animal, { x: 12.4, z: 23.21 })).toBe(false)
    expect(isNameVisible(animal, { x: 10, z: 30 })).toBe(false)
  })
})
