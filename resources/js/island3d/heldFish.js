// #232 手上的魚：在海邊找到、還沒收進圖鑑也能拿去問的那一條。
// 只存在這台瀏覽器（localStorage），依角色分開；畫面只透過這個模組讀寫，之後要改存伺服器時只換這裡。
// 瀏覽器不能存時只留在這次打開的頁面

/** 儲存手上的魚用的鍵，依角色分開 */
export const heldFishKey = (characterId) => `island.heldFish.${characterId}`

function readStored(storage, key) {
  try {
    return storage?.getItem(key) ?? null
  } catch {
    return null
  }
}

function writeStored(storage, key, value) {
  try {
    storage?.setItem(key, String(value))
  } catch {
    // 存不進去：只留在這次打開的頁面
  }
}

/**
 * storage 瀏覽器的儲存（測試傳假的）；key 這個角色的鍵；fishIds 目前所有未軟刪除的魚編號；random 亂數（0～1）。
 * get()：手上的魚編號，沒有或已經不在清單裡是 null。
 * catchFish()：從所有魚裡完全隨機挑一條拿在手上，回傳 { id, released 放回的那條（沒有為 null）}；沒有任何魚時回傳 null
 */
export function createHeldFish({ storage, key, fishIds, random = Math.random }) {
  const valid = new Set(fishIds)
  const stored = Number(readStored(storage, key))
  let current = valid.has(stored) ? stored : null

  return {
    get: () => current,
    catchFish() {
      if (fishIds.length === 0) {
        return null
      }
      const released = current
      current = fishIds[Math.min(Math.floor(random() * fishIds.length), fishIds.length - 1)]
      writeStored(storage, key, current)
      return { id: current, released }
    },
  }
}
