<template>
  <!-- 魚島作答的選項（#232 從作答頁抽出，作答頁與 3D 的記錄視窗共用）：點選項就送出（發出 choose）；
       叫法另有「都不對，我來補充」打字。送出、限流與錯誤由上層處理，這裡只顯示 -->
  <form v-if="supplementing" class="space-y-2" @submit.prevent="emit('choose', supplement)">
    <label :for="inputId" class="block px-1 text-elder-body font-bold">你知道的叫法</label>
    <input
      :id="inputId"
      v-model="supplement"
      type="text"
      :maxlength="nameMaxLength"
      :aria-invalid="errorMessage ? 'true' : 'false'"
      :aria-describedby="errorMessage ? errorId : undefined"
      class="min-h-touch-primary w-full rounded-island border-2 border-island-ink px-3 text-elder-body"
    />
    <p class="px-1 text-elder-aux text-island-muted">最多 {{ nameMaxLength }} 個字</p>
    <p v-if="errorMessage" :id="errorId" role="alert" class="text-elder-body text-red-700">
      {{ errorMessage }}
    </p>
    <div class="flex gap-2 pt-2">
      <button
        type="button"
        :disabled="processing"
        :class="secondaryButton"
        @click="leaveSupplement"
      >
        取消
      </button>
      <button type="submit" :disabled="locked" :class="[primaryButton, 'flex-1']">
        {{ submitLabel }}
      </button>
    </div>
  </form>

  <div v-else class="flex flex-col gap-2">
    <!-- 點選項就送出，沒有送出鈕；被限流時在選項上方放一個停用的鈕告訴使用者還要等多久 -->
    <button v-if="waitSeconds > 0" type="button" disabled :class="primaryButton">
      {{ submitLabel }}
    </button>
    <button
      v-for="(option, i) in options"
      :key="i"
      type="button"
      data-testid="option"
      :disabled="locked"
      :aria-pressed="option === myAnswer ? 'true' : 'false'"
      class="flex min-h-touch-primary items-center justify-between rounded-island border-2 bg-white px-4 text-left text-elder-body font-extrabold transition-transform active:scale-[0.98] disabled:opacity-50 motion-reduce:transition-none"
      :class="option === myAnswer ? 'border-island-accent' : 'border-white'"
      @click="emit('choose', option)"
    >
      <span>{{ option }}</span>
      <span aria-hidden="true">›</span>
    </button>
    <button
      v-if="allowSupplement"
      type="button"
      data-testid="option"
      :disabled="locked"
      class="flex min-h-touch-primary items-center justify-between rounded-island border-2 border-white bg-white px-4 text-left text-elder-body font-bold disabled:opacity-50"
      @click="enterSupplement"
    >
      <span>都不對，我來補充</span>
      <span aria-hidden="true">✎</span>
    </button>
    <p v-if="errorMessage" role="alert" class="text-elder-body text-red-700">
      {{ errorMessage }}
    </p>
    <button
      v-if="cancelLabel"
      type="button"
      :disabled="processing"
      class="min-h-touch-primary rounded-island text-elder-body font-bold underline disabled:opacity-50"
      @click="emit('cancel')"
    >
      {{ cancelLabel }}
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  options: { type: Array, required: true },
  myAnswer: { type: String, default: null },
  // 叫法才有「都不對，我來補充」
  allowSupplement: { type: Boolean, default: false },
  // 送出途中或被限流時不能再送；waitSeconds 還要等幾秒（0 沒有被限流）
  locked: { type: Boolean, default: false },
  processing: { type: Boolean, default: false },
  waitSeconds: { type: Number, default: 0 },
  submitLabel: { type: String, required: true },
  errorMessage: { type: String, default: null },
  nameMaxLength: { type: Number, required: true },
  // 選項下方的「不答」按鈕文字；沒給就不顯示
  cancelLabel: { type: String, default: null },
  inputId: { type: String, default: 'island-supplement' },
})
// choose：選了一個選項或送出補充的叫法；cancel：按了「不答」；reset：進出補充時，上層清掉錯誤
const emit = defineEmits(['choose', 'cancel', 'reset'])
// 正在打補充的叫法（上層用來調整版面，例如作答頁縮小照片）
const supplementing = defineModel('supplementing', { type: Boolean, default: false })

const errorId = 'island-answer-error'
const supplement = ref('')
const primaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white disabled:opacity-50'
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold disabled:opacity-50'

function enterSupplement() {
  supplement.value = ''
  supplementing.value = true
  emit('reset')
}

function leaveSupplement() {
  supplement.value = ''
  supplementing.value = false
  emit('reset')
}
</script>
