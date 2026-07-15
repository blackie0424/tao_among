<template>
  <Head title="田調工作區 | among no tao" />

  <FishAppLayout pageTitle="田調工作區" :showHeader="true">
    <div class="space-y-4">

      <!-- 頂部工具列：新增按鈕 + 筆數選擇器 -->
      <div class="flex items-center gap-3">
        <Link
          href="/fish/batch-create"
          class="flex items-center justify-center gap-2 px-5 min-h-touch-primary rounded-xl bg-teal-600 text-white text-elder-name font-bold hover:bg-teal-700 transition shadow shrink-0"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          新增魚種
        </Link>

        <div class="flex items-center gap-2 ml-auto">
          <span class="text-elder-aux text-gray-500 shrink-0">每欄顯示</span>
          <select
            data-testid="limit-selector"
            :value="limit"
            class="border border-gray-200 rounded-lg px-3 py-1.5 text-elder-aux text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300"
            @change="onLimitChange"
          >
            <option v-for="opt in LIMIT_OPTIONS" :key="opt" :value="opt">{{ opt }} 筆</option>
          </select>
        </div>
      </div>

      <!-- 三欄 Grid（桌機） / 單欄（手機） -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- 待補發音 -->
        <section>
          <h2 class="text-elder-body font-bold text-gray-700 mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15.536 8.464a5 5 0 010 7.072M12 6a7 7 0 010 12M9 16.121A3 3 0 1112.5 12H9a3 3 0 00-3.621 2.879" />
            </svg>
            待補發音
            <span class="text-elder-aux text-gray-400 font-normal">（{{ needAudio.length }} 筆）</span>
          </h2>
          <div v-if="needAudio.length" class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <Link
              v-for="fish in needAudio"
              :key="fish.id"
              :href="`/fish/${fish.id}?tab=audio`"
              class="flex items-center gap-3 px-4 min-h-touch-primary hover:bg-blue-50 transition"
            >
              <img
                v-if="fish.image_url"
                :src="fish.image_url"
                :alt="fish.name"
                class="w-10 h-10 object-cover rounded-lg shrink-0"
              />
              <div v-else class="w-10 h-10 bg-gray-100 rounded-lg shrink-0" />
              <span class="text-elder-body text-elder-text flex-1 truncate">{{ fish.name }}</span>
              <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
          <p v-else class="text-elder-body text-gray-400 px-1">全部已有發音 🎉</p>
        </section>

        <!-- 待補照片 -->
        <section>
          <h2 class="text-elder-body font-bold text-gray-700 mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            待補照片
            <span class="text-elder-aux text-gray-400 font-normal">（{{ needPhoto.length }} 筆）</span>
          </h2>
          <div v-if="needPhoto.length" class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <Link
              v-for="fish in needPhoto"
              :key="fish.id"
              :href="`/fish/${fish.id}/media-manager`"
              class="flex items-center gap-3 px-4 min-h-touch-primary hover:bg-blue-50 transition"
            >
              <div class="w-10 h-10 bg-gray-100 rounded-lg shrink-0 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <span class="text-elder-body text-elder-text flex-1 truncate">{{ fish.name }}</span>
              <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
          <p v-else class="text-elder-body text-gray-400 px-1">全部已有照片 🎉</p>
        </section>

        <!-- 最近編輯 -->
        <section>
          <h2 class="text-elder-body font-bold text-gray-700 mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            最近編輯
          </h2>
          <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
            <Link
              v-for="fish in recentEdits"
              :key="fish.id"
              :href="`/fish/${fish.id}`"
              class="flex items-center gap-3 px-4 min-h-touch-primary hover:bg-blue-50 transition"
            >
              <img
                v-if="fish.image_url"
                :src="fish.image_url"
                :alt="fish.name"
                class="w-10 h-10 object-cover rounded-lg shrink-0"
              />
              <div v-else class="w-10 h-10 bg-gray-100 rounded-lg shrink-0" />
              <span class="text-elder-body text-elder-text flex-1 truncate">{{ fish.name }}</span>
              <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          </div>
        </section>

      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'

const LIMIT_OPTIONS = [10, 20, 30, 50]

defineProps({
  needAudio:   { type: Array, default: () => [] },
  needPhoto:   { type: Array, default: () => [] },
  recentEdits: { type: Array, default: () => [] },
  limit:       { type: Number, default: 20 },
})

function onLimitChange(event) {
  router.get('/workspace', { limit: event.target.value }, { preserveState: false })
}
</script>
