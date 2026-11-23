<template>
  <span :class="variant === 'bar' ? 'block w-full' : 'inline-flex items-center gap-2'">
    <!-- 長條版（試聽畫面） -->
    <button
      v-if="variant === 'bar'"
      type="button"
      :class="barClasses"
      :data-state="state"
      :aria-label="ariaLabel"
      v-bind="$attrs"
      @click="toggle"
    >
      <svg v-if="state === 'loading'" class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" data-testid="play-spinner" aria-hidden="true">
        <circle cx="12" cy="12" r="9" opacity=".25" />
        <path d="M12 3a9 9 0 0 1 9 9" />
      </svg>
      <svg v-else-if="state === 'playing'" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
        <rect class="rpb-bar" x="4" y="10" width="4" height="10" />
        <rect class="rpb-bar rpb-bar-2" x="10" y="4" width="4" height="16" />
        <rect class="rpb-bar rpb-bar-3" x="16" y="8" width="4" height="12" />
      </svg>
      <span v-else-if="state === 'idle' || state === 'ended'" aria-hidden="true">▶</span>
      <span>{{ barText }}</span>
      <span v-if="state === 'playing'" class="absolute bottom-0 left-0 h-1.5 w-full bg-white/25" aria-hidden="true">
        <span class="block h-1.5 bg-white" :style="{ width: `${percent}%` }" data-testid="play-progress"></span>
      </span>
    </button>

    <!-- 圓形圖示版（說法卡、你的錄音） -->
    <template v-else>
      <button
        type="button"
        :class="circleClasses"
        :data-state="state"
        :aria-label="ariaLabel"
        v-bind="$attrs"
        @click="toggle"
      >
        <span v-if="state === 'playing'" class="flex h-12 w-12 items-center justify-center rounded-full" :style="{ background: `conic-gradient(${RING_COLOR} ${percent}%, ${RING_TRACK_COLOR} 0)` }" data-testid="play-ring" :data-percent="percent">
          <span class="flex h-[2.4rem] w-[2.4rem] items-center justify-center rounded-full bg-brand text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <rect class="rpb-bar" x="4" y="10" width="4" height="10" />
              <rect class="rpb-bar rpb-bar-2" x="10" y="4" width="4" height="16" />
              <rect class="rpb-bar rpb-bar-3" x="16" y="8" width="4" height="12" />
            </svg>
          </span>
        </span>
        <svg v-else-if="state === 'loading'" class="h-6 w-6 animate-spin text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" data-testid="play-spinner" aria-hidden="true">
          <circle cx="12" cy="12" r="9" opacity=".25" />
          <path d="M12 3a9 9 0 0 1 9 9" />
        </svg>
        <span v-else-if="state === 'error'" class="text-xl font-bold text-red-700" aria-hidden="true">!</span>
        <svg v-else class="h-5 w-5 text-brand" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
      </button>
      <span v-if="state === 'playing'" class="text-elder-aux font-bold text-brand" data-testid="play-time">{{ timeText }}</span>
    </template>
  </span>
</template>

<script>
// 同一時間只播一段：開始播新的時，把前一段停掉（要放在一般 script，所有元件共用同一個變數）
let activePlayer = null
</script>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  src: { type: String, required: true },
  variant: { type: String, default: 'circle' }, // 'bar'＝試聽畫面的長條；'circle'＝圓形圖示
  knownSeconds: { type: Number, default: null }, // 已知的總秒數（錄音時記下的）；瀏覽器讀不到長度時用
  idleLabel: { type: String, default: '聽聽看' },
  endedLabel: { type: String, default: '再聽一次' },
  errorLabel: { type: String, default: '無法播放' },
})

const SECONDS_PER_MINUTE = 60
const PERCENT = 100
const RING_COLOR = '#2f5d62' // 進度外圈（品牌色）
const RING_TRACK_COLOR = '#cfc5b3' // 進度外圈底色（框線色）

const state = ref('idle') // idle | loading | playing | ended | error
const currentTime = ref(0)
const audioDuration = ref(NaN)
let audio = null

const totalSeconds = computed(() => {
  if (props.knownSeconds) return props.knownSeconds
  return Number.isFinite(audioDuration.value) && audioDuration.value > 0 ? Math.round(audioDuration.value) : null
})
const percent = computed(() => (totalSeconds.value ? Math.min(PERCENT, Math.floor((currentTime.value / totalSeconds.value) * PERCENT)) : 0))

const formatTime = (seconds) => `${Math.floor(seconds / SECONDS_PER_MINUTE)}:${String(Math.floor(seconds % SECONDS_PER_MINUTE)).padStart(2, '0')}`
const timeText = computed(() => (totalSeconds.value ? `${formatTime(currentTime.value)}／${formatTime(totalSeconds.value)}` : formatTime(currentTime.value)))

const barText = computed(() => {
  if (state.value === 'loading') return '準備播放…'
  if (state.value === 'playing') return `播放中 ${timeText.value}`
  if (state.value === 'ended') return props.endedLabel
  if (state.value === 'error') return props.errorLabel
  return props.idleLabel
})
const ariaLabel = computed(() => {
  if (state.value === 'loading') return '準備播放，再按一下取消'
  if (state.value === 'playing') return '播放中，再按一下停止'
  if (state.value === 'error') return `${props.errorLabel}，再按一次重試`
  return '播放錄音'
})

const barBase = 'relative flex w-full min-h-[3.5rem] items-center justify-center gap-2 overflow-hidden border-2 px-2 text-elder-body'
const barClasses = computed(
  () =>
    ({
      playing: `${barBase} border-brand bg-brand font-bold text-white`,
      loading: `${barBase} border-brand bg-brand/10 font-bold text-brand`,
      error: `${barBase} border-red-700 bg-white text-red-700`,
    })[state.value] ?? `${barBase} border-rule-strong bg-white text-elder-text`
)
const circleClasses = computed(
  () =>
    ({
      playing: 'inline-flex h-12 w-12 items-center justify-center rounded-full',
      loading: 'inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-brand bg-brand/10',
      error: 'inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-red-700',
    })[state.value] ?? 'inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-brand hover:bg-paper'
)

const player = { stop }

function release() {
  if (activePlayer === player) activePlayer = null
}

function ensureAudio() {
  if (audio) return audio
  audio = new Audio(props.src)
  audio.addEventListener('playing', () => {
    state.value = 'playing'
  })
  audio.addEventListener('loadedmetadata', () => {
    audioDuration.value = audio.duration
  })
  audio.addEventListener('timeupdate', () => {
    audioDuration.value = audio.duration
    currentTime.value = audio.currentTime
  })
  audio.addEventListener('ended', () => {
    state.value = 'ended'
    currentTime.value = 0
    release()
  })
  audio.addEventListener('error', fail)
  return audio
}

function fail() {
  state.value = 'error'
  currentTime.value = 0
  release()
}

function stop() {
  if (audio) {
    audio.pause()
    audio.currentTime = 0
  }
  currentTime.value = 0
  state.value = 'idle'
  release()
}

function start() {
  if (activePlayer && activePlayer !== player) activePlayer.stop()
  activePlayer = player
  state.value = 'loading'
  currentTime.value = 0
  const element = ensureAudio()
  element.currentTime = 0
  element.play()?.catch?.(fail)
}

function toggle() {
  if (state.value === 'loading' || state.value === 'playing') stop()
  else start()
}

function dispose() {
  if (audio) audio.pause()
  audio = null
  audioDuration.value = NaN
  release()
}

watch(
  () => props.src,
  () => {
    dispose()
    currentTime.value = 0
    state.value = 'idle'
  }
)

onBeforeUnmount(dispose)
</script>

<style scoped>
.rpb-bar {
  transform-box: fill-box;
  transform-origin: bottom;
  animation: rpb-bounce 0.9s ease-in-out infinite;
}
.rpb-bar-2 {
  animation-delay: 0.15s;
}
.rpb-bar-3 {
  animation-delay: 0.3s;
}
@keyframes rpb-bounce {
  0%,
  100% {
    transform: scaleY(0.45);
  }
  50% {
    transform: scaleY(1);
  }
}
@media (prefers-reduced-motion: reduce) {
  .rpb-bar {
    animation: none;
  }
}
</style>
