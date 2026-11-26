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

    <FishCardList
      :tribe="tribe"
      :fish="fish"
      :page="page"
      :has-more-pages="has_more_pages"
      :empty-reason="empty_reason"
      :tribes="tribes"
      :result-options="result_options"
      :unknown-processing-method="unknown_processing_method"
      :base-path="`/island/tribes/${tribe}`"
      no-fish-text="目前沒有魚"
    />
  </IslandLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import FishCardList from '@/Components/Island/FishCardList.vue'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

defineProps({
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
</script>
