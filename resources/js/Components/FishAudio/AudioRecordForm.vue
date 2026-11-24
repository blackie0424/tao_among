<template>
  <form class="space-y-6" @submit.prevent="submit">
    <fieldset>
      <legend class="text-elder-body font-bold text-elder-text mb-2">部落</legend>
      <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="部落">
        <button
          v-for="tribe in tribes"
          :key="tribe"
          type="button"
          role="radio"
          :aria-checked="selectedTribe === tribe"
          :data-tribe="tribe"
          class="min-h-touch-secondary px-2 text-elder-body capitalize"
          :class="selectedTribe === tribe ? 'border-2 border-brand font-bold text-elder-text' : 'border border-rule text-elder-subtext'"
          @click="selectedTribe = tribe"
        >
          {{ tribe }}
        </button>
      </div>
    </fieldset>

    <section>
      <h2 class="text-elder-body font-bold text-elder-text mb-2">
        錄音<span class="ml-1 text-elder-aux font-normal text-elder-subtext">（限 {{ maxSeconds }} 秒以內）</span>
      </h2>
      <slot name="current-recording" />
      <div class="border border-rule-strong bg-paper-raised p-4 text-center">
        <template v-if="!audioBlob">
          <button
            v-if="!isRecording"
            type="button"
            class="inline-flex h-[72px] w-[72px] items-center justify-center rounded-full bg-brand text-white text-elder-title hover:bg-brand-dark"
            aria-label="開始錄音"
            :disabled="submitting"
            @click="recorder.start()"
          >
            ●
          </button>
          <button
            v-else
            type="button"
            class="inline-flex h-[72px] w-[72px] items-center justify-center rounded-full border-2 border-red-600 text-red-700 text-elder-title"
            aria-label="停止錄音"
            @click="recorder.stop()"
          >
            ■
          </button>
          <p class="mt-2 text-elder-body text-elder-text">
            {{ isRecording ? `錄音中，還剩 ${remaining} 秒` : '按下開始錄音' }}
          </p>
          <canvas v-show="isRecording" ref="waveCanvas" width="300" height="60" class="mx-auto mt-2"></canvas>
        </template>
        <template v-else>
          <audio :src="audioUrl" controls class="mx-auto mb-2"></audio>
          <button
            type="button"
            class="min-h-touch-secondary px-4 text-elder-body text-brand underline"
            :disabled="submitting"
            @click="resetRecording"
          >
            重新錄音
          </button>
        </template>
      </div>
    </section>

    <p v-if="displayError" class="text-elder-body text-red-700" role="alert">{{ displayError }}</p>

    <button
      type="submit"
      class="block w-full min-h-touch-primary bg-brand text-white text-elder-body font-bold hover:bg-brand-dark disabled:opacity-60"
      :disabled="submitting"
    >
      {{ submitting ? '送出中…' : submitLabel }}
    </button>

    <slot name="footer" />
  </form>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useAudioRecorder } from '@/composables/useAudioRecorder.js'

const props = defineProps({
  tribes: { type: Array, default: () => [] },
  initialTribe: { type: String, default: null },
  maxSeconds: { type: Number, default: 5 },
  // 新增一定要錄音；編輯可以只改部落
  requireRecording: { type: Boolean, default: true },
  submitLabel: { type: String, default: '存檔' },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['submit'])

const selectedTribe = ref(props.tribes.includes(props.initialTribe) ? props.initialTribe : null)
const waveCanvas = ref(null)
const recorder = useAudioRecorder({ maxSeconds: props.maxSeconds, canvasRef: waveCanvas })
const { isRecording, audioBlob, audioUrl, remaining } = recorder

const localError = ref('')
watch(recorder.error, (message) => {
  localError.value = message
})
const displayError = computed(() => localError.value || props.error)

function resetRecording() {
  recorder.reset()
  localError.value = ''
}

function submit() {
  if (!selectedTribe.value) {
    localError.value = '請選擇部落'
    return
  }
  if (props.requireRecording && !audioBlob.value) {
    localError.value = '請先錄音'
    return
  }
  localError.value = ''
  emit('submit', {
    tribe: selectedTribe.value,
    blob: audioBlob.value,
    ext: recorder.ext.value,
    // 錄音元件量到的實際秒數；伺服器另外檢查不超過上限
    durationMs: audioBlob.value ? Math.max(1, Math.round(recorder.recordedSeconds.value * 1000)) : null,
  })
}
</script>
