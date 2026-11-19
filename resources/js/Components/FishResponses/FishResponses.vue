<template>
  <section class="border border-rule-strong bg-paper-raised p-4" data-testid="fish-responses">
    <!-- 1 哪個部落 -->
    <div v-if="step === 'tribe'" data-testid="step-tribe">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">哪個部落？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="tribe in options.tribes" :key="tribe" type="button" :class="bigButton" @click="chooseTribe(tribe)">
          {{ tribe }}<span v-if="answeredTribes.has(tribe)" aria-label="已回答"> ✓</span>
        </button>
      </div>
      <button v-if="mine.length" type="button" :class="linkButton" @click="step = 'done'">‹</button>
    </div>

    <!-- 2 叫不叫目前的名字 -->
    <div v-else-if="step === 'confirm'" data-testid="step-confirm">
      <button type="button" :class="linkButton" @click="step = 'tribe'">‹ {{ form.tribe }}</button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">叫它 {{ fishName }} 嗎？</p>
      <button type="button" class="w-full min-h-[4rem] bg-green-700 text-3xl text-white" aria-label="是" @click="saveName(fishName)">✔</button>
      <button type="button" class="mt-3 w-full min-h-[4rem] border-2 border-red-700 bg-white text-3xl text-red-700" aria-label="不是" @click="startTyping">✖</button>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
    </div>

    <!-- 3 叫什麼（保留文字輸入） -->
    <div v-else-if="step === 'name'" data-testid="step-name">
      <button type="button" :class="linkButton" @click="step = fishName ? 'confirm' : 'tribe'">‹ {{ form.tribe }}</button>
      <p class="my-3 font-serif text-elder-name font-bold text-elder-text">叫什麼？</p>
      <input v-model="typedName" type="text" class="w-full min-h-touch-secondary border border-rule-strong bg-white px-3 text-elder-body" aria-label="叫法" />
      <p class="mt-1 text-elder-aux text-elder-subtext">羅馬拼音、接近的中文字都可以</p>
      <p v-if="form.errors.name" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.name }}</p>
      <button type="button" :disabled="!typedName.trim() || form.processing" class="mt-3 w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white disabled:opacity-60" @click="saveName(typedName)">OK</button>
    </div>

    <!-- 4 分類（可略過） -->
    <div v-else-if="step === 'category'" data-testid="step-category">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">{{ options.food_categories.slice(0, 2).join('？ ') }}？</p>
      <div class="grid grid-cols-2 gap-3">
        <button v-for="category in options.food_categories" :key="category" type="button" :class="bigButton" @click="saveCategory(category)">{{ category }}</button>
      </div>
      <p v-if="form.errors.food_category" role="alert" class="mt-2 text-elder-body text-red-700">{{ form.errors.food_category }}</p>
      <button type="button" :class="linkButton" @click="step = 'done'">略過 ›</button>
    </div>

    <!-- 5 完成 -->
    <div v-else-if="step === 'done'" data-testid="step-done">
      <p class="mb-3 font-serif text-elder-name font-bold text-elder-text">✔ 謝謝！</p>
      <button type="button" :class="bigButton" @click="startOver">再說一個部落</button>
      <button type="button" class="mt-3 w-full min-h-touch-secondary bg-brand text-elder-body font-bold text-white" @click="step = 'others'">看大家怎麼說</button>
    </div>

    <!-- 6 大家怎麼說（只列有回答的部落） -->
    <div v-else data-testid="step-others">
      <button type="button" :class="linkButton" @click="step = mine.length ? 'done' : 'tribe'">‹ 大家怎麼說</button>
      <ul class="mt-2 divide-y divide-rule">
        <li v-for="item in respondedTribes" :key="item.tribe" class="py-2" data-testid="response-tribe">
          <span class="font-serif text-elder-body font-bold text-elder-text">{{ item.tribe }}</span>
          <span v-if="item.has_variants" class="ml-2 text-elder-aux text-elder-subtext" aria-label="有不同說法">≠</span>
          <p class="text-elder-body text-elder-text">{{ nameText(item) }}</p>
          <p v-if="categoryText(item)" class="text-elder-aux text-elder-subtext">{{ categoryText(item) }}</p>
        </li>
      </ul>
      <p v-if="progress.total > 0" class="mt-3 text-elder-aux text-elder-subtext" data-testid="response-progress">{{ progress.answered }}／{{ progress.total }}</p>
    </div>
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
  progress: { type: Object, default: () => ({ answered: 0, total: 0 }) },
})

const bigButton = 'w-full min-h-[3.5rem] border-2 border-rule-strong bg-white px-2 text-elder-body text-elder-text'
const linkButton = 'mt-3 min-h-touch-secondary text-elder-body text-brand underline'

const form = useForm({ tribe: '', name: '', food_category: '' })
const step = ref(props.mine.length ? 'done' : 'tribe')
const typedName = ref('')
const savedName = ref('')

// 魚目前的名字；「我不知道」不列為選項，這時略過「叫不叫這個名字」
const fishName = computed(() => props.options.name_options[0] ?? null)
const answeredTribes = computed(() => new Set(props.mine.map((r) => r.tribe)))
const respondedTribes = computed(() => props.summary.filter((item) => item.has_responses))

// 字數以 Unicode 字元計（去前後空白後），與後端一致
const codePointLength = (text) => Array.from(text).length

function chooseTribe(tribe) {
  const previous = props.mine.find((r) => r.tribe === tribe)
  form.reset()
  form.clearErrors()
  form.tribe = tribe
  form.food_category = previous?.food_category ?? ''
  typedName.value = previous?.name ?? ''
  step.value = fishName.value ? 'confirm' : 'name'
}

function startTyping() {
  step.value = 'name'
}

function startOver() {
  form.reset()
  form.clearErrors()
  step.value = 'tribe'
}

// 叫法一確定就先送出；分類是下一步，另外送（略過也不影響已存的叫法）
function saveName(rawName) {
  const name = rawName.trim()
  if (codePointLength(name) > props.options.name_max_length) {
    form.setError('name', `叫法最多 ${props.options.name_max_length} 字`)
    return
  }
  submit(name, form.food_category, () => {
    savedName.value = name
    step.value = 'category'
  })
}

function saveCategory(category) {
  form.food_category = category
  submit(savedName.value, category, () => {
    step.value = 'done'
  })
}

function submit(name, foodCategory, onSuccess) {
  form
    .transform((data) => ({ ...data, name, food_category: foodCategory }))
    .post(`/fish/${props.fishId}/responses`, { preserveScroll: true, onSuccess })
}

const nameText = (item) => (item.names.length ? item.names.map((n) => `${n.name} ${n.count}`).join('、') : '—')

const categoryText = (item) =>
  item.categories
    .filter((c) => c.count > 0)
    .map((c) => `${c.value} ${c.count}`)
    .join('、')
</script>
