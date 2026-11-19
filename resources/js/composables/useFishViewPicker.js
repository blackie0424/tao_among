/**
 * useFishViewPicker — 清單看法小視窗的開關與「已問過」記錄
 *
 * 職責（SRP）：
 *   - 管理小視窗是否顯示
 *   - 以帳號區分，記在這個瀏覽器的 localStorage：問過就不再自動跳出（不記選了哪一項）
 *
 * @param {number|string|null} userId  目前登入者編號（換帳號會再問一次）
 */

import { ref } from 'vue'

const ASKED_KEY_PREFIX = 'fish_view_asked'
const ASKED_VALUE = '1'

export function useFishViewPicker(userId) {
  const storageKey = `${ASKED_KEY_PREFIX}:${userId}`
  const show = ref(false)

  // 隱私模式等情況 localStorage 不能用：視為沒問過，每次都跳出，不報錯
  const hasAsked = () => {
    try {
      return localStorage.getItem(storageKey) === ASKED_VALUE
    } catch (e) {
      return false
    }
  }

  const markAsked = () => {
    try {
      localStorage.setItem(storageKey, ASKED_VALUE)
    } catch (e) {
      // 不能存就算了：下次進來會再問一次
    }
  }

  /** 第一次進來才自動跳出 */
  const openFirstTime = () => {
    if (!hasAsked()) show.value = true
  }

  /** 使用者主動開啟（右下角鈕） */
  const open = () => {
    show.value = true
  }

  /** 選了一項或關閉視窗，都算問過 */
  const close = () => {
    markAsked()
    show.value = false
  }

  return { show, openFirstTime, open, close }
}
