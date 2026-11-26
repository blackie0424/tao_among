import { describe, expect, it } from 'vitest'
import { createFrameClock } from '@/island3d/frameClock'

describe('island3d/frameClock 畫面時間', () => {
  it('第一張畫面間隔是 0，之後是和上一張的差', () => {
    const tick = createFrameClock()

    expect(tick(1000, false)).toEqual({ frameMs: 0, deltaSeconds: 0, elapsedSeconds: 0 })
    expect(tick(1016, false)).toEqual({ frameMs: 16, deltaSeconds: 0.016, elapsedSeconds: 0.016 })
    expect(tick(1056, false)).toEqual({ frameMs: 40, deltaSeconds: 0.04, elapsedSeconds: 0.056 })
  })

  it('分頁在背景時不出畫面（回傳 null）', () => {
    const tick = createFrameClock()
    tick(1000, false)

    expect(tick(1016, true)).toBeNull()
    expect(tick(6000, true)).toBeNull()
  })

  it('從背景回來：第一張間隔重設為 0，經過的秒數不含背景時間', () => {
    const tick = createFrameClock()
    tick(1000, false)
    tick(1100, false) // 經過 0.1 秒
    tick(1116, true)
    tick(61000, true) // 在背景待了一分鐘

    expect(tick(61016, false)).toEqual({ frameMs: 0, deltaSeconds: 0, elapsedSeconds: 0.1 })
    expect(tick(61036, false)).toEqual({ frameMs: 20, deltaSeconds: 0.02, elapsedSeconds: 0.12 })
  })

  it('瀏覽器在背景直接不叫畫面（沒有收到背景狀態）：隔太久的那一張也當成重新開始', () => {
    const tick = createFrameClock()
    tick(1000, false)
    tick(1100, false)

    // 兩張隔了一分鐘，超過 250 毫秒的上限
    expect(tick(61100, false)).toEqual({ frameMs: 0, deltaSeconds: 0, elapsedSeconds: 0.1 })
  })

  it('剛好 250 毫秒的間隔照算', () => {
    const tick = createFrameClock()
    tick(1000, false)

    expect(tick(1250, false)).toEqual({ frameMs: 250, deltaSeconds: 0.25, elapsedSeconds: 0.25 })
  })
})
