<template>
  <Head :title="`魚島：${islandTribeLabel(tribe)}`" />

  <IslandLayout>
    <template #header>
      <Link
        href="/island"
        data-testid="back-to-map"
        class="flex min-h-touch-primary items-center rounded-island bg-white px-3 text-elder-aux font-bold"
      >
        ← 地圖
      </Link>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <h1 class="px-1 text-elder-title font-extrabold">{{ islandTribeLabel(tribe) }}</h1>
    <p class="px-1 pb-3 text-elder-aux text-island-muted">
      {{ islandTribeLabel(tribe) }}的人怎麼說這些魚？
    </p>

    <div
      v-if="progress"
      data-testid="progress"
      class="mb-3 flex items-center gap-2 rounded-island bg-white px-4 py-2.5 text-elder-aux"
    >
      <b>{{ tribe }} 的魚</b>
      <div
        role="progressbar"
        :aria-valuenow="progress.with_result"
        aria-valuemin="0"
        :aria-valuemax="progress.total"
        :aria-label="`${tribe} 有結果的魚`"
        class="h-3 flex-1 overflow-hidden rounded-full bg-island-line"
      >
        <div
          class="h-full bg-island-land-line"
          :style="{ width: `${(progress.with_result / progress.total) * 100}%` }"
        />
      </div>
      <b>{{ progress.with_result }}/{{ progress.total }}</b>
    </div>

    <p v-if="empty_reason === 'no_fish'" class="rounded-island bg-white p-4 text-elder-body">
      目前沒有魚
    </p>
    <p v-else-if="empty_reason === 'no_more'" class="rounded-island bg-white p-4 text-elder-body">
      沒有更多了
    </p>

    <!-- 有結果的魚卡：點了開細節視窗；沒結果的魚卡：整張連到這個部落的作答頁 -->
    <ul v-else class="grid grid-cols-2 gap-3">
      <li
        v-for="(item, index) in fish"
        :key="item.id"
        data-testid="fish-card"
        class="overflow-hidden rounded-island bg-white"
      >
        <button
          v-if="item.results"
          :ref="(el) => (cardButtons[index] = el)"
          type="button"
          class="flex h-full w-full flex-col text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
          @click="openDetail(index)"
        >
          <img
            :src="item.image_url"
            alt=""
            loading="lazy"
            class="aspect-[4/3] w-full bg-island-line object-cover"
          />
          <div class="flex flex-1 flex-col p-3">
            <p
              class="text-elder-body font-bold"
              :class="{ 'text-island-muted': item.name === null }"
            >
              {{ item.name ?? '還沒有名字' }}
            </p>
            <div v-for="summary in cardSummaries(item)" :key="summary.key" class="mt-2">
              <p class="text-sm text-island-muted">{{ summary.label }}</p>
              <ResultBar :segments="summary.segments" />
              <p data-testid="result-label" class="text-elder-aux font-bold">
                {{ summary.text }}
              </p>
            </div>
          </div>
        </button>
        <Link
          v-else
          :href="`/island/tribes/${tribe}/fish/${item.id}`"
          class="flex h-full flex-col focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
        >
          <img
            :src="item.image_url"
            alt=""
            loading="lazy"
            class="aspect-[4/3] w-full bg-island-line object-cover"
          />
          <div class="flex flex-1 flex-col p-3">
            <p
              class="text-elder-body font-bold"
              :class="{ 'text-island-muted': item.name === null }"
            >
              {{ item.name ?? '還沒有名字' }}
            </p>

            <span
              class="mt-auto block rounded-island bg-island-accent px-2 py-2 text-center text-elder-aux font-bold text-white"
            >
              {{ item.name === null ? '幫它取名' : '搶先回答' }}
            </span>
          </div>
        </Link>
      </li>
    </ul>

    <nav v-if="page > 1 || has_more_pages" class="mt-4 flex gap-3" aria-label="換頁">
      <Link
        v-if="page > 1"
        :href="pageUrl(page - 1)"
        data-testid="previous-page"
        class="flex min-h-touch-primary flex-1 items-center justify-center rounded-island bg-white text-elder-body font-bold"
      >
        上一頁
      </Link>
      <Link
        v-if="has_more_pages"
        :href="pageUrl(page + 1)"
        data-testid="next-page"
        class="flex min-h-touch-primary flex-1 items-center justify-center rounded-island bg-island-accent text-elder-body font-bold text-white"
      >
        下一頁
      </Link>
    </nav>
    <ResultDetailDialog
      v-if="detailIndex !== null"
      :fish="fish[detailIndex]"
      :tribe="tribe"
      :tribes="tribes"
      :result-options="result_options"
      :unknown-processing-method="unknown_processing_method"
      @close="closeDetail"
    />
  </IslandLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'
import ResultBar from '@/Components/Island/ResultBar.vue'
import ResultDetailDialog from '@/Components/Island/ResultDetailDialog.vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandResultColor, islandTribeLabel } from '@/constants/island'

const CARD_QUESTIONS = [
  { key: 'food_category', label: '分類' },
  { key: 'processing_method', label: '食用' },
]

const props = defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
  fish: { type: Array, required: true },
  page: { type: Number, required: true },
  has_more_pages: { type: Boolean, required: true },
  empty_reason: { type: String, default: null },
  tribes: { type: Array, required: true },
  result_options: { type: Object, required: true },
  unknown_processing_method: { type: String, required: true },
  progress: { type: Object, default: null },
})

const detailIndex = ref(null)
const cardButtons = ref([])

/**
 * 魚卡上兩題的長條與標籤：只算所在部落，食用不算「我不知道」。
 * 標籤＝最多人選的選項＋人數，平手並列（例如「oyod、rahet 2」）。
 */
function cardSummaries(item) {
  return CARD_QUESTIONS.map(({ key, label }) => {
    const options = props.result_options[key]
    const choices = (item.results[key][props.tribe] ?? []).filter(
      (choice) => choice.value !== props.unknown_processing_method
    )
    const top = Math.max(...choices.map((choice) => choice.count))
    const leaders = choices.filter((choice) => choice.count === top).map((choice) => choice.value)
    return {
      key,
      label,
      segments: choices.map((choice) => ({
        ...choice,
        color: islandResultColor(options, choice.value, props.unknown_processing_method),
      })),
      text: `${leaders.join('、')} ${top}`,
    }
  })
}

const openDetail = (index) => (detailIndex.value = index)

// 關閉後焦點回到原本那張魚卡
async function closeDetail() {
  const index = detailIndex.value
  detailIndex.value = null
  await nextTick()
  cardButtons.value[index]?.focus()
}

const pageUrl = (page) => `/island/tribes/${props.tribe}?page=${page}`
</script>
