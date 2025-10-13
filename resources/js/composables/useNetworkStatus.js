/**
 * 網路狀態監控 Composable
 * 提供網路連線狀態監控和重連機制
 */

import { ref, onMounted, onUnmounted } from 'vue'

export function useNetworkStatus() {
  const isOnline = ref(navigator.onLine)
  const wasOffline = ref(false)
  const reconnectAttempts = ref(0)
  const maxReconnectAttempts = 5

  /**
   * 處理網路連線事件
   */
  function handleOnline() {
    const wasOfflineBefore = !isOnline.value
    isOnline.value = true

    if (wasOfflineBefore) {
      wasOffline.value = true
      reconnectAttempts.value = 0
      console.log('網路連線已恢復')

      // 觸發重連事件
      window.dispatchEvent(new CustomEvent('network-reconnected'))

      // 3秒後清除離線狀態提示
      setTimeout(() => {
        wasOffline.value = false
      }, 3000)
    }
  }

  /**
   * 處理網路斷線事件
   */
  function handleOffline() {
    isOnline.value = false
    console.log('網路連線已斷開')

    // 觸發斷線事件
    window.dispatchEvent(new CustomEvent('network-disconnected'))
  }

  /**
   * 檢查網路連線狀態
   */
  async function checkConnection() {
    try {
      const response = await fetch('/api/ping', {
        method: 'HEAD',
        cache: 'no-cache',
      })
      return response.ok
    } catch (error) {
      return false
    }
  }

  /**
   * 嘗試重新連線
   */
  async function attemptReconnect() {
    if (reconnectAttempts.value >= maxReconnectAttempts) {
      return false
    }

    reconnectAttempts.value++

    try {
      const isConnected = await checkConnection()
      if (isConnected) {
        handleOnline()
        return true
      }
    } catch (error) {
      console.error('重連嘗試失敗:', error)
    }

    // 指數退避重試
    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts.value), 30000)
    setTimeout(attemptReconnect, delay)

    return false
  }

  /**
   * 監聽網路狀態變化
   */
  function startNetworkMonitoring() {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)

    // 定期檢查網路狀態（每30秒）
    const intervalId = setInterval(async () => {
      if (navigator.onLine && !isOnline.value) {
        const isActuallyOnline = await checkConnection()
        if (isActuallyOnline) {
          handleOnline()
        }
      }
    }, 30000)

    return () => {
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
      clearInterval(intervalId)
    }
  }

  onMounted(() => {
    const cleanup = startNetworkMonitoring()

    onUnmounted(() => {
      cleanup()
    })
  })

  return {
    isOnline,
    wasOffline,
    reconnectAttempts,
    maxReconnectAttempts,
    checkConnection,
    attemptReconnect,
  }
}
