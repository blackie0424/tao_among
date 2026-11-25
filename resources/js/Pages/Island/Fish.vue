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

    <div class="mb-3 flex items-center gap-3 rounded-island bg-white p-3">
      <img
        :src="fish.image_url"
        alt=""
        class="h-16 w-20 rounded-island bg-island-line object-cover"
      />
      <div>
        <h1
          class="text-elder-heading font-extrabold"
          :class="{ 'text-island-muted': fish.name === null }"
        >
          {{ fish.name ?? '還沒有名字' }}
        </h1>
        <p class="text-elder-aux text-island-muted">
          第 {{ index + 1 }} / {{ questions.length }} 題
        </p>
      </div>
    </div>

    <section class="rounded-island bg-white p-4">
      <h2 class="text-elder-heading font-bold">{{ promptOf(current.key) }}</h2>

      <form v-if="!showingResult" class="mt-3 space-y-2" @submit.prevent="submit">
        <!-- 倒數每秒變動：給報讀軟體的是固定的一句（一開始的秒數），畫面上的倒數不重複報讀 -->
        <p v-if="waitSeconds > 0" role="alert" class="text-elder-body font-bold text-red-700">
          <span class="sr-only">送出太多次了，請等 {{ initialWaitSeconds }} 秒再試</span>
          <span aria-hidden="true" data-testid="rate-limit-message"
            >送出太多次了，請等 {{ waitSeconds }} 秒再試</span
          >
        </p>

        <label
          v-for="(option, i) in answerOptions"
          :key="i"
          data-testid="option"
          class="flex min-h-touch-primary cursor-pointer items-center rounded-island border-2 px-3 text-elder-body font-bold has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-island-ink/30"
          :class="
            choice === option.value
              ? 'border-island-ink bg-island-ink text-white'
              : 'border-island-line'
          "
        >
          <input
            v-model="choice"
            type="radio"
            name="answer"
            :value="option.value"
            class="sr-only"
          />
          {{ option.label }}
        </label>

        <div v-if="choice === SUPPLEMENT">
          <label for="island-supplement" class="block text-elder-body font-bold"
            >你知道的叫法</label
          >
          <input
            id="island-supplement"
            v-model="supplement"
            type="text"
            :maxlength="name_max_length"
            class="mt-1 min-h-touch-primary w-full rounded-island border-2 border-island-line px-3 text-elder-body"
          />
        </div>

        <p
          v-if="form.errors.value || form.errors.question"
          role="alert"
          class="text-elder-body text-red-700"
        >
          {{ form.errors.value || form.errors.question }}
        </p>

        <div class="flex gap-2 pt-2">
          <button
            type="button"
            :disabled="form.processing"
            :class="[secondaryButton, 'disabled:opacity-50']"
            @click="reveal(current.key)"
          >
            跳過
          </button>
          <button
            type="submit"
            :disabled="!choice || form.processing || waitSeconds > 0"
            class="min-h-touch-primary flex-1 rounded-island bg-island-accent text-elder-body font-bold text-white disabled:opacity-50"
          >
            {{ submitLabel }}
          </button>
        </div>
      </form>

      <div v-else>
        <QuestionResult :result="current.result" :my-answer="current.my_answer" />

        <div class="mt-4 flex flex-col gap-2">
          <button
            v-if="current.my_answer !== null"
            type="button"
            :class="secondaryButton"
            @click="edit"
          >
            改我的回答
          </button>
          <button v-if="!isLast" type="button" :class="primaryButton" @click="next">
            下一題 ›
          </button>
          <template v-else>
            <Link
              v-if="next_fish_id !== null"
              :href="`/island/tribes/${tribe}/fish/${next_fish_id}`"
              :class="primaryButton"
            >
              下一條魚 ›
            </Link>
            <Link :href="tribeUrl" :class="secondaryButton"
              >回到 {{ islandTribeLabel(tribe) }}</Link
            >
          </template>
        </div>
      </div>
    </section>
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

// 「都不對／補充」在選項裡的值：用物件而不是字串，任何真的寫法都不會等於它
const SUPPLEMENT = Object.freeze({ supplement: true })
const PROMPTS = {
  name: (tribe) => `${tribe}怎麼叫這條魚？`,
  food_category: (tribe) => `${tribe}把這條魚分在哪一類？`,
  processing_method: (tribe) => `${tribe}怎麼處理這條魚？`,
}
const primaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white'
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'

const index = ref(0)
// 這次看過結果的題目（答了或跳過）；editing＝正在改已答過的題目
const revealed = ref({})
const editing = ref(false)
const choice = ref(null)
const supplement = ref('')
const form = useForm({ question: '', value: '' })
const { waitSeconds, initialWaitSeconds } = useRateLimitCountdown()

const current = computed(() => props.questions[index.value])
const isLast = computed(() => index.value === props.questions.length - 1)
const tribeUrl = computed(() => `/island/tribes/${props.tribe}`)
const showingResult = computed(
  () => !editing.value && (revealed.value[current.value.key] || current.value.my_answer !== null)
)
const answerOptions = computed(() => {
  const options = current.value.options.map((value) => ({ value, label: value }))
  return current.value.key === 'name'
    ? [...options, { value: SUPPLEMENT, label: '都不對／補充' }]
    : options
})
const submitLabel = computed(() => {
  if (form.processing) return '送出中…'
  return waitSeconds.value > 0 ? `${waitSeconds.value} 秒後可以再試` : '送出'
})

const promptOf = (key) => PROMPTS[key](islandTribeLabel(props.tribe))

/** 顯示某一題的結果；送出的回應可能在換題後才到，所以一律指定題目，不用「目前這一題」 */
function reveal(key) {
  revealed.value = { ...revealed.value, [key]: true }
  if (key === current.value.key) {
    editing.value = false
  }
}

function edit() {
  choice.value = current.value.my_answer
  supplement.value = ''
  form.clearErrors()
  editing.value = true
}

function next() {
  if (form.processing) return
  index.value += 1
  choice.value = null
  supplement.value = ''
  form.clearErrors()
}

function submit() {
  if (waitSeconds.value > 0 || !choice.value) return
  const key = current.value.key
  form.question = key
  form.value = choice.value === SUPPLEMENT ? supplement.value : choice.value
  form.post(`/island/tribes/${props.tribe}/fish/${props.fish.id}/answers`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => reveal(key),
  })
}
</script>
