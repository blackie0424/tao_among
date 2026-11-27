import { describe, expect, it } from 'vitest'
import {
  AKAY_POSES,
  FAMILY_LOOKS,
  FAMILY_ROLES,
  PALETTE_SWAPS,
  SKIN_AND_HAIR_CELLS,
  akayPose,
  animationSeconds,
  familyMembers,
  memberPlacement,
} from '@/island3d/family'
import { HOUSEHOLDS, spotKey } from '@/island3d/households'
import { footprintCorners, footprintGapToPoint } from '@/island3d/landmarks'

const MEMBERS = familyMembers()
const TOLERANCE = 1e-6
const distance = (a, b) => Math.hypot(a.x - b.x, a.z - b.z)
const sameCell = (a, b) => a[0] === b[0] && a[1] === b[1]
const walkers = MEMBERS.filter((member) => member.role !== 'akay')
const akays = MEMBERS.filter((member) => member.role === 'akay')
// 看 60 秒，每 0.25 秒看一次
const MOMENTS = Array.from({ length: 241 }, (_, i) => i * 0.25)

describe('island3d/family 每戶四位家人（#201 驗收條件 5）', () => {
  it('每一件一位家人：vahay kaminan、pananadengan akay、zazawan maran、tagakal kaka，一共 72 位', () => {
    expect(FAMILY_ROLES).toEqual({
      vahay: 'kaminan',
      pananadengan: 'akay',
      zazawan: 'maran',
      tagakal: 'kaka',
    })
    expect(MEMBERS).toHaveLength(72)
    MEMBERS.forEach((member) => expect(member.role).toBe(FAMILY_ROLES[member.kind]))
  })

  it('外觀：kaminan 3 戶各用 female-d、female-e、female-f；男性用 male-b、male-e、male-f，3 戶各換一組配色', () => {
    expect(FAMILY_LOOKS.map((look) => look.kaminan)).toEqual([
      { model: 'female-d', palette: 0 },
      { model: 'female-e', palette: 0 },
      { model: 'female-f', palette: 0 },
    ])
    FAMILY_LOOKS.forEach((look, hex) => {
      expect(look.maran).toEqual({ model: 'male-b', palette: hex })
      expect(look.akay).toEqual({ model: 'male-e', palette: hex })
      expect(look.kaka).toEqual({ model: 'male-f', palette: hex })
    })
  })

  it('每位家人記得自己在哪個範圍：和 spotAt 回報的代號一樣（點家人開對話用）', () => {
    MEMBERS.forEach((member) => {
      const household = HOUSEHOLDS.find(
        ({ tribe, hex }) => tribe === member.tribe && hex === member.hex
      )
      const slot = household.items.findIndex((item) => item.kind === member.kind)

      expect(member.spot).toBe(spotKey({ tribe: member.tribe, hex: member.hex, slot }))
    })
  })

  it('每位家人的外觀照自己是第幾格的戶', () => {
    MEMBERS.forEach((member) => expect(member.look).toEqual(FAMILY_LOOKS[member.hex][member.role]))
  })

  it('換配色只換衣服的色塊：不碰皮膚與頭髮（貼圖第 3 列前 4 欄）；第 1、2 組各至少換一塊、彼此不同', () => {
    expect(SKIN_AND_HAIR_CELLS).toEqual([
      [0, 2],
      [1, 2],
      [2, 2],
      [3, 2],
    ])
    expect(PALETTE_SWAPS[0]).toEqual([])
    PALETTE_SWAPS.flat().forEach(([from, to]) => {
      SKIN_AND_HAIR_CELLS.forEach((cell) => {
        expect(sameCell(from, cell)).toBe(false)
        expect(sameCell(to, cell)).toBe(false)
      })
    })
    expect(PALETTE_SWAPS[1].length).toBeGreaterThan(0)
    expect(PALETTE_SWAPS[2].length).toBeGreaterThan(0)
    expect(JSON.stringify(PALETTE_SWAPS[1])).not.toBe(JSON.stringify(PALETTE_SWAPS[2]))
  })
})

describe('island3d/family 家人在哪裡（#201 驗收條件 6–8）', () => {
  it('kaminan、maran、kaka 任何時間都在自己的範圍裡，而且真的有在走', () => {
    walkers.forEach((member) => {
      const spots = MOMENTS.map((seconds) => memberPlacement(member, seconds, { active: true }))
      spots.forEach(({ position }) =>
        expect(distance(position, member.zone)).toBeLessThanOrEqual(member.zone.radius + TOLERANCE)
      )
      expect(
        Math.max(...spots.map(({ position }) => distance(position, spots[0].position)))
      ).toBeGreaterThan(1)
    })
  })

  it('akay 坐在中間立石正前方、離石頭前緣 0.3，在 pananadengan 的佔地裡，面向和這一戶相同，不走動', () => {
    akays.forEach((member) => {
      const household = HOUSEHOLDS.find(
        ({ tribe, hex }) => tribe === member.tribe && hex === member.hex
      )
      const stones = household.items.find((item) => item.kind === 'pananadengan')
      const forward = { x: Math.sin(household.heading), z: Math.cos(household.heading) }
      const placements = MOMENTS.map((seconds) =>
        memberPlacement(member, seconds, { active: true })
      )
      const [{ position, heading }] = placements
      const ahead = (position.x - stones.x) * forward.x + (position.z - stones.z) * forward.z
      const aside = (position.x - stones.x) * forward.z - (position.z - stones.z) * forward.x

      // 中間那塊立石：中心在佔地中線往後 0.75、厚 0.6，前緣在 -0.45
      expect(ahead).toBeCloseTo(-0.45 + 0.3, 6)
      expect(aside).toBeCloseTo(0, 6)
      expect(footprintGapToPoint(footprintCorners(stones), position)).toBe(0)
      expect(heading).toBeCloseTo(household.heading, 9)
      placements.forEach((placement) => expect(placement.position).toEqual(position))
    })
  })

  it('akay 的坐姿：正面坐 → 轉頭看左 → 正面坐 → 轉頭看右，每 8 秒換一次，3 戶錯開', () => {
    expect(AKAY_POSES).toEqual(['sit', 'wheelchair-look-left', 'sit', 'wheelchair-look-right'])
    expect([0, 8, 16, 24, 32].map((seconds) => akayPose(seconds, 0, { active: true }))).toEqual([
      'sit',
      'wheelchair-look-left',
      'sit',
      'wheelchair-look-right',
      'sit',
    ])
    const changes = (hex) =>
      MOMENTS.filter(
        (seconds) =>
          akayPose(seconds, hex, { active: true }) !==
          akayPose(seconds + 0.25, hex, { active: true })
      )
    const [first, second, third] = [0, 1, 2].map(changes)
    expect(first).not.toEqual(second)
    expect(second).not.toEqual(third)
    expect(first).not.toEqual(third)
  })

  it('減少動態效果：走動的家人站在範圍中心，akay 固定正面坐', () => {
    walkers.forEach((member) =>
      MOMENTS.slice(0, 40).forEach((seconds) =>
        expect(
          memberPlacement(member, seconds, { active: true, reducedMotion: true }).position
        ).toEqual({
          x: member.zone.x,
          z: member.zone.z,
        })
      )
    )
    MOMENTS.forEach((seconds) =>
      expect(akayPose(seconds, 1, { active: true, reducedMotion: true })).toBe('sit')
    )
  })

  it('不在所在部落：走動的家人停在範圍中心，akay 正面坐', () => {
    walkers.forEach((member) =>
      expect(memberPlacement(member, 13.5, { active: false }).position).toEqual({
        x: member.zone.x,
        z: member.zone.z,
      })
    )
    MOMENTS.forEach((seconds) => expect(akayPose(seconds, 2, { active: false })).toBe('sit'))
  })
})

describe('island3d/family 動畫什麼時候播（#201 驗收條件 7、8）', () => {
  const fade = { fadeSeconds: 0.2 }

  it('所在部落、沒有減少動態效果：照實際經過的秒數播', () => {
    expect(animationSeconds(0.016, { active: true, changed: false, ...fade })).toBe(0.016)
  })

  it('減少動態效果：換動作那一張跑完淡入淡出，之後不再往前推（站著或坐著不動）', () => {
    expect(
      animationSeconds(0.016, { active: true, reducedMotion: true, changed: true, ...fade })
    ).toBe(0.2)
    expect(
      animationSeconds(0.016, { active: true, reducedMotion: true, changed: false, ...fade })
    ).toBe(0)
  })

  it('換到別的部落：原部落的家人換動作那一張跑完淡入淡出，之後停住', () => {
    expect(animationSeconds(0.016, { active: false, changed: true, ...fade })).toBe(0.2)
    expect(animationSeconds(0.016, { active: false, changed: false, ...fade })).toBe(0)
  })
})
