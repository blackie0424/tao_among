<template>
  <Head :title="`${fish.name}的知識管理`" />

  <div class="space-y-8">
      <!-- Section Header -->
      <div class="flex items-center justify-between">
         <div>
            <h2 class="text-2xl font-serif font-bold text-stone-800">知識管理</h2>
            <p class="text-stone-500 mt-1">地方知識與進階筆記的總覽與管理</p>
         </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-white p-4 rounded-xl shadow-sm border border-stone-100 flex flex-col items-center justify-center text-center">
             <span class="text-3xl font-bold text-emerald-600 mb-1">{{ tribalClassificationsCount || 0 }}</span>
             <span class="text-sm text-stone-500">地方知識</span>
          </div>
          <div class="bg-white p-4 rounded-xl shadow-sm border border-stone-100 flex flex-col items-center justify-center text-center">
             <span class="text-3xl font-bold text-blue-600 mb-1">{{ notes || 0 }}</span>
             <span class="text-sm text-stone-500">進階筆記</span>
          </div>
          <!-- You can add more stats here if available -->
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- 地方知識 Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-stone-800 mb-2">地方知識</h3>
                <p class="text-stone-500 text-sm leading-relaxed mb-6">
                  記錄這條魚在蘭嶼各部落的食用分類（oyod/rahet）及處理方式（去鱗/剝皮等）。
                </p>

                <div class="space-y-2 text-sm text-stone-400 mb-8">
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-stone-300"></span>
                        食用分類：oyod, rahet, 不食用
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-stone-300"></span>
                        處理方式：去鱗, 剝皮, 切塊
                    </div>
                </div>

                <Link
                  :href="`/fish/${fish.id}/tribal-classifications`"
                  class="block w-full py-3 px-4 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-center rounded-lg font-medium transition-colors"
                >
                  管理地方知識
                </Link>
            </div>
        </div>

        <!-- 進階知識 Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-stone-200 overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-6">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-stone-800 mb-2">進階知識</h3>
                <p class="text-stone-500 text-sm leading-relaxed mb-6">
                  除部落分類外的其他詳細資料，如生態習性、文化故事、命名由來等，皆可在此紀錄。
                </p>

                <div class="space-y-2 text-sm text-stone-400 mb-8">
                     <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-stone-300"></span>
                        可依據不同地區（locate）進行紀錄
                    </div>
                     <div class="flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-stone-300"></span>
                        支援多種標籤分類
                    </div>
                </div>

                <Link
                  :href="`/fish/${fish.id}/knowledge-list`"
                  class="block w-full py-3 px-4 bg-blue-50 hover:bg-blue-100 text-blue-700 text-center rounded-lg font-medium transition-colors"
                >
                  管理進階知識
                </Link>
            </div>
        </div>
      </div>
  </div>
</template>

<script>
import FishLayout from '@/Layouts/FishLayout.vue'

export default {
  layout: FishLayout,
}
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps({
  fish: Object,
  notes: Number,
  tribalClassificationsCount: Number,
})
</script>
