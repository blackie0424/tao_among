<template>
  <div class="space-y-6 w-full mb-6 md:mb-10" data-testid="fish-responses">
    <!-- 各部落的說法 -->
    <section class="border border-rule-strong bg-paper-raised p-4">
      <h3 class="mb-3 pb-2 border-b border-rule font-serif text-elder-name font-bold text-elder-text">各部落的說法</h3>
      <ul class="divide-y divide-rule">
        <li v-for="item in summary" :key="item.tribe" class="py-3" data-testid="response-tribe">
          <div class="flex flex-wrap items-baseline gap-2">
            <span class="font-serif text-elder-body font-bold text-elder-text">{{ item.tribe }}</span>
            <span v-if="item.has_variants" class="rounded-full border border-rule-strong px-2 text-sm text-elder-subtext">有不同說法</span>
          </div>
          <p v-if="!item.has_responses" class="mt-1 text-elder-body text-elder-subtext">還沒有人</p>
          <template v-else>
            <p v-if="!item.names.length" class="mt-1 text-elder-body text-elder-subtext">—</p>
            <div v-for="n in item.names" :key="n.name" class="mt-2 flex items-center gap-3">
              <span class="w-32 shrink-0 break-words text-elder-body text-elder-text">{{ n.name }}</span>
              <div class="h-3 flex-1 rounded-full bg-rule">
                <div class="h-3 rounded-full bg-brand" :style="{ width: barWidth(item, n) }"></div>
              </div>
              <span class="w-8 text-right text-elder-body text-elder-text">{{ n.count }}</span>
            </div>
            <p v-if="categoryText(item)" class="mt-2 text-elder-body text-elder-subtext">{{ categoryText(item) }}</p>
          </template>
        </li>
      </ul>
      <p class="mt-3 text-sm text-elder-subtext">數字是說這個叫法的人數，不是部落的居民人數。</p>
    </section>

    <!-- 全站進度 -->
    <section class="border border-rule-strong bg-paper-raised p-4" data-testid="response-progress">
      <h3 class="mb-2 font-serif text-elder-name font-bold text-elder-text">大家一起補滿空格</h3>
      <p class="text-elder-body text-elder-text">
        <template v-if="progress.total > 0">{{ progress.answered }}／{{ progress.total }} 條魚至少有一個部落補了說法</template>
        <template v-else>—</template>
      </p>
    </section>

    <!-- 我也來說 -->
    <form class="border border-rule-strong bg-paper-raised p-4 space-y-5" data-testid="response-form" @submit.prevent="submit">
      <h3 class="font-serif text-elder-name font-bold text-elder-text">這是哪個部落的說法？</h3>
      <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <button
          v-for="tribe in options.tribes"
          :key="tribe"
          type="button"
          :class="choiceClass(form.tribe === tribe)"
          :aria-pressed="form.tribe === tribe"
          @click="selectTribe(tribe)"
        >{{ tribe }}</button>
      </div>
      <p v-if="form.errors.tribe" class="text-elder-body text-red-700">{{ form.errors.tribe }}</p>

      <fieldset class="space-y-3">
        <legend class="text-elder-body font-bold text-elder-text">叫這條魚</legend>
        <button
          v-for="option in options.name_options"
          :key="option"
          type="button"
          :class="choiceClass(nameChoice === option)"
          :aria-pressed="nameChoice === option"
          @click="nameChoice = nameChoice === option ? '' : option"
        >{{ option }}</button>
        <button type="button" :class="choiceClass(nameChoice === CUSTOM)" :aria-pressed="nameChoice === CUSTOM" @click="nameChoice = CUSTOM">自己打</button>
        <input
          v-if="nameChoice === CUSTOM"
          v-model="customName"
          type="text"
          :maxlength="options.name_max_length"
          class="w-full min-h-touch-secondary border border-rule-strong bg-white px-3 text-elder-body"
          aria-label="叫法"
        />
        <p v-if="form.errors.name" class="text-elder-body text-red-700">{{ form.errors.name }}</p>
      </fieldset>

      <fieldset class="space-y-3">
        <legend class="text-elder-body font-bold text-elder-text">分類</legend>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
          <button
            v-for="category in options.food_categories"
            :key="category"
            type="button"
            :class="choiceClass(form.food_category === category)"
            :aria-pressed="form.food_category === category"
            @click="form.food_category = form.food_category === category ? '' : category"
          >{{ category }}</button>
        </div>
        <p v-if="form.errors.food_category" class="text-elder-body text-red-700">{{ form.errors.food_category }}</p>
      </fieldset>

      <button type="submit" :disabled="form.processing" class="w-full min-h-touch-secondary bg-brand text-white text-elder-body font-bold disabled:opacity-60">送出</button>
      <p v-if="form.recentlySuccessful" role="status" class="text-elder-body text-brand">已收到，謝謝！</p>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

const CUSTOM = '__custom__'

const props = defineProps({
  fishId: { type: Number, required: true },
  options: { type: Object, required: true },
  summary: { type: Array, default: () => [] },
  mine: { type: Array, default: () => [] },
  progress: { type: Object, default: () => ({ answered: 0, total: 0 }) },
})

const form = useForm({ tribe: '', name: '', food_category: '' })
const nameChoice = ref('')
const customName = ref('')

const choiceClass = (selected) => [
  'min-h-touch-secondary w-full border px-3 text-elder-body',
  selected ? 'border-brand bg-brand/10 font-bold text-brand' : 'border-rule-strong bg-white text-elder-text',
]

const barWidth = (item, name) => `${Math.round((name.count / item.names[0].count) * 100)}%`

const categoryText = (item) =>
  item.categories.filter((c) => c.count > 0).map((c) => `${c.value} ${c.count}`).join('、')

// 選到部落時，帶入自己先前對該部落的回答
function selectTribe(tribe) {
  form.tribe = tribe
  const previous = props.mine.find((r) => r.tribe === tribe)
  form.food_category = previous?.food_category ?? ''
  const name = previous?.name ?? ''
  if (name && props.options.name_options.includes(name)) {
    nameChoice.value = name
  } else {
    nameChoice.value = name ? CUSTOM : ''
    customName.value = name
  }
}

function submit() {
  form
    .transform((data) => ({
      ...data,
      name: nameChoice.value === CUSTOM ? customName.value : nameChoice.value,
    }))
    .post(`/fish/${props.fishId}/responses`, { preserveScroll: true })
}
</script>
