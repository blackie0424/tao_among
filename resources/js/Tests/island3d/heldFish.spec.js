import { describe, expect, it } from 'vitest'
import { createHeldFish, heldFishKey } from '@/island3d/heldFish'

// #232 AC 2、4：手上的魚——只存在這台瀏覽器、依角色分開；從所有魚完全隨機抽，可能重複

function memoryStorage() {
  const data = new Map()
  return {
    getItem: (key) => (data.has(key) ? data.get(key) : null),
    setItem: (key, value) => data.set(key, String(value)),
    removeItem: (key) => data.delete(key),
  }
}

const brokenStorage = {
  getItem: () => {
    throw new Error('blocked')
  },
  setItem: () => {
    throw new Error('quota')
  },
  removeItem: () => {
    throw new Error('blocked')
  },
}

const sequence = (...values) => {
  let i = 0
  return () => values[i++ % values.length]
}

describe('heldFishKey', () => {
  it('依角色分開', () => {
    expect(heldFishKey(3)).not.toBe(heldFishKey(4))
  })
})

describe('createHeldFish', () => {
  const fishIds = [10, 20, 30]

  it('一開始手上沒有魚', () => {
    const held = createHeldFish({ storage: memoryStorage(), key: 'k', fishIds })

    expect(held.get()).toBeNull()
  })

  it('找魚：依亂數從所有魚裡挑一條，拿在手上', () => {
    const held = createHeldFish({ storage: memoryStorage(), key: 'k', fishIds, random: () => 0.5 })

    expect(held.catchFish()).toEqual({ id: 20, released: null })
    expect(held.get()).toBe(20)
  })

  it('完全隨機：同一條可以再抽到', () => {
    const held = createHeldFish({
      storage: memoryStorage(),
      key: 'k',
      fishIds,
      random: sequence(0, 0),
    })

    held.catchFish()
    expect(held.catchFish()).toEqual({ id: 10, released: 10 })
  })

  it('手上已經有魚時，那條放回去（回傳放回的編號），換成新的', () => {
    const held = createHeldFish({
      storage: memoryStorage(),
      key: 'k',
      fishIds,
      random: sequence(0, 0.99),
    })

    held.catchFish()
    expect(held.catchFish()).toEqual({ id: 30, released: 10 })
    expect(held.get()).toBe(30)
  })

  it('重新整理（同一份儲存、同一個角色）手上的魚還在', () => {
    const storage = memoryStorage()
    createHeldFish({ storage, key: 'k', fishIds, random: () => 0 }).catchFish()

    expect(createHeldFish({ storage, key: 'k', fishIds }).get()).toBe(10)
  })

  it('別的角色看不到這個角色手上的魚', () => {
    const storage = memoryStorage()
    createHeldFish({ storage, key: heldFishKey(1), fishIds, random: () => 0 }).catchFish()

    expect(createHeldFish({ storage, key: heldFishKey(2), fishIds }).get()).toBeNull()
  })

  it('手上的魚已經不在魚清單裡（軟刪除）：當成沒魚，找到新魚時也不算放回', () => {
    const storage = memoryStorage()
    createHeldFish({ storage, key: 'k', fishIds: [99], random: () => 0 }).catchFish()
    const held = createHeldFish({ storage, key: 'k', fishIds, random: () => 0 })

    expect(held.get()).toBeNull()
    expect(held.catchFish()).toEqual({ id: 10, released: null })
  })

  it('儲存的值不是編號：當成沒魚', () => {
    const storage = memoryStorage()
    storage.setItem('k', 'abc')

    expect(createHeldFish({ storage, key: 'k', fishIds }).get()).toBeNull()
  })

  it('沒有任何魚：找不到魚（null），手上不變', () => {
    const held = createHeldFish({ storage: memoryStorage(), key: 'k', fishIds: [] })

    expect(held.catchFish()).toBeNull()
    expect(held.get()).toBeNull()
  })

  it('瀏覽器不能存：只留在這次打開的頁面，不丟錯誤', () => {
    const held = createHeldFish({ storage: brokenStorage, key: 'k', fishIds, random: () => 0 })

    expect(held.catchFish()).toEqual({ id: 10, released: null })
    expect(held.get()).toBe(10)
  })

  it('沒有儲存（例如沒有 localStorage）：一樣只留在這次打開的頁面', () => {
    const held = createHeldFish({ storage: null, key: 'k', fishIds, random: () => 0 })

    held.catchFish()
    expect(held.get()).toBe(10)
  })
})
