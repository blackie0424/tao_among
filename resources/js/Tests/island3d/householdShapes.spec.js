import { describe, expect, it } from 'vitest'
import { HOUSEHOLD_KINDS, HOUSEHOLD_KIND_ORDER } from '@/island3d/households'
import { shapeParts } from '@/island3d/landmarks'

const partsOf = (kind) => {
  const { width, depth } = HOUSEHOLD_KINDS[kind]
  return shapeParts(kind, width, depth)
}
const colorsOf = (kind) => new Set(partsOf(kind).map((part) => part.color))
const topOf = (parts) => Math.max(...parts.map((part) => part.y + part.height / 2))

describe('island3d/landmarks 家屋四件的造型（#201 驗收條件 1）', () => {
  it('四件的零件都在自己的佔地裡，底部不低於地面', () => {
    HOUSEHOLD_KIND_ORDER.forEach((kind) => {
      const { width, depth } = HOUSEHOLD_KINDS[kind]
      const parts = partsOf(kind)

      expect(parts.length).toBeGreaterThan(0)
      parts.forEach((part) => {
        expect(Math.abs(part.x) + part.width / 2).toBeLessThanOrEqual(width / 2 + 1e-9)
        expect(Math.abs(part.z) + part.depth / 2).toBeLessThanOrEqual(depth / 2 + 1e-9)
        expect(part.y - part.height / 2).toBeGreaterThanOrEqual(-1e-9)
      })
    })
  })

  it('vahay：木屋、茅草兩面坡屋頂、石牆、石階與石板', () => {
    const parts = partsOf('vahay')

    expect(colorsOf('vahay')).toEqual(
      new Set(['#8a5a34', '#9c8a62', '#7f838a', '#a4a7ab', '#5a3a22', '#2e1d10'])
    )
    expect(parts.some((part) => part.shape === 'gable' && part.color === '#9c8a62')).toBe(true)
  })

  it('pananadengan：3 塊頂端圓的立石，最高 1.3，四周圓扁的石板', () => {
    const parts = partsOf('pananadengan')
    const roundTops = parts.filter(
      (part) =>
        part.shape === 'cylinder' &&
        part.axis === 'z' &&
        ['#5f6266', '#55585c'].includes(part.color)
    )
    const flatStones = parts.filter((part) => part.shape === 'cylinder' && part.color === '#7c7f83')

    expect(roundTops).toHaveLength(3)
    expect(new Set(roundTops.map((part) => part.width)).size).toBe(3)
    expect(topOf(roundTops)).toBeCloseTo(1.3, 5)
    expect(flatStones.length).toBeGreaterThanOrEqual(6)
  })

  it('zazawan：四角竹柱、7 根平行竹竿高 2.2，竿下掛魚乾', () => {
    const parts = partsOf('zazawan')
    const bamboo = parts.filter((part) => part.color === '#c7a564')
    const poles = bamboo.filter((part) => part.axis === 'x')
    const legs = bamboo.filter((part) => part.axis === 'y')
    const fish = parts.filter((part) => ['#9a6a3e', '#b07d4c'].includes(part.color))

    expect(legs).toHaveLength(4)
    expect(poles).toHaveLength(7)
    poles.forEach((pole) => expect(pole.y - pole.height / 2).toBeCloseTo(2.2, 5))
    expect(fish.length).toBeGreaterThan(poles.length)
    fish.forEach((one) => expect(one.y + one.height / 2).toBeLessThanOrEqual(2.2 + 1e-9))
  })

  it('tagakal：高腳木台（台面高 1.3）、茅草頂、梯子', () => {
    const parts = partsOf('tagakal')
    const deck = parts.find((part) => part.shape === 'box' && part.color === '#8a5a34')

    expect(deck.y - deck.height / 2).toBeCloseTo(1.3, 5)
    expect(parts.some((part) => part.shape === 'gable' && part.color === '#9c8a62')).toBe(true)
    expect(topOf(parts)).toBeGreaterThan(2.5)
  })
})
