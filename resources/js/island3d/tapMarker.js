// 魚島 3D：點地面的提示（#200 驗收條件 4，規格修訂 3）。不依賴 three.js。
// 橘色圓圈標在目的地（位置來自 movement.js 的 destinationOf）；
// 點到不能走的地方時，灰色圓圈在點的位置出現一下。marker 是 { rejected }：
//   rejected 最近一次被拒絕的輕點 { point, until 幾秒時消失 }，沒有時是 null

// 灰色圓圈出現多久
const REJECTED_SECONDS = 0.5

export function createTapMarker() {
  return { rejected: null }
}

/**
 * 這次輕點算什麼。before、after 是 tapGround 前後的輸入狀態：
 * 'accepted' 設了新的路線；'ignored' 按住方向鍵所以不理會；'rejected' 點到不能走的地方
 */
export function tapOutcome(before, after) {
  if (after !== before) {
    return 'accepted'
  }
  return before.keys.length > 0 ? 'ignored' : 'rejected'
}

/** 點到不能走的地方：灰色圓圈出現在 point，從 nowSeconds 起算 REJECTED_SECONDS 後消失 */
export function rejectTap(marker, point, nowSeconds) {
  return {
    ...marker,
    rejected: { point: { x: point.x, z: point.z }, until: nowSeconds + REJECTED_SECONDS },
  }
}

/** nowSeconds 這個時候灰色圓圈在哪裡；已經消失或沒有是 null */
export function rejectedPointAt(marker, nowSeconds) {
  const { rejected } = marker
  return rejected !== null && nowSeconds < rejected.until ? rejected.point : null
}

/** 圓圈放大幾倍：鏡頭在最近的距離時是 1；拉遠時跟著放大（距離 4 倍時 2 倍），拉遠後仍看得到 */
export function markerScale(cameraDistance, nearestDistance) {
  return Math.sqrt(cameraDistance / nearestDistance)
}
