<template>
  <Head :title="`魚島：${fish.name ?? '還沒有名字'}`" />

  <IslandLayout>
    <template #header>
      <Link
        :href="tribeUrl"
        class="flex min-h-touch-primary items-center rounded-island bg-white px-3 text-elder-aux font-bold"
      >
        ← {{ islandTribeLabel(tribe) }}
      </Link>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <!-- 照片完整顯示不裁切；點照片或「放大」全螢幕看，可兩指放大 -->
    <div
      data-testid="fish-photo"
      :data-size="supplementing ? 'small' : 'large'"
      class="relative overflow-hidden rounded-island bg-white transition-[height] duration-300 motion-reduce:transition-none"
      :class="supplementing ? 'h-28' : 'h-52'"
    >
      <button
        ref="zoomButton"
        type="button"
        data-testid="photo-zoom"
        class="block h-full w-full focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
        @click="photoOpen = true"
      >
        <img :src="fish.image_url" alt="" class="h-full w-full object-contain" />
        <span
          class="absolute bottom-2 right-2 rounded-island bg-island-sea px-3 py-1 text-elder-aux font-bold"
          >⤢ 放大</span
        >
      </button>
      <h1
        class="pointer-events-none absolute left-3 top-2 rounded-island bg-white/85 px-3 py-1 text-elder-heading font-extrabold"
      >
        {{ fish.name ?? '還沒有名字' }}
      </h1>
    </div>

    <div role="tablist" aria-label="題目" class="mt-3 flex gap-1 rounded-island bg-white p-1">
      <button
        v-for="q in questions"
        :key="q.key"
        type="button"
        role="tab"
        :aria-selected="q.key === activeKey ? 'true' : 'false'"
        class="min-h-touch-primary flex-1 rounded-island text-elder-body font-extrabold transition-colors duration-200 motion-reduce:transition-none"
        :class="q.key === activeKey ? 'bg-island-ink text-white' : 'text-island-ink'"
        @click="openTab(q.key)"
      >
        {{ TAB_LABELS[q.key] }}{{ q.my_answer !== null ? ' ✓' : '' }}
      </button>
    </div>

    <h2 class="mt-3 px-1 text-elder-title font-extrabold">{{ promptOf(activeKey) }}</h2>

    <!-- 倒數每秒變動：給報讀軟體的是固定的一句（一開始的秒數），畫面上的倒數不重複報讀 -->
    <p
      v-if="waitSeconds > 0"
      role="alert"
      class="mt-2 rounded-island bg-white p-3 text-elder-body font-bold text-red-700"
    >
      <span class="sr-only">送出太多次了，請等 {{ initialWaitSeconds }} 秒再試</span>
      <span aria-hidden="true" data-testid="rate-limit-message"
        >送出太多次了，請等 {{ waitSeconds }} 秒再試</span
      >
    </p>

    <Transition name="island-fade" mode="out-in">
      <div v-if="mode === 'result'" :key="`result-${activeKey}`" class="mt-3">
        <div class="rounded-island bg-white p-4">
          <QuestionResult :result="current.result" :my-answer="current.my_answer" />
        </div>
        <button type="button" :class="[primaryButton, 'mt-3 w-full']" @click="startAnswer">
          {{ current.my_answer === null ? '我來回答' : '改我的回答' }}
        </button>
      </div>

      <IslandAnswerOptions
        v-else
        :key="`answer-${activeKey}`"
        v-model:supplementing="supplementing"
        class="mt-3"
        :options="current.options"
        :my-answer="current.my_answer"
        :allow-supplement="activeKey === 'name'"
        :locked="locked"
        :processing="form.processing"
        :wait-seconds="waitSeconds"
        :submit-label="submitLabel"
        :error-message="errorMessage"
        :name-max-length="name_max_length"
        cancel-label="先不答，看結果"
        @choose="send"
        @cancel="showResult"
        @reset="form.clearErrors()"
      />
    </Transition>

    <div class="mt-6 flex gap-2">
      <Link :href="tribeUrl" :class="secondaryButton">回到 {{ islandTribeLabel(tribe) }}</Link>
      <Link
        v-if="next_fish_id !== null"
        :href="`/island/tribes/${tribe}/fish/${next_fish_id}`"
        :class="[primaryButton, 'flex-1']"
      >
        下一條魚 ›
      </Link>
    </div>

    <div
      v-if="photoOpen"
      role="dialog"
      aria-modal="true"
      aria-label="魚的照片"
      class="fixed inset-0 z-50 flex flex-col bg-black/90"
    >
      <div class="flex justify-end p-3">
        <button
          ref="closePhotoButton"
          type="button"
          class="min-h-touch-primary rounded-island bg-white px-4 text-elder-body font-bold"
          @click="photoOpen = false"
        >
          關閉
        </button>
      </div>
      <!-- 交給瀏覽器的兩指縮放（viewport 允許放大到 5 倍） -->
      <img
        :src="fish.image_url"
        :alt="fish.name ?? '還沒有名字的魚'"
        class="min-h-0 flex-1 touch-pinch-zoom object-contain"
      />
    </div>
  </IslandLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import IslandAnswerOptions from '@/Components/Island/IslandAnswerOptions.vue'
import QuestionResult from '@/Components/Island/QuestionResult.vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { useRateLimitCountdown } from '@/composables/useRateLimitCountdown'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
  fish: { type: Object, required: true },
  questions: { type: Array, required: true },
  name_max_length: { type: Number, required: true },
  next_fish_id: { type: Number, default: null },
})

const TAB_LABELS = { name: '叫法', food_category: '分類', processing_method: '處理' }
const PROMPTS = {
  name: (tribe) => `${tribe}怎麼叫這條魚？`,
  food_category: (tribe) => `${tribe}把這條魚分在哪一類？`,
  processing_method: (tribe) => `${tribe}怎麼處理這條魚？`,
}
const primaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white disabled:opacity-50'
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold disabled:opacity-50'

// mode：result 看結果（每個分頁一進來）、answer 選選項；supplementing 正在打補充的叫法（選項元件裡切換）
// 網址帶 question（#189 地點清單、NPC 的「我來說」）時一進來停在那一題；沒帶或值不對時停在第一題
const requestedKey = new URLSearchParams(window.location.search).get('question')
const activeKey = ref(
  props.questions.some((q) => q.key === requestedKey) ? requestedKey : props.questions[0].key
)
const mode = ref('result')
const supplementing = ref(false)
const photoOpen = ref(false)
const closePhotoButton = ref(null)
const zoomButton = ref(null)
const form = useForm({ question: '', value: '' })
const { waitSeconds, initialWaitSeconds } = useRateLimitCountdown()

const current = computed(() => props.questions.find((q) => q.key === activeKey.value))
const tribeUrl = computed(() => `/island/tribes/${props.tribe}`)
// 送出途中或被限流時不能再送
const locked = computed(() => form.processing || waitSeconds.value > 0)
const errorMessage = computed(() => form.errors.value || form.errors.question || null)
const submitLabel = computed(() => {
  if (form.processing) return '送出中…'
  return waitSeconds.value > 0 ? `${waitSeconds.value} 秒後可以再試` : '送出'
})

const promptOf = (key) => PROMPTS[key](islandTribeLabel(props.tribe))

// 全螢幕照片：焦點移到「關閉」並留在對話框裡（裡面只有這一個可按的東西），Esc 不論焦點在哪都能關；
// 關閉後焦點回到「放大」
function onPhotoKeydown(event) {
  if (event.key === 'Escape') {
    photoOpen.value = false
  } else if (event.key === 'Tab') {
    event.preventDefault()
    closePhotoButton.value?.focus()
  }
}

watch(photoOpen, async (open) => {
  await nextTick()
  if (open) {
    document.addEventListener('keydown', onPhotoKeydown)
    closePhotoButton.value?.focus()
  } else {
    document.removeEventListener('keydown', onPhotoKeydown)
    zoomButton.value?.focus()
  }
})
onUnmounted(() => document.removeEventListener('keydown', onPhotoKeydown))

function setMode(next) {
  mode.value = next
  supplementing.value = false
  form.clearErrors()
}

function openTab(key) {
  activeKey.value = key
  setMode('result')
}

const startAnswer = () => setMode('answer')
const showResult = () => setMode('result')

/** 點選項（或送出補充）就送出這一題；回應可能在換分頁後才到，只有還停在同一題時才切回結果 */
function send(value) {
  if (locked.value) return
  const key = activeKey.value
  form.question = key
  form.value = value
  form.post(`/island/tribes/${props.tribe}/fish/${props.fish.id}/answers`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (activeKey.value === key) setMode('result')
    },
  })
}
</script>

<style scoped>
.island-fade-enter-active,
.island-fade-leave-active {
  transition:
    opacity 0.25s ease,
    transform 0.25s ease;
}
.island-fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.island-fade-leave-to {
  opacity: 0;
}
@media (prefers-reduced-motion: reduce) {
  .island-fade-enter-active,
  .island-fade-leave-active {
    transition: none;
  }
}
</style>
