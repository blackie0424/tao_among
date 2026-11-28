import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createAnimals } from '@/island3d/animalMeshes'
import { cameraRange, createCameraRig, labelScale, zoomByWheel } from '@/island3d/cameraRig'
import { HOUSEHOLDS } from '@/island3d/households'
import { buildHouseholdSigns, buildLandmarks, showSigns } from '@/island3d/landmarkMeshes'
import { nameLabelsOf, scaleNameLabels } from '@/island3d/nameLabels'

// 原本的高度（畫面高度的比例）：地標與家屋招牌 0.03、動物名稱 0.024
const SIGN_HEIGHT = 0.03
const ANIMAL_HEIGHT = 0.024
const flat = { x0: -500, z0: -500, step: 1000, cols: 2, rows: 2, heights: new Float32Array(4) }
const idleLoader = { load() {} }
// 每個部落 12 個地點的名稱
const spots = Object.fromEntries(
  HOUSEHOLDS.map(({ tribe }) => [
    tribe,
    Array.from({ length: 12 }, (_, i) => ({ name: `地點${i}` })),
  ])
)

/** jsdom 沒有 canvas 的 2D 繪圖：給一個什麼都不畫的假的，招牌貼圖照樣做得出來 */
const quietContext = new Proxy({}, { get: () => () => {}, set: () => true })

describe('island3d/nameLabels 三種名字標示跟著鏡頭距離放大（#228 驗收條件 4）', () => {
  beforeEach(() => {
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(quietContext)
  })
  afterEach(() => vi.restoreAllMocks())

  /** 實際的地標招牌、家屋招牌、動物名稱 */
  function buildAll() {
    const landmarks = buildLandmarks(idleLoader)
    const householdSigns = buildHouseholdSigns(spots)
    const animals = createAnimals(idleLoader, { field: flat })
    const labels = nameLabelsOf({ landmarks, householdSigns, animals })
    return { landmarks, householdSigns, animals, labels }
  }
  const range = cameraRange({ aspect: 16 / 9, viewRadius: 100, pitch: Math.atan2(12, 13) })
  const entered = createCameraRig({ aspect: 16 / 9, viewRadius: 100 })
  const closest = zoomByWheel(entered, -5000)

  it('收集到三種標示：每個地標、每一件家屋、每一隻動物各一個', () => {
    const { landmarks, householdSigns, animals, labels } = buildAll()

    expect(labels).toHaveLength(
      landmarks.signs.children.length + householdSigns.children.length + animals.figures.length
    )
    expect(landmarks.signs.children.length).toBeGreaterThan(0)
    expect(householdSigns.children.length).toBe(HOUSEHOLDS.length * 4)
    expect(animals.figures.length).toBeGreaterThan(0)
  })

  it('進入時的距離：三種標示的大小同現在（招牌高 0.03、動物名稱高 0.024）', () => {
    const { landmarks, householdSigns, animals, labels } = buildAll()

    scaleNameLabels(labels, labelScale(entered, range))

    expect(landmarks.signs.children[0].scale.y).toBeCloseTo(SIGN_HEIGHT, 5)
    expect(householdSigns.children[0].scale.y).toBeCloseTo(SIGN_HEIGHT, 5)
    expect(animals.figures[0].label.scale.y).toBeCloseTo(ANIMAL_HEIGHT, 5)
  })

  it('最近的距離：三種標示都是現在的 2.2 倍（招牌 0.066、動物名稱 0.0528），寬度一起放大', () => {
    const { landmarks, householdSigns, animals, labels } = buildAll()
    const width = landmarks.signs.children[0].scale.x

    scaleNameLabels(labels, labelScale(closest, range))

    expect(landmarks.signs.children[0].scale.y).toBeCloseTo(0.066, 5)
    expect(landmarks.signs.children[0].scale.x).toBeCloseTo(width * 2.2, 5)
    expect(householdSigns.children[0].scale.y).toBeCloseTo(0.066, 5)
    expect(animals.figures[0].label.scale.y).toBeCloseTo(0.0528, 5)
  })

  it('拉近再拉回進入時的距離：回到原本的大小，不會越放越大', () => {
    const { landmarks, animals, labels } = buildAll()

    scaleNameLabels(labels, labelScale(closest, range))
    scaleNameLabels(labels, labelScale(closest, range))
    scaleNameLabels(labels, labelScale(entered, range))

    expect(landmarks.signs.children[0].scale.y).toBeCloseTo(SIGN_HEIGHT, 5)
    expect(animals.figures[0].label.scale.y).toBeCloseTo(ANIMAL_HEIGHT, 5)
  })

  it('隱藏中的標示放大後仍然隱藏：離角色太遠的動物名稱、拉遠時隱藏的招牌', () => {
    const { landmarks, householdSigns, animals, labels } = buildAll()
    showSigns(landmarks.signs, 0.9)
    showSigns(householdSigns, 0.9)

    scaleNameLabels(labels, labelScale(closest, range))

    expect(animals.figures.every((figure) => figure.label.visible === false)).toBe(true)
    expect(landmarks.signs.visible).toBe(false)
    expect(householdSigns.visible).toBe(false)
  })
})
