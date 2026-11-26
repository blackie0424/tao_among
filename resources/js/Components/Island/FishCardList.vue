<template>
  <!-- 魚卡清單（部落頁、地點清單共用）：空清單文字、魚卡、換頁與細節視窗 -->
  <div>
    <p v-if="emptyReason === 'no_fish'" class="rounded-island bg-white p-4 text-elder-body">
      {{ noFishText }}
    </p>
    <p v-else-if="emptyReason === 'no_more'" class="rounded-island bg-white p-4 text-elder-body">
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
          class="flex h-full w-full flex-col text-left transition-colors hover:bg-island-sea/30 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
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
          :href="answerUrl(item)"
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

    <nav v-if="page > 1 || hasMorePages" class="mt-4 flex gap-3" aria-label="換頁">
      <Link
        v-if="page > 1"
        :href="pageUrl(page - 1)"
        data-testid="previous-page"
        class="flex min-h-touch-primary flex-1 items-center justify-center rounded-island bg-white text-elder-body font-bold"
      >
        上一頁
      </Link>
      <Link
        v-if="hasMorePages"
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
      :result-options="resultOptions"
      :unknown-processing-method="unknownProcessingMethod"
      @close="closeDetail"
    />
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { nextTick, ref } from 'vue'
import ResultBar from '@/Components/Island/ResultBar.vue'
import ResultDetailDialog from '@/Components/Island/ResultDetailDialog.vue'
import { islandResultColor } from '@/constants/island'

const CARD_QUESTIONS = [
  { key: 'food_category', label: '分類' },
  { key: 'processing_method', label: '食用' },
]

const props = defineProps({
  tribe: { type: String, required: true },
  fish: { type: Array, required: true },
  page: { type: Number, required: true },
  hasMorePages: { type: Boolean, required: true },
  emptyReason: { type: String, default: null },
  tribes: { type: Array, required: true },
  resultOptions: { type: Object, required: true },
  unknownProcessingMethod: { type: String, required: true },
  /** 換頁網址的路徑（不含 ?page=） */
  basePath: { type: String, required: true },
  /** 沒有任何魚時的文字 */
  noFishText: { type: String, required: true },
})
const detailIndex = ref(null)
const cardButtons = ref([])

/**
 * 魚卡上兩題的長條與標籤：只算所在部落，食用不算「我不知道」。
 * 標籤＝最多人選的選項＋人數，平手並列（例如「oyod、rahet 2」）。
 */
function cardSummaries(item) {
  return CARD_QUESTIONS.map(({ key, label }) => {
    const options = props.resultOptions[key]
    const choices = (item.results[key][props.tribe] ?? []).filter(
      (choice) => choice.value !== props.unknownProcessingMethod
    )
    const top = Math.max(...choices.map((choice) => choice.count))
    const leaders = choices.filter((choice) => choice.count === top).map((choice) => choice.value)
    return {
      key,
      label,
      segments: choices.map((choice) => ({
        ...choice,
        color: islandResultColor(options, choice.value, props.unknownProcessingMethod),
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

// 沒有結果的魚卡：到作答頁「要問的那一題」（#189，後端算好放在 ask）
const answerUrl = (item) =>
  `/island/tribes/${props.tribe}/fish/${item.id}` + (item.ask ? `?question=${item.ask}` : '')

const pageUrl = (page) => `${props.basePath}?page=${page}`
</script>
