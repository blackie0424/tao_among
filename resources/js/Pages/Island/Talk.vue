<template>
  <Head :title="`魚島：${islandTribeLabel(tribe)} ${spot.name}`" />

  <IslandLayout>
    <template #header>
      <Link
        :href="houseUrl"
        data-testid="back-to-house"
        class="flex min-h-touch-primary items-center rounded-island bg-white px-3 text-elder-aux font-bold"
      >
        ← 家屋
      </Link>
      <p v-if="fish" data-testid="talk-count" class="text-elder-aux font-bold">
        第 {{ n }}／{{ total }} 條
      </p>
      <Link
        :href="listUrl"
        data-testid="talk-list-link"
        class="flex min-h-touch-primary items-center rounded-island bg-white px-3 text-elder-aux font-bold"
      >
        看清單
      </Link>
    </template>

    <section class="rounded-island bg-white p-4">
      <p class="flex items-center gap-2 text-elder-body font-extrabold">
        <span
          class="inline-block h-10 w-10 shrink-0 rounded-full border-4 border-island-ink"
          :style="{ backgroundColor: islandSpotColor(spot.code) }"
          aria-hidden="true"
        />
        <span data-testid="npc-name">{{ spot.name }}的{{ spot.npc }}</span>
      </p>

      <img
        v-if="fish"
        :src="fish.image_url"
        :alt="fish.name ?? '還沒有名字的魚'"
        width="400"
        height="300"
        class="my-3 aspect-[4/3] w-full rounded-island bg-island-line object-contain"
      />

      <p data-testid="speech" class="mt-3 rounded-island bg-island-sea/40 p-3 text-elder-body">
        {{ speech }}
      </p>

      <div class="mt-4 flex flex-wrap gap-2">
        <button
          v-if="fish?.results"
          ref="whoSaidButton"
          type="button"
          data-testid="who-said"
          :class="secondaryButton"
          @click="detailOpen = true"
        >
          誰說的？
        </button>
        <Link
          v-if="fish?.ask"
          :href="`/island/tribes/${tribe}/fish/${fish.id}?question=${fish.ask}`"
          data-testid="answer-here"
          :class="primaryButton"
        >
          我來說
        </Link>
        <Link
          v-if="fish && !is_last"
          :href="`${talkUrl}?n=${n + 1}`"
          data-testid="next-fish"
          :class="fish.ask ? secondaryButton : primaryButton"
        >
          下一條 →
        </Link>
        <Link v-else :href="houseUrl" data-testid="finish-to-house" :class="secondaryButton">
          回到家屋
        </Link>
      </div>
    </section>

    <ResultDetailDialog
      v-if="detailOpen"
      :fish="fish"
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
import { computed, nextTick, ref } from 'vue'
import ResultDetailDialog from '@/Components/Island/ResultDetailDialog.vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandSpotColor, islandTribeLabel } from '@/constants/island'

const props = defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
  spot: { type: Object, required: true },
  n: { type: Number, required: true },
  total: { type: Number, required: true },
  is_last: { type: Boolean, required: true },
  fish: { type: Object, default: null },
  speech: { type: String, required: true },
  tribes: { type: Array, required: true },
  result_options: { type: Object, required: true },
  unknown_processing_method: { type: String, required: true },
})

const primaryButton =
  'flex min-h-touch-primary flex-1 items-center justify-center rounded-island bg-island-accent px-4 text-elder-body font-bold text-white'
const secondaryButton =
  'flex min-h-touch-primary flex-1 items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'

const houseUrl = computed(() => `/island/tribes/${props.tribe}`)
const listUrl = computed(() => `/island/tribes/${props.tribe}/spots/${props.spot.code}`)
const talkUrl = computed(() => `${listUrl.value}/talk`)

const detailOpen = ref(false)
const whoSaidButton = ref(null)

// 關閉後焦點回到「誰說的？」
async function closeDetail() {
  detailOpen.value = false
  await nextTick()
  whoSaidButton.value?.focus()
}
</script>
