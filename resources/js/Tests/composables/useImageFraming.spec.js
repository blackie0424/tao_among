import { describe, it, expect } from 'vitest'
import { computeFramedLayout, framedImageStyle, rotatedSize } from '@/composables/useImageFraming'

const layout = (photoWidth, photoHeight, frameWidth, frameHeight, framing) =>
  computeFramedLayout({ photoWidth, photoHeight, frameWidth, frameHeight, framing })

const framing = (x, y, zoom, rotation = 0) => ({ x, y, zoom, rotation })

describe('useImageFraming 顯示規則', () => {
  it('(a) 4000×3000、框 320×180、中心 30/60、1.0x：顯示 320×240，左上角 (0,-54)', () => {
    const { stage } = layout(4000, 3000, 320, 180, framing(30, 60, 1))

    expect(stage.widthPct).toBeCloseTo(100)
    expect(stage.heightPct).toBeCloseTo((240 / 180) * 100)
    expect(stage.leftPct).toBeCloseTo(0)
    expect(stage.topPct).toBeCloseTo((-54 / 180) * 100)
    // 看到的縱向範圍：照片的 22.5%～97.5%
    const visibleTop = (-stage.topPct / stage.heightPct) * 100
    const visibleBottom = ((100 - stage.topPct) / stage.heightPct) * 100
    expect(visibleTop).toBeCloseTo(22.5)
    expect(visibleBottom).toBeCloseTo(97.5)
  })

  it('(b) 同照片同框、1.5x：顯示 480×360，左上角 (0,-126)，橫向夾限仍從 0 開始', () => {
    const { stage } = layout(4000, 3000, 320, 180, framing(30, 60, 1.5))

    expect(stage.widthPct).toBeCloseTo((480 / 320) * 100)
    expect(stage.heightPct).toBeCloseTo((360 / 180) * 100)
    expect(stage.leftPct).toBeCloseTo(0)
    expect(stage.topPct).toBeCloseTo((-126 / 180) * 100)
    const visibleTop = (-stage.topPct / stage.heightPct) * 100
    const visibleBottom = ((100 - stage.topPct) / stage.heightPct) * 100
    expect(visibleTop).toBeCloseTo(35)
    expect(visibleBottom).toBeCloseTo(85)
  })

  it('(c) 2250×4000 轉 90 度、16:9 框、1.0x：整張照片可見', () => {
    const { stage, image, rotation } = layout(2250, 4000, 320, 180, framing(50, 50, 1, 90))

    expect(rotation).toBe(90)
    expect(stage.widthPct).toBeCloseTo(100)
    expect(stage.heightPct).toBeCloseTo(100)
    expect(stage.leftPct).toBeCloseTo(0)
    expect(stage.topPct).toBeCloseTo(0)
    // 旋轉前的 <img> 是 180×320，繞中心轉 90 度後剛好填滿 320×180
    expect(image.widthPct).toBeCloseTo((180 / 320) * 100)
    expect(image.heightPct).toBeCloseTo((320 / 180) * 100)
    expect(image.leftPct).toBeCloseTo((70 / 320) * 100)
    expect(image.topPct).toBeCloseTo((-70 / 180) * 100)
  })

  it('(c) 同照片換成 240×180 的框：顯示 320×180，橫向只看到 75%', () => {
    const { stage } = layout(2250, 4000, 240, 180, framing(50, 50, 1, 90))

    expect(stage.widthPct).toBeCloseTo((320 / 240) * 100)
    expect(stage.heightPct).toBeCloseTo(100)
    expect(100 / stage.widthPct).toBeCloseTo(0.75)
  })

  it('(d) 中心在照片邊角：貼齊邊角、不露白', () => {
    const topLeft = layout(4000, 3000, 320, 180, framing(0, 0, 1.5)).stage
    expect(topLeft.leftPct).toBeCloseTo(0)
    expect(topLeft.topPct).toBeCloseTo(0)

    const bottomRight = layout(4000, 3000, 320, 180, framing(100, 100, 1.5)).stage
    // 照片右下角剛好貼齊框右下角
    expect(bottomRight.leftPct + bottomRight.widthPct).toBeCloseTo(100)
    expect(bottomRight.topPct + bottomRight.heightPct).toBeCloseTo(100)
  })

  it('框永遠被照片填滿（任意中心與放大都不露白）', () => {
    for (const [x, y, zoom, rotation] of [[0, 100, 1, 0], [100, 0, 3, 270], [37, 81, 2.2, 180], [50, 50, 1, 90]]) {
      const { stage } = layout(3000, 2000, 300, 200, framing(x, y, zoom, rotation))
      expect(stage.leftPct).toBeLessThanOrEqual(1e-9)
      expect(stage.topPct).toBeLessThanOrEqual(1e-9)
      expect(stage.leftPct + stage.widthPct).toBeGreaterThanOrEqual(100 - 1e-9)
      expect(stage.topPct + stage.heightPct).toBeGreaterThanOrEqual(100 - 1e-9)
    }
  })

  it('null（沒調整）等同置中 1.0x 不旋轉的 cover', () => {
    expect(layout(4000, 3000, 320, 180, null)).toEqual(layout(4000, 3000, 320, 180, framing(50, 50, 1, 0)))
    expect(layout(4000, 3000, 320, 180, null).stage.leftPct).toBeCloseTo(0)
  })

  it('轉 90／270 度時寬高對調，0／180 不對調', () => {
    expect(rotatedSize(100, 200, 90)).toEqual({ width: 200, height: 100 })
    expect(rotatedSize(100, 200, 270)).toEqual({ width: 200, height: 100 })
    expect(rotatedSize(100, 200, 180)).toEqual({ width: 100, height: 200 })
    expect(rotatedSize(100, 200, 0)).toEqual({ width: 100, height: 200 })
  })

  it('framedImageStyle 轉成百分比樣式與旋轉', () => {
    const style = framedImageStyle(layout(2250, 4000, 320, 180, framing(50, 50, 1, 90)))

    expect(style.position).toBe('absolute')
    expect(style.maxWidth).toBe('none')
    expect(style.transform).toBe('rotate(90deg)')
    expect(style.width).toBe(`${(180 / 320) * 100}%`)
    expect(framedImageStyle(layout(4000, 3000, 320, 180, null)).transform).toBe('none')
  })
})

import { dragFraming, effectiveCenter, framingToFields, rotateFraming, zoomFraming } from '@/composables/useImageFraming'

describe('編輯器：拖曳、放大、旋轉', () => {
  const photo = { width: 4000, height: 3000 }
  const frame = { width: 320, height: 240 } // 4:3 調整框

  it('effectiveCenter：置中 1.0x 的中心是 50/50；中心被夾限時回報實際在框中央的點', () => {
    expect(effectiveCenter(computeFramedLayout({ photoWidth: 4000, photoHeight: 3000, frameWidth: 320, frameHeight: 240, framing: null }))).toEqual({ x: 50, y: 50 })

    // 1.0x、框 320×180：縱向只有 240 的照片，中心 0% 會被夾限，實際中央在照片 37.5%
    const clamped = computeFramedLayout({ photoWidth: 4000, photoHeight: 3000, frameWidth: 320, frameHeight: 180, framing: { x: 50, y: 0, zoom: 1, rotation: 0 } })
    expect(effectiveCenter(clamped).y).toBeCloseTo(37.5)
  })

  it('拖曳：2.0x 時手指往右拖 100px，魚身中心往左移（顯示 640px 寬，移 100/640＝15.625%）', () => {
    const next = dragFraming({ x: 50, y: 50, zoom: 2, rotation: 0 }, photo, frame, 100, 0)

    expect(next.x).toBeCloseTo(34.38, 1)
    expect(next.y).toBe(50)
    expect(next.zoom).toBe(2)
  })

  it('拖曳到照片邊緣：寫回夾限後的實際中心，不會超出可移動範圍', () => {
    const next = dragFraming({ x: 50, y: 50, zoom: 2, rotation: 0 }, photo, frame, 5000, 0)

    // 2.0x 時框只能看到照片寬度的一半，中心最左只能到 25%
    expect(next.x).toBeCloseTo(25)
  })

  it('1.0x 時沒有可移動空間的方向，拖曳不改變', () => {
    const next = dragFraming(null, photo, frame, 80, 80)

    expect(next).toEqual({ x: 50, y: 50, zoom: 1, rotation: 0 })
  })

  it('放大保持框中央的點不動，並夾在 1.0～3.0', () => {
    const at = { x: 30, y: 50, zoom: 2, rotation: 0 }
    const centerBefore = effectiveCenter(computeFramedLayout({ photoWidth: 4000, photoHeight: 3000, frameWidth: 320, frameHeight: 240, framing: at }))

    const zoomed = zoomFraming(at, photo, frame, 2.5)
    expect(zoomed.zoom).toBe(2.5)
    expect(zoomed.x).toBeCloseTo(centerBefore.x, 1)

    expect(zoomFraming(at, photo, frame, 9).zoom).toBe(3)
    expect(zoomFraming(at, photo, frame, 0.2).zoom).toBe(1)
  })

  it('旋轉：每次 90 度，中心與放大重設為 50/50/1.0；可連續轉、可反向', () => {
    expect(rotateFraming({ x: 20, y: 30, zoom: 2, rotation: 0 }, 1)).toEqual({ x: 50, y: 50, zoom: 1, rotation: 90 })
    expect(rotateFraming({ x: 50, y: 50, zoom: 1, rotation: 270 }, 1).rotation).toBe(0)
    expect(rotateFraming(null, -1).rotation).toBe(270)
  })

  it('framingToFields：有調整是四個值，沒調整（null）是四個 null', () => {
    expect(framingToFields({ x: 30.5, y: 60, zoom: 1.5, rotation: 90 })).toEqual({
      image_focus_x: 30.5, image_focus_y: 60, image_zoom: 1.5, image_rotation: 90,
    })
    expect(framingToFields(null)).toEqual({
      image_focus_x: null, image_focus_y: null, image_zoom: null, image_rotation: null,
    })
  })
})
