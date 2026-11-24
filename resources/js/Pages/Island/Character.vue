<template>
  <Head title="魚島：設定角色" />

  <IslandLayout>
    <div class="px-1 pb-4 pt-4 text-center">
      <h1 class="text-elder-title font-extrabold">歡迎來到魚島</h1>
      <p class="mt-1 text-elder-body text-island-muted">不用登入，取個名字就能開始</p>
    </div>

    <form class="space-y-3" @submit.prevent="submit">
      <!-- 倒數每秒變動：給報讀軟體的是固定的一句（一開始的秒數），畫面上的倒數不重複報讀 -->
      <p
        v-if="waitSeconds > 0"
        role="alert"
        class="rounded-island bg-white p-4 text-elder-body font-bold text-red-700"
      >
        <span class="sr-only">送出太多次了，請等 {{ initialWaitSeconds }} 秒再試</span>
        <span aria-hidden="true" data-testid="rate-limit-message"
          >送出太多次了，請等 {{ waitSeconds }} 秒再試</span
        >
      </p>

      <div class="rounded-island bg-white p-4">
        <label for="island-name" class="block text-elder-body font-bold">你的名字</label>
        <input
          id="island-name"
          ref="nameInput"
          v-model="form.name"
          name="name"
          type="text"
          autocomplete="nickname"
          :maxlength="name_max_length"
          :aria-describedby="
            form.errors.name ? 'island-name-hint island-name-error' : 'island-name-hint'
          "
          :aria-invalid="form.errors.name ? 'true' : 'false'"
          class="mt-2 min-h-touch-primary w-full rounded-island border-2 px-3 text-elder-body focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/30"
          :class="form.errors.name ? 'border-red-700' : 'border-island-line'"
        />
        <p id="island-name-hint" class="mt-1 text-elder-aux text-island-muted">
          別人會看到這個名字，最多 {{ name_max_length }} 個字
        </p>
        <p
          v-if="form.errors.name"
          id="island-name-error"
          role="alert"
          class="mt-1 text-elder-body text-red-700"
        >
          {{ form.errors.name }}
        </p>
      </div>

      <fieldset class="rounded-island bg-white p-4">
        <legend class="float-left mb-3 w-full text-elder-body font-bold">你是哪個部落的人</legend>
        <div class="clear-both grid grid-cols-2 gap-2">
          <label
            v-for="tribe in tribes"
            :key="tribe"
            class="flex min-h-touch-primary cursor-pointer items-center justify-center rounded-island border-2 px-2 text-center text-elder-body font-bold has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-island-ink/30"
            :class="
              form.tribe === tribe
                ? 'border-island-ink bg-island-ink text-white'
                : 'border-island-line bg-white text-island-ink'
            "
          >
            <input
              v-model="form.tribe"
              type="radio"
              name="tribe"
              :value="tribe"
              class="sr-only"
              :aria-describedby="form.errors.tribe ? 'island-tribe-error' : undefined"
            />
            {{ islandTribeLabel(tribe) }}
          </label>
        </div>
        <p
          v-if="form.errors.tribe"
          id="island-tribe-error"
          role="alert"
          class="mt-2 text-elder-body text-red-700"
        >
          {{ form.errors.tribe }}
        </p>
      </fieldset>

      <button
        type="submit"
        :disabled="form.processing || waitSeconds > 0"
        class="min-h-touch-primary w-full rounded-island bg-island-accent text-elder-body font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40 disabled:opacity-50"
      >
        {{ submitLabel }}
      </button>
    </form>
  </IslandLayout>
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
  tribes: { type: Array, required: true },
  name_max_length: { type: Number, required: true },
})

const form = useForm({ name: '', tribe: '' })
const nameInput = ref(null)
// 被限流後還要等幾秒才能再送出；0 代表沒有被限流
const waitSeconds = ref(0)
const initialWaitSeconds = ref(0)

const HTTP_TOO_MANY_REQUESTS = 429
// 伺服器沒回報要等多久時用的秒數（限流以一分鐘為單位）
const DEFAULT_WAIT_SECONDS = 60
const ONE_SECOND_MS = 1000

const submitLabel = computed(() => {
  if (form.processing) return '進入中…'
  return waitSeconds.value > 0 ? `${waitSeconds.value} 秒後可以再試` : '進入魚島'
})

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

// Retry-After 只接受正整數的秒數（例如 "45"）；"1.5"、"45abc"、"0" 都不算
const POSITIVE_INTEGER = /^[1-9]\d*$/

/** 429 回應的 Retry-After（秒）；沒有值或不是正整數時用預設值 */
function retryAfterSeconds(response) {
  const value = String(response.headers?.['retry-after'] ?? '').trim()
  return POSITIVE_INTEGER.test(value) ? Number(value) : DEFAULT_WAIT_SECONDS
}

// 被限流時伺服器回的不是頁面資料，預設會跳出瀏覽器的錯誤視窗；這裡攔下來，改在表單上顯示還要等多久
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

// 送出後有錯：把焦點移到第一個有錯的欄位（名字在前、部落在後）
async function focusFirstError(errors) {
  await nextTick()
  if (errors.name) {
    nameInput.value?.focus()
  } else if (errors.tribe) {
    document.querySelector('input[name="tribe"]')?.focus()
  }
}

function submit() {
  if (waitSeconds.value > 0) return
  form.post('/island/character', { onError: focusFirstError })
}
</script>
