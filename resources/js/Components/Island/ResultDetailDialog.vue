<template>
  <div
    class="fixed inset-0 z-50 flex items-end justify-center bg-island-ink/60 p-3 sm:items-center"
  >
    <div
      ref="panel"
      role="dialog"
      aria-modal="true"
      :aria-label="`${title}：大家怎麼說`"
      class="max-h-full w-full max-w-md overflow-y-auto rounded-island bg-white p-4 text-island-ink"
    >
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-elder-title font-extrabold">{{ title }}</h2>
        <button
          ref="closeButton"
          type="button"
          data-testid="close-detail"
          class="min-h-touch-secondary rounded-island border-2 border-island-line px-4 text-elder-body font-bold"
          @click="emit('close')"
        >
          關閉
        </button>
      </div>

      <div class="my-3 flex gap-2">
        <button
          v-for="question in QUESTIONS"
          :key="question.key"
          type="button"
          :data-testid="`tab-${question.key}`"
          :aria-pressed="String(activeKey === question.key)"
          class="min-h-touch-secondary rounded-island px-4 text-elder-aux font-bold"
          :class="
            activeKey === question.key
              ? 'bg-island-ink text-white'
              : 'border-2 border-island-line bg-white'
          "
          @click="activeKey = question.key"
        >
          {{ question.label }}
        </button>
      </div>

      <ul class="mb-3 flex flex-wrap gap-x-3 gap-y-1 text-elder-aux">
        <li
          v-for="item in legend"
          :key="item.value"
          data-testid="legend-item"
          class="flex items-center gap-1"
        >
          <span class="inline-block h-3 w-3 rounded" :style="{ backgroundColor: item.color }" />
          {{ item.value }}
        </li>
      </ul>

      <ul class="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2 text-elder-aux">
        <li
          v-for="row in rows"
          :key="row.key"
          data-testid="detail-row"
          :data-current="String(row.current)"
          class="contents"
        >
          <span
            data-testid="detail-row-label"
            :class="row.current ? 'font-extrabold text-island-accent' : ''"
          >
            {{ row.label }} {{ row.total }}
          </span>
          <ResultBar :segments="row.segments" :width="row.width" height-class="h-4" />
        </li>
      </ul>

      <Link
        :href="`/island/tribes/${tribe}/fish/${fish.id}`"
        data-testid="answer-link"
        class="mt-4 flex min-h-touch-primary items-center justify-center rounded-island bg-island-accent text-elder-body font-extrabold text-white"
      >
        我也來說
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import ResultBar from '@/Components/Island/ResultBar.vue'
import { islandResultColor } from '@/constants/island'

const QUESTIONS = [
  { key: 'food_category', label: '分類' },
  { key: 'processing_method', label: '食用' },
]
const ISLAND_ROW_LABEL = '全島'

const props = defineProps({
  fish: { type: Object, required: true },
  tribe: { type: String, required: true },
  tribes: { type: Array, required: true },
  resultOptions: { type: Object, required: true },
  unknownProcessingMethod: { type: String, required: true },
})
const emit = defineEmits(['close'])

const activeKey = ref(QUESTIONS[0].key)
const panel = ref(null)
const closeButton = ref(null)

const title = computed(() => props.fish.name ?? '還沒有名字')
const byTribe = computed(() => props.fish.results[activeKey.value])
const options = computed(() => props.resultOptions[activeKey.value])

const withColors = (choices) =>
  choices.map((choice) => ({
    ...choice,
    color: islandResultColor(options.value, choice.value, props.unknownProcessingMethod),
  }))
const totalOf = (choices) => choices.reduce((sum, choice) => sum + choice.count, 0)

// 全島＝各部落合計，選項依設定順序
const islandChoices = computed(() =>
  options.value
    .map((value) => ({
      value,
      count: Object.values(byTribe.value).reduce(
        (sum, choices) => sum + (choices.find((c) => c.value === value)?.count ?? 0),
        0
      ),
    }))
    .filter((choice) => choice.count > 0)
)
const islandTotal = computed(() => totalOf(islandChoices.value))

const legend = computed(() => withColors(islandChoices.value))

const rowOf = (key, label, choices, current = false) => {
  const total = totalOf(choices)
  return {
    key,
    label,
    total,
    current,
    segments: withColors(choices),
    width: `${(total / islandTotal.value) * 100}%`,
  }
}

// 所在部落第一、全島第二，其他部落依人數多到少（同人數依部落清單順序）；這題沒人答的其他部落不列
const rows = computed(() => {
  const others = props.tribes
    .filter((tribe) => tribe !== props.tribe && byTribe.value[tribe])
    .map((tribe) => rowOf(tribe, tribe, byTribe.value[tribe]))
    .sort((a, b) => b.total - a.total)
  const current = byTribe.value[props.tribe] ?? []
  return [
    rowOf(props.tribe, props.tribe, current, true),
    rowOf(ISLAND_ROW_LABEL, ISLAND_ROW_LABEL, islandChoices.value),
    ...others,
  ]
})

// Esc 關閉；Tab 在視窗內循環（焦點不跑到後面的頁面）
function onKeydown(event) {
  if (event.key === 'Escape') {
    emit('close')
  } else if (event.key === 'Tab') {
    trapTab(event)
  }
}

function trapTab(event) {
  const focusable = [...panel.value.querySelectorAll('button, a[href]')]
  const index = focusable.indexOf(document.activeElement)
  const next = event.shiftKey ? index - 1 : index + 1
  event.preventDefault()
  focusable[(next + focusable.length) % focusable.length].focus()
}

onMounted(async () => {
  document.addEventListener('keydown', onKeydown)
  await nextTick()
  closeButton.value?.focus()
})
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>
