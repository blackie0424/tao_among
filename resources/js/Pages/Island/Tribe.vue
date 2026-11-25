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

    <p v-if="empty_reason === 'no_fish'" class="rounded-island bg-white p-4 text-elder-body">
      目前沒有魚
    </p>
    <p v-else-if="empty_reason === 'no_more'" class="rounded-island bg-white p-4 text-elder-body">
      沒有更多了
    </p>

    <!-- 魚卡與「搶先回答／幫它取名」先不可點，作答頁在 #172；大家的結果在 #183 -->
    <ul v-else class="grid grid-cols-2 gap-3">
      <li
        v-for="item in fish"
        :key="item.id"
        data-testid="fish-card"
        class="flex flex-col overflow-hidden rounded-island bg-white"
      >
        <img
          :src="item.image_url"
          alt=""
          loading="lazy"
          class="aspect-[4/3] w-full bg-island-line object-cover"
        />
        <div class="flex flex-1 flex-col p-3">
          <p class="text-elder-body font-bold" :class="{ 'text-island-muted': item.name === null }">
            {{ item.name ?? '還沒有名字' }}
          </p>

          <span
            class="mt-auto block rounded-island bg-island-accent px-2 py-2 text-center text-elder-aux font-bold text-white"
          >
            {{ item.name === null ? '幫它取名' : '搶先回答' }}
          </span>
        </div>
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
  </IslandLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import IslandLayout from '@/Layouts/IslandLayout.vue'
import { islandTribeLabel } from '@/constants/island'

const props = defineProps({
  character: { type: Object, required: true },
  tribe: { type: String, required: true },
  fish: { type: Array, required: true },
  page: { type: Number, required: true },
  has_more_pages: { type: Boolean, required: true },
  empty_reason: { type: String, default: null },
})

const pageUrl = (page) => `/island/tribes/${props.tribe}?page=${page}`
</script>
