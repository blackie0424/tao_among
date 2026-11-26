// 魚島 3D：把瀏覽器給的畫面時間，換成「這張畫面的間隔」與「開始到現在經過的秒數」。不依賴 three.js。
// 分頁在背景的時間不算：背景時不出畫面，回來後的第一張間隔重設為 0，
// 所以角色與 NPC 不會因為離開一陣子就瞬間跳一大段。

const MS_PER_SECOND = 1000
// 兩張畫面隔超過這麼久，當成中斷過（有些瀏覽器在背景直接不叫畫面，回來時才補一張）
const MAX_FRAME_GAP_MS = 250

/**
 * 回傳每張畫面呼叫一次的函式 tick(now, hidden)：
 * hidden（分頁在背景）時回傳 null，表示這張不要畫；否則回傳 { frameMs, deltaSeconds, elapsedSeconds }。
 */
export function createFrameClock() {
  let previous = null
  let elapsedMs = 0

  return (now, hidden) => {
    if (hidden) {
      previous = null
      return null
    }
    const gap = previous === null ? 0 : now - previous
    const frameMs = gap > MAX_FRAME_GAP_MS ? 0 : gap
    previous = now
    elapsedMs += frameMs
    return {
      frameMs,
      deltaSeconds: frameMs / MS_PER_SECOND,
      elapsedSeconds: elapsedMs / MS_PER_SECOND,
    }
  }
}
