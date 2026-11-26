import { describe, expect, it } from 'vitest'
import { createQualityGovernor, initialQuality, pixelRatioFor } from '@/island3d/quality'

const DESKTOP_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'
const PHONE_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) Chrome/126.0 Mobile Safari/537.36'

/** 連續餵 count 張、每張間隔 frameMs 的畫面，回傳這段期間發生的級別變化 */
const feed = (governor, clock, frameMs, count, hidden = false) => {
  const changes = []
  for (let i = 0; i < count; i++) {
    clock.now += frameMs
    const changed = governor.sample(clock.now, frameMs, hidden)
    if (changed) changes.push(changed)
  }
  return changes
}

/** 已經過了載入後前 4 秒的調節器 */
const warmedUp = (level) => {
  const governor = createQualityGovernor(level)
  const clock = { now: 0 }
  feed(governor, clock, 16, 251) // 251 × 16 = 4016 毫秒
  return { governor, clock }
}

describe('island3d/quality 畫質分級', () => {
  it('一開始：寬度 800 以下用「中」，801 以上的電腦用「高」', () => {
    expect(initialQuality({ width: 800, userAgent: DESKTOP_UA })).toBe('medium')
    expect(initialQuality({ width: 801, userAgent: DESKTOP_UA })).toBe('high')
  })

  it('一開始：手機瀏覽器即使視窗很寬也用「中」', () => {
    expect(initialQuality({ width: 1200, userAgent: PHONE_UA })).toBe('medium')
  })

  it('繪圖解析度上限：高 1.75、中 1.4、低固定 1', () => {
    expect(pixelRatioFor('high', 3)).toBe(1.75)
    expect(pixelRatioFor('medium', 3)).toBe(1.4)
    expect(pixelRatioFor('low', 3)).toBe(1)
  })

  it('裝置像素比低於上限時用裝置自己的值', () => {
    expect(pixelRatioFor('high', 1)).toBe(1)
    expect(pixelRatioFor('medium', 1.25)).toBe(1.25)
  })

  it('3 秒平均間隔超過 33 毫秒：降一級（高 → 中）', () => {
    const { governor, clock } = warmedUp('high')

    // 40 毫秒一張，75 張 = 3000 毫秒
    expect(feed(governor, clock, 40, 74)).toEqual([])
    expect(feed(governor, clock, 40, 1)).toEqual(['medium'])
    expect(governor.level).toBe('medium')
  })

  it('平均間隔剛好 33 毫秒不降級', () => {
    const { governor, clock } = warmedUp('high')

    expect(feed(governor, clock, 33, 200)).toEqual([])
    expect(governor.level).toBe('high')
  })

  it('持續變慢會一路降到「低」，之後不再降', () => {
    const { governor, clock } = warmedUp('high')

    expect(feed(governor, clock, 40, 75 * 5)).toEqual(['medium', 'low'])
    expect(governor.level).toBe('low')
  })

  it('變順了也不會自動升回去', () => {
    const { governor, clock } = warmedUp('medium')
    feed(governor, clock, 40, 75)

    expect(feed(governor, clock, 10, 2000)).toEqual([])
    expect(governor.level).toBe('low')
  })

  it('載入後的前 4 秒不列入計算', () => {
    const governor = createQualityGovernor('high')
    const clock = { now: 0 }

    // 前 4 秒很慢（40 毫秒 × 100 張），之後順（16 毫秒）
    expect(feed(governor, clock, 40, 100)).toEqual([])
    expect(feed(governor, clock, 16, 400)).toEqual([])
    expect(governor.level).toBe('high')
  })

  it('分頁在背景的時間不列入計算', () => {
    const { governor, clock } = warmedUp('high')

    expect(feed(governor, clock, 200, 100, true)).toEqual([])
    expect(feed(governor, clock, 16, 400)).toEqual([])
    expect(governor.level).toBe('high')
  })

  it('單張超過 250 毫秒的停頓不列入計算', () => {
    const { governor, clock } = warmedUp('high')

    feed(governor, clock, 16, 100)
    feed(governor, clock, 251, 20)

    expect(feed(governor, clock, 16, 400)).toEqual([])
    expect(governor.level).toBe('high')
  })
})
