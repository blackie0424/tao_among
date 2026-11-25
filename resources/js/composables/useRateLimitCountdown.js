import { router } from '@inertiajs/vue3'
import { onMounted, onUnmounted, ref } from 'vue'

const HTTP_TOO_MANY_REQUESTS = 429
// 伺服器沒回報要等多久時用的秒數（限流以一分鐘為單位）
const DEFAULT_WAIT_SECONDS = 60
const ONE_SECOND_MS = 1000
// Retry-After 只接受正整數的秒數（例如 "45"）；"1.5"、"45abc"、"0" 都不算
const POSITIVE_INTEGER = /^[1-9]\d*$/

/** 429 回應的 Retry-After（秒）；沒有值或不是正整數時用預設值 */
function retryAfterSeconds(response) {
  const value = String(response.headers?.['retry-after'] ?? '').trim()
  return POSITIVE_INTEGER.test(value) ? Number(value) : DEFAULT_WAIT_SECONDS
}

/**
 * 魚島表單被限流時的倒數。
 * 被限流時伺服器回的不是頁面資料，預設會跳出瀏覽器的錯誤視窗；這裡攔下來，改成倒數還要等幾秒。
 * waitSeconds：還要等幾秒，0 代表沒有被限流；initialWaitSeconds：這次倒數一開始的秒數（給報讀軟體）。
 */
export function useRateLimitCountdown() {
  const waitSeconds = ref(0)
  const initialWaitSeconds = ref(0)

  let countdownTimer = null
  function stopCountdown() {
    clearInterval(countdownTimer)
    countdownTimer = null
  }

  function startCountdown(seconds) {
    stopCountdown()
    initialWaitSeconds.value = seconds
    waitSeconds.value = seconds
    countdownTimer = setInterval(() => {
      waitSeconds.value -= 1
      if (waitSeconds.value <= 0) {
        stopCountdown()
      }
    }, ONE_SECOND_MS)
  }

  let stopListeningInvalid = () => {}
  onMounted(() => {
    stopListeningInvalid = router.on('invalid', (event) => {
      if (event.detail.response.status === HTTP_TOO_MANY_REQUESTS) {
        event.preventDefault()
        startCountdown(retryAfterSeconds(event.detail.response))
      }
    })
  })
  onUnmounted(() => {
    stopListeningInvalid()
    stopCountdown()
  })

  return { waitSeconds, initialWaitSeconds }
}
