// 魚島 3D：畫質分級（#199 驗收條件 4）。三級只差在繪圖解析度（裝置像素比的上限）。不依賴 three.js。

// 由高到低；maxPixelRatio 是這一級的裝置像素比上限，fixedPixelRatio 是不看裝置、固定用的值
const QUALITY_LEVELS = [
  { name: 'high', maxPixelRatio: 1.75 },
  { name: 'medium', maxPixelRatio: 1.4 },
  { name: 'low', fixedPixelRatio: 1 },
]

// 視窗寬度在這以下當成手機
const NARROW_VIEWPORT_MAX_WIDTH = 800
const MOBILE_USER_AGENT = /Mobi|Android/i

// 載入後這段時間不列入計算（剛載入時本來就會卡）
const WARMUP_MS = 4000
// 每累積這麼久的畫面算一次平均
const WINDOW_MS = 3000
// 平均每張畫面間隔超過這個值（低於每秒 30 張）就降一級
const SLOW_FRAME_MS = 33
// 單張超過這個值當成停頓（切分頁、卡住），不列入計算
const STALL_FRAME_MS = 250

/** 一開始的級別：視窗窄或手機瀏覽器用「中」，其他用「高」 */
export function initialQuality({ width, userAgent }) {
  return width <= NARROW_VIEWPORT_MAX_WIDTH || MOBILE_USER_AGENT.test(userAgent) ? 'medium' : 'high'
}

/** 這一級實際用的繪圖解析度：「低」固定 1；其他是裝置自己的像素比，但不超過這一級的上限 */
export function pixelRatioFor(level, devicePixelRatio) {
  const { maxPixelRatio, fixedPixelRatio } = QUALITY_LEVELS.find(({ name }) => name === level)
  return fixedPixelRatio ?? Math.min(devicePixelRatio, maxPixelRatio)
}

/**
 * 執行中的自動降級：每張畫面呼叫一次 sample，級別改變時回傳新的級別名稱，否則回傳 null。
 * 只會往下降、最低到「低」，不會自動升回去。
 */
export function createQualityGovernor(initialLevel) {
  let index = QUALITY_LEVELS.findIndex(({ name }) => name === initialLevel)
  let startedAt = null
  let windowMs = 0
  let frames = 0

  return {
    get level() {
      return QUALITY_LEVELS[index].name
    },
    sample(now, frameMs, hidden = false) {
      startedAt ??= now
      if (now - startedAt < WARMUP_MS || hidden || frameMs > STALL_FRAME_MS) {
        return null
      }
      windowMs += frameMs
      frames += 1
      if (windowMs < WINDOW_MS) {
        return null
      }
      const meanFrameMs = windowMs / frames
      windowMs = 0
      frames = 0
      if (meanFrameMs <= SLOW_FRAME_MS || index === QUALITY_LEVELS.length - 1) {
        return null
      }
      index += 1
      return QUALITY_LEVELS[index].name
    },
  }
}

/**
 * 把畫面時鐘（frameClock）的一張畫面交給調節器。沒出畫面（null）或間隔不是真的量到的
 * （interrupted：第一張、從背景回來、停頓太久）都不列入計算。回傳值同 governor.sample。
 */
export function sampleFrame(governor, now, frame) {
  if (frame === null || frame.interrupted) {
    return null
  }
  return governor.sample(now, frame.frameMs)
}
