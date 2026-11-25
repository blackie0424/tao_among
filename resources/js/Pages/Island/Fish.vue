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

    <!-- 作答時照片最大；看結果或打補充時縮小，讓出空間 -->
    <div
      data-testid="fish-photo"
      :data-size="photoLarge ? 'large' : 'small'"
      class="island-photo relative overflow-hidden rounded-island bg-island-line"
      :class="photoLarge ? 'aspect-[4/3]' : 'h-28'"
    >
      <img :src="fish.image_url" alt="" class="h-full w-full object-cover" />
      <h1
        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-4 pb-3 pt-8 text-elder-title font-extrabold text-white"
      >
        {{ fish.name ?? '還沒有名字' }}
      </h1>
    </div>

    <ol class="mt-3 flex gap-1.5" :aria-label="`第 ${index + 1} / ${questions.length} 題`">
      <li
        v-for="(q, i) in questions"
        :key="q.key"
        data-testid="progress-step"
        :data-state="i <= index ? 'done' : 'todo'"
        class="h-2 flex-1 rounded-full transition-colors duration-300 motion-reduce:transition-none"
        :class="i <= index ? 'bg-island-accent' : 'bg-white'"
      />
    </ol>

    <h2 class="mt-3 px-1 text-elder-title font-extrabold">{{ promptOf(current.key) }}</h2>

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
      <div v-if="showingResult" :key="`result-${current.key}`" class="mt-3">
        <div class="rounded-island bg-white p-4">
          <QuestionResult :result="current.result" :my-answer="current.my_answer" />
        </div>
        <div class="mt-4 flex gap-2">
          <button
            v-if="current.my_answer !== null"
            type="button"
            :class="secondaryButton"
            @click="edit"
          >
            改我的回答
          </button>
          <button v-if="!isLast" type="button" :class="[primaryButton, 'flex-1']" @click="next">
            下一題 ›
          </button>
        </div>
        <div v-if="isLast" class="mt-2 flex flex-col gap-2">
          <Link
            v-if="next_fish_id !== null"
            :href="`/island/tribes/${tribe}/fish/${next_fish_id}`"
            :class="primaryButton"
          >
            下一條魚 ›
          </Link>
          <Link :href="tribeUrl" :class="secondaryButton">回到 {{ islandTribeLabel(tribe) }}</Link>
        </div>
      </div>

      <form
        v-else-if="supplementing"
        key="supplement"
        class="mt-3 space-y-2"
        @submit.prevent="send(supplement)"
      >
        <label for="island-supplement" class="block px-1 text-elder-body font-bold"
          >你知道的叫法</label
        >
        <input
          id="island-supplement"
          v-model="supplement"
          type="text"
          :maxlength="name_max_length"
          :aria-invalid="errorMessage ? 'true' : 'false'"
          :aria-describedby="errorMessage ? 'island-answer-error' : undefined"
          class="min-h-touch-primary w-full rounded-island border-2 border-island-ink px-3 text-elder-body"
        />
        <p class="px-1 text-elder-aux text-island-muted">最多 {{ name_max_length }} 個字</p>
        <p
          v-if="errorMessage"
          id="island-answer-error"
          role="alert"
          class="text-elder-body text-red-700"
        >
          {{ errorMessage }}
        </p>
        <div class="flex gap-2 pt-2">
          <button
            type="button"
            :disabled="form.processing"
            :class="secondaryButton"
            @click="cancelSupplement"
          >
            取消
          </button>
          <button type="submit" :disabled="locked" :class="[primaryButton, 'flex-1']">
            {{ submitLabel }}
          </button>
        </div>
      </form>

      <div v-else :key="`answer-${current.key}`" class="mt-3 flex flex-col gap-2">
        <button
          v-for="(option, i) in current.options"
          :key="i"
          type="button"
          data-testid="option"
          :disabled="locked"
          :aria-pressed="option === current.my_answer ? 'true' : 'false'"
          class="flex min-h-touch-primary items-center justify-between rounded-island border-2 bg-white px-4 text-left text-elder-body font-extrabold transition-transform active:scale-[0.98] disabled:opacity-50 motion-reduce:transition-none"
          :class="option === current.my_answer ? 'border-island-accent' : 'border-white'"
          @click="send(option)"
        >
          <span>{{ option }}</span>
          <span aria-hidden="true">›</span>
        </button>
        <button
          v-if="current.key === 'name'"
          type="button"
          data-testid="option"
          :disabled="locked"
          class="flex min-h-touch-primary items-center justify-between rounded-island border-2 border-white bg-white px-4 text-left text-elder-body font-bold disabled:opacity-50"
          @click="startSupplement"
        >
          <span>都不對，我來補充</span>
          <span aria-hidden="true">✎</span>
        </button>
        <p v-if="errorMessage" role="alert" class="text-elder-body text-red-700">
          {{ errorMessage }}
        </p>
        <button
          type="button"
          :disabled="form.processing"
          class="min-h-touch-primary rounded-island text-elder-body font-bold underline disabled:opacity-50"
          @click="reveal(current.key)"
        >
          不知道，跳過
        </button>
      </div>
    </Transition>
  </IslandLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
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

const PROMPTS = {
  name: (tribe) => `${tribe}怎麼叫這條魚？`,
  food_category: (tribe) => `${tribe}把這條魚分在哪一類？`,
  processing_method: (tribe) => `${tribe}怎麼處理這條魚？`,
}
const primaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white disabled:opacity-50'
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold disabled:opacity-50'

const index = ref(0)
// 這次看過結果的題目（答了或跳過）；editing＝正在改已答過的題目；supplementing＝正在打補充的叫法
const revealed = ref({})
const editing = ref(false)
const supplementing = ref(false)
const supplement = ref('')
const form = useForm({ question: '', value: '' })
const { waitSeconds, initialWaitSeconds } = useRateLimitCountdown()

const current = computed(() => props.questions[index.value])
const isLast = computed(() => index.value === props.questions.length - 1)
const tribeUrl = computed(() => `/island/tribes/${props.tribe}`)
const showingResult = computed(
  () => !editing.value && (revealed.value[current.value.key] || current.value.my_answer !== null)
)
const photoLarge = computed(() => !showingResult.value && !supplementing.value)
// 送出途中或被限流時不能再送
const locked = computed(() => form.processing || waitSeconds.value > 0)
const errorMessage = computed(() => form.errors.value || form.errors.question || null)
const submitLabel = computed(() => {
  if (form.processing) return '送出中…'
  return waitSeconds.value > 0 ? `${waitSeconds.value} 秒後可以再試` : '送出'
})

const promptOf = (key) => PROMPTS[key](islandTribeLabel(props.tribe))

function resetAnswerState() {
  supplementing.value = false
  supplement.value = ''
  form.clearErrors()
}

/** 顯示某一題的結果；送出的回應可能在換題後才到，所以一律指定題目，不用「目前這一題」 */
function reveal(key) {
  revealed.value = { ...revealed.value, [key]: true }
  if (key === current.value.key) {
    editing.value = false
    supplementing.value = false
  }
}

function edit() {
  resetAnswerState()
  editing.value = true
}

function next() {
  if (form.processing) return
  index.value += 1
  resetAnswerState()
}

function startSupplement() {
  form.clearErrors()
  supplementing.value = true
}

function cancelSupplement() {
  resetAnswerState()
}

/** 點選項（或送出補充）就送出這一題 */
function send(value) {
  if (locked.value) return
  const key = current.value.key
  form.question = key
  form.value = value
  form.post(`/island/tribes/${props.tribe}/fish/${props.fish.id}/answers`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => reveal(key),
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
.island-photo {
  transition: height 0.3s ease;
}
@media (prefers-reduced-motion: reduce) {
  .island-fade-enter-active,
  .island-fade-leave-active,
  .island-photo {
    transition: none;
  }
}
</style>
