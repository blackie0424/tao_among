<template>
  <Head :title="`魚島：${islandTribeLabel(tribe)} ${spot.name}`" />

  <IslandLayout>
    <template #header>
      <Link
        :href="`/island/tribes/${tribe}`"
        data-testid="back-to-house"
        class="flex min-h-touch-primary items-center rounded-island bg-white px-3 text-elder-aux font-bold"
      >
        ← 家屋
      </Link>
      <p class="rounded-island bg-white px-3 py-1.5 text-elder-aux">{{ character.name }}</p>
    </template>

    <h1 class="px-1 text-elder-title font-extrabold">{{ spot.name }}的魚</h1>
    <p class="px-1 pb-3 text-elder-aux text-island-muted">
      {{ islandTribeLabel(tribe) }}的人怎麼說這些魚？
    </p>

    <FishCardList
      :tribe="tribe"
      :fish="fish"
      :page="page"
      :has-more-pages="has_more_pages"
      :empty-reason="empty_reason"
      :tribes="tribes"
      :result-options="result_options"
      :unknown-processing-method="unknown_processing_method"
      :base-path="`/island/tribes/${tribe}/spots/${spot.code}`"
      no-fish-text="這裡還沒有魚"
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
  spot: { type: Object, required: true },
  fish: { type: Array, required: true },
  page: { type: Number, required: true },
  has_more_pages: { type: Boolean, required: true },
  empty_reason: { type: String, default: null },
  tribes: { type: Array, required: true },
  result_options: { type: Object, required: true },
  unknown_processing_method: { type: String, required: true },
})
</script>
