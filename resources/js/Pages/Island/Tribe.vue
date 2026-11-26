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

    <h1 class="px-1 pb-3 text-elder-title font-extrabold">{{ islandTribeLabel(tribe) }}的家屋</h1>

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

    <!-- 家屋的院子：四個地點 -->
    <ul
      data-testid="spot-cards"
      class="grid grid-cols-2 gap-3 rounded-island border-4 border-island-land-line bg-island-land p-3"
    >
      <li
        v-for="spot in spots"
        :key="spot.code"
        data-testid="spot-card"
        class="flex flex-col items-center rounded-island bg-white p-3 text-center"
      >
        <!-- 點卡片本身＝跟這個地點的 NPC 聊天 -->
        <Link
          :href="`/island/tribes/${tribe}/spots/${spot.code}/talk?n=1`"
          data-testid="spot-talk-link"
          class="flex w-full flex-col items-center rounded-island focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-island-ink/40"
        >
          <span
            class="mb-2 block h-12 w-12 rounded-full border-4 border-island-ink"
            :style="{ backgroundColor: islandSpotColor(spot.code) }"
            aria-hidden="true"
          />
          <p data-testid="spot-name" class="text-elder-body font-extrabold">{{ spot.name }}</p>
          <p class="text-elder-aux">{{ spot.npc }}</p>
          <p data-testid="spot-progress" class="text-elder-aux text-island-muted">
            {{ spot.total === 0 ? '這裡還沒有魚' : `${spot.with_result}/${spot.total} 有結果` }}
          </p>
        </Link>
        <Link
          :href="`/island/tribes/${tribe}/spots/${spot.code}`"
          data-testid="spot-list-link"
          :aria-label="`看${spot.name}的清單`"
          class="mt-2 flex min-h-touch-secondary w-full items-center justify-center rounded-island border-2 border-island-line text-elder-aux font-bold"
        >
          看清單
        </Link>
      </li>
    </ul>
  </IslandLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandSpotColor, islandTribeLabel } from '@/constants/island'

defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
  spots: { type: Array, required: true },
  progress: { type: Object, default: null },
})
</script>
