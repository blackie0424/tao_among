<template>
  <section class="border border-rule-strong bg-paper-raised p-4" data-testid="fish-responses">
    <h3 class="mb-3 pb-2 border-b border-rule font-serif text-elder-name font-bold text-elder-text">大家怎麼說</h3>

    <!-- 繼續上次做到一半的回答 -->
    <div v-if="step === 'resume'" data-testid="step-resume">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">{{ incomplete.tribe }}</p>
      <button type="button" class="w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white" @click="continueIncomplete">繼續 ›</button>
      <button type="button" :class="[bigButton, 'mt-3']" @click="startOver">再說一個部落</button>
    </div>

    <!-- 1 你們也叫它 X 嗎？ -->
    <div v-else-if="step === 'confirm'" data-testid="step-confirm">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">你們也叫它 {{ fishName }} 嗎？</p>
      <button type="button" class="w-full min-h-[4rem] bg-green-700 text-3xl text-white" aria-label="是" @click="agree">✔</button>
      <button type="button" class="mt-3 w-full min-h-[4rem] border-2 border-red-700 bg-white text-3xl text-red-700" aria-label="不是" @click="step = 'name'">✖</button>
    </div>

    <!-- 2 叫什麼（保留文字輸入） -->
    <div v-else-if="step === 'name'" data-testid="step-name">
      <button v-if="fishName" type="button" aria-label="返回" :class="linkButton" @click="step = 'confirm'">‹</button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">{{ fishName ? '叫什麼？' : '這條魚，你們叫什麼？' }}</p>
      <input v-model="typedName" type="text" class="w-full min-h-touch-secondary border border-rule-strong bg-white px-3 text-elder-body" aria-label="叫法" />
      <p class="mt-1 text-elder-aux text-elder-subtext">羅馬拼音、接近的中文字都可以</p>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
      <button type="button" :disabled="!typedName.trim()" class="mt-3 w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white disabled:opacity-60" @click="confirmTyped">OK</button>
      <button type="button" :class="linkButton" @click="skipName">略過 ›</button>
    </div>

    <!-- 3 哪個部落 -->
    <div v-else-if="step === 'tribe'" data-testid="step-tribe">
      <button type="button" aria-label="返回" :class="linkButton" @click="step = agreed ? 'confirm' : 'name'">‹</button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">哪個部落？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="tribe in options.tribes" :key="tribe" type="button" :disabled="form.processing" :class="bigButton" @click="chooseTribe(tribe)">
          {{ tribe }}<span v-if="answeredTribes.has(tribe)" aria-label="已回答"> ✓</span>
        </button>
      </div>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
    </div>

    <!-- 4 分類（可略過） -->
    <div v-else-if="step === 'category'" data-testid="step-category">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">{{ options.food_categories.slice(0, 2).join('？ ') }}？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="category in options.food_categories" :key="category" type="button" :class="[bigButton, category === previousCategory && 'border-brand font-bold']" @click="saveCategory(category)">{{ category }}</button>
      </div>
      <p v-if="previousCategory" class="mt-3 text-elder-aux text-elder-subtext" data-testid="previous-category">原本：{{ previousCategory }}</p>
      <p v-if="form.errors.food_category" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.food_category }}</p>
      <button type="button" :class="linkButton" @click="skipCategory">略過 ›</button>
    </div>

    <!-- 5 大家怎麼說：答完直接看，自己的選擇特別標出；下方可再說一個部落 -->
    <div v-else data-testid="step-others">
      <p v-if="justSubmitted" class="mb-2 text-elder-body font-bold text-green-700" data-testid="thanks">✔ 謝謝！</p>
      <button v-if="backStep" type="button" aria-label="返回" :class="linkButton" @click="step = backStep">‹</button>
      <ul class="mt-2 space-y-3">
        <li
          v-for="item in respondedTribes"
          :key="item.tribe"
          :class="['border bg-white p-3', mineOf(item) ? 'border-2 border-amber-600' : 'border-rule']"
          :data-mine="mineOf(item) ? 'true' : undefined"
          data-testid="response-tribe"
        >
          <span class="font-serif text-elder-body font-bold text-elder-text">{{ item.tribe }}</span>
          <span v-if="mineOf(item)" class="ml-2 text-elder-aux font-bold text-amber-700" aria-label="我選的">✔</span>
          <span v-if="item.has_variants" class="ml-2 text-elder-aux text-elder-subtext" aria-label="有不同說法">≠</span>
          <p class="mt-1 text-elder-body text-elder-text" data-testid="tribe-names">
            <template v-if="item.names.length">
              <template v-for="(n, i) in item.names" :key="n.name">
                <span v-if="i">、</span>
                <span :class="isMyName(item, n.name) && 'font-bold text-amber-700'" :data-mine="isMyName(item, n.name) ? 'true' : undefined">{{ n.name }}<template v-if="isMyName(item, n.name)"> ✔</template></span>
              </template>
            </template>
            <template v-else>—</template>
          </p>
          <ul v-if="categoryBars(item).length" class="mt-2 space-y-1" data-testid="tribe-categories">
            <li
              v-for="bar in categoryBars(item)"
              :key="bar.value"
              :class="['grid grid-cols-[4.5rem_1fr_1.5rem] items-center gap-2 text-elder-aux', isMyCategory(item, bar.value) ? 'font-bold text-amber-700' : 'text-elder-text']"
              :data-mine="isMyCategory(item, bar.value) ? 'true' : undefined"
            >
              <span>{{ bar.value }}<template v-if="isMyCategory(item, bar.value)"> ✔</template></span>
              <span class="block h-3 rounded-sm bg-rule" aria-hidden="true">
                <span :class="['block h-3 rounded-sm', isMyCategory(item, bar.value) ? 'bg-amber-600' : 'bg-brand']" :style="{ width: bar.width }" data-testid="bar-fill"></span>
              </span>
              <span class="text-right">{{ bar.count }}</span>
            </li>
          </ul>
          <p v-else class="mt-2 text-elder-aux text-elder-subtext">還沒有人選分類</p>
        </li>
      </ul>
      <button v-if="mine.length" type="button" :class="[bigButton, 'mt-4']" @click="startOver">再說一個部落</button>
    </div>

    <button v-if="respondedTribes.length && ['resume', 'confirm', 'name', 'tribe'].includes(step)" type="button" :class="linkButton" @click="openOthers">看大家怎麼說</button>
    <p class="mt-3 text-elder-aux text-elder-subtext" data-testid="responders">{{ responders }} 人回應</p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  fishId: { type: Number, required: true },
  options: { type: Object, required: true },
  summary: { type: Array, default: () => [] },
  mine: { type: Array, default: () => [] },
  responders: { type: Number, default: 0 },
})

const bigButton = 'w-full min-h-[3.5rem] border-2 border-rule-strong bg-white px-2 text-elder-body text-elder-text'
const linkButton = 'mt-3 min-h-touch-secondary text-elder-body text-brand underline'

const form = useForm({ tribe: '', name: '', food_category: '' })
const typedName = ref('')
const draftName = ref(null) // null＝還沒決定；''＝略過叫法；其他＝已決定的叫法
const agreed = ref(false)
const savedName = ref('')
const previousCategory = ref('')
const backStep = ref(null) // 從問題畫面點「看大家怎麼說」進來時，可以返回原本的問題
const justSubmitted = ref(false)

// 魚目前的名字；「我不知道」不列為選項，這時第一題直接問叫什麼
const fishName = computed(() => props.options.name_options[0] ?? null)
const answeredTribes = computed(() => new Set(props.mine.map((r) => r.tribe)))
const respondedTribes = computed(() => props.summary.filter((item) => item.has_responses))
// 做到一半：已經有叫法、但還沒選分類的回答
const incomplete = computed(() => props.mine.find((r) => r.name && !r.food_category) ?? null)

const firstQuestion = () => (fishName.value ? 'confirm' : 'name')
// 進來時：做到一半就提示繼續；答過的直接看大家怎麼說；沒答過就是第一題
const homeStep = () => (incomplete.value ? 'resume' : props.mine.length ? 'others' : firstQuestion())
const step = ref(homeStep())

// 自己的選擇（用來在結果中特別標出）；寫法比對與後端一致：去前後空白、只有英文字母不分大小寫
const asciiLower = (text) => text.trim().replace(/[A-Z]/g, (c) => c.toLowerCase())
const mineOf = (item) => props.mine.find((r) => r.tribe === item.tribe)
const isMyName = (item, name) => {
  const mineName = mineOf(item)?.name
  return Boolean(mineName) && asciiLower(mineName) === asciiLower(name)
}
const isMyCategory = (item, category) => mineOf(item)?.food_category === category

// 字數以 Unicode 字元計（去前後空白後），與後端一致
const codePointLength = (text) => Array.from(text).length

function openOthers() {
  backStep.value = step.value
  justSubmitted.value = false
  step.value = 'others'
}

function showResults() {
  backStep.value = null
  justSubmitted.value = true
  step.value = 'others'
}

function agree() {
  agreed.value = true
  draftName.value = fishName.value
  step.value = 'tribe'
}

function confirmTyped() {
  const name = typedName.value.trim()
  if (codePointLength(name) > props.options.name_max_length) {
    form.setError('name', `叫法最多 ${props.options.name_max_length} 字`)
    return
  }
  form.clearErrors()
  draftName.value = name
  step.value = 'tribe'
}

// 只填分類：叫法留空（若先前有叫法，選了分類送出時一併清空）
function skipName() {
  form.clearErrors()
  typedName.value = ''
  draftName.value = ''
  step.value = 'tribe'
}

// 選好部落：有叫法就先存（分類是下一步，另外送）；略過叫法就直接問分類
// 修改時先沿用原本的分類，使用者中途離開也不會遺失；要清掉分類得在分類那步按略過。
function chooseTribe(tribe) {
  form.clearErrors()
  form.tribe = tribe
  previousCategory.value = props.mine.find((r) => r.tribe === tribe)?.food_category ?? ''
  if (!draftName.value) {
    savedName.value = ''
    step.value = 'category'
    return
  }
  submit(draftName.value, previousCategory.value, () => {
    savedName.value = draftName.value
    step.value = 'category'
  })
}

function continueIncomplete() {
  form.clearErrors()
  form.tribe = incomplete.value.tribe
  savedName.value = incomplete.value.name
  previousCategory.value = ''
  step.value = 'category'
}

function startOver() {
  form.reset()
  form.clearErrors()
  typedName.value = ''
  draftName.value = null
  agreed.value = false
  justSubmitted.value = false
  backStep.value = null
  step.value = firstQuestion()
}

function saveCategory(category) {
  form.food_category = category
  submit(savedName.value, category, showResults)
}

// 略過分類＝不填分類：原本有分類就送出空的把它清掉；叫法也沒有的話兩項皆空，無法送出，提示至少填一項
function skipCategory() {
  if (!savedName.value) {
    form.setError('food_category', '至少要填一項')
    return
  }
  if (previousCategory.value) {
    submit(savedName.value, '', () => {
      previousCategory.value = ''
      showResults()
    })
    return
  }
  showResults()
}

function submit(name, foodCategory, onSuccess) {
  form
    .transform((data) => ({ ...data, name, food_category: foodCategory }))
    .post(`/fish/${props.fishId}/responses`, { preserveScroll: true, onSuccess })
}

// 分類長條圖：同一個部落內比較，最多人的選項填滿，其餘依比例；沒人選的選項不畫
const categoryBars = (item) => {
  const picked = item.categories.filter((c) => c.count > 0)
  const max = Math.max(...picked.map((c) => c.count))
  return picked.map((c) => ({ ...c, width: `${Math.round((c.count / max) * 100)}%` }))
}
</script>
