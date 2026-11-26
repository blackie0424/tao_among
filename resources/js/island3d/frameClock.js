// 魚島 3D：把瀏覽器給的畫面時間，換成「這張畫面的間隔」與「開始到現在經過的秒數」。不依賴 three.js。
// 分頁在背景的時間不算：背景時不出畫面，回來後的第一張間隔重設為 0，
// 所以角色與 NPC 不會因為離開一陣子就瞬間跳一大段。

const MS_PER_SECOND = 1000
// 兩張畫面隔超過這麼久，當成中斷過（有些瀏覽器在背景直接不叫畫面，回來時才補一張）
const MAX_FRAME_GAP_MS = 250

/**
 * 回傳 { tick, pause }：
 * - tick(now, hidden) 每張畫面呼叫一次。hidden（分頁在背景）時回傳 null，表示這張不要畫；
 *   否則回傳 { frameMs, deltaSeconds, elapsedSeconds, interrupted }。
 *   interrupted 表示這張的間隔不是真的量到的（第一張、從背景回來、停頓太久），間隔一律算 0，
 *   也不該拿去算畫質。
 * - pause() 在分頁切到背景時呼叫：就算背景期間瀏覽器沒有叫任何畫面，回來的第一張也會重新開始。
 */
export function createFrameClock() {
  let previous = null
  let elapsedMs = 0

  const pause = () => {
    previous = null
  }

  const tick = (now, hidden) => {
    if (hidden) {
      pause()
      return null
    }
    const gap = previous === null ? null : now - previous
    const interrupted = gap === null || gap > MAX_FRAME_GAP_MS
    const frameMs = interrupted ? 0 : gap
    previous = now
    elapsedMs += frameMs
    return {
      frameMs,
      deltaSeconds: frameMs / MS_PER_SECOND,
      elapsedSeconds: elapsedMs / MS_PER_SECOND,
      interrupted,
    }
  }

  return { tick, pause }
}
