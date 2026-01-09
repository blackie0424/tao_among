<template>
  <Head :title="`${fish.name}的地方知識`" />

  <div class="space-y-8">
      <!-- Section Header -->
      <div class="flex items-center justify-between">
         <div>
            <h2 class="text-2xl font-serif font-bold text-stone-800">地方知識</h2>
            <p class="text-stone-500 mt-1">不同部落的飲食分類與處理方式</p>
         </div>

         <!-- FAB / Action (Desktop) -->
         <Link
            v-if="!isAllTribesRecorded"
            :href="`/fish/${fish.id}/tribal-classifications/create`"
            class="hidden md:inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-sm transition-colors text-sm font-medium"
         >
            <span class="mr-2 text-lg">+</span> 新增資料
         </Link>
      </div>

      <!-- Completion Status -->
      <div
        v-if="isAllTribesRecorded"
        class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 md:p-6 shadow-sm flex items-start gap-4"
      >
        <div class="flex-shrink-0 bg-emerald-100 rounded-full p-2">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <div>
            <h3 class="text-lg font-bold text-emerald-800 mb-1">已完整記錄</h3>
            <p class="text-emerald-700 text-sm">
              您已記錄 <span class="font-bold">{{ totalTribesCount }}</span> 個部落對於「{{ fish.name }}」的飲食分類與處理方式。
            </p>
        </div>
      </div>

      <!-- Stats -->
      <div class="flex flex-wrap gap-4 text-sm">
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span class="w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
             已記錄 {{ fish.tribal_classifications?.length || 0 }} 筆
          </div>
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span :class="['w-2.5 h-2.5 rounded-full', isAllTribesRecorded ? 'bg-emerald-500' : 'bg-stone-300']"></span>
             涵蓋 {{ uniqueTribes.length }} / {{ totalTribesCount }} 部落
          </div>
      </div>

      <!-- List -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Empty State -->
        <div v-if="fish.tribal_classifications?.length === 0" class="col-span-full text-center py-16 bg-white rounded-2xl border border-dashed border-stone-200">
             <div class="text-stone-300 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
             </div>
             <h3 class="text-lg font-bold text-stone-700">尚未記錄資料</h3>
             <p class="text-stone-500 mt-2 mb-6">點擊下方按鈕開始記錄</p>
             <Link
                :href="`/fish/${fish.id}/tribal-classifications/create`"
                class="inline-flex items-center px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-sm transition-colors font-medium"
             >
                立即新增
             </Link>
        </div>

        <!-- Cards -->
        <TribalClassificationCard
            v-for="classification in fish.tribal_classifications"
            :key="classification.id"
            :classification="classification"
            :fishId="fish.id"
            @updated="onClassificationUpdated"
            @deleted="onClassificationDeleted"
        />
      </div>
  </div>

  <!-- FAB for Mobile -->
  <FabButton
    v-if="!isAllTribesRecorded"
    bgClass="bg-emerald-600"
    hoverClass="hover:bg-emerald-700"
    textClass="text-white"
    label="新增"
    icon="+"
    :to="`/fish/${fish.id}/tribal-classifications/create`"
    position="right-bottom"
  />
</template>

<script>
import FishLayout from '@/Layouts/FishLayout.vue'

export default {
  layout: FishLayout,
}
</script>

<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import TribalClassificationCard from '@/Components/TribalClassificationCard.vue' // Adjust path if needed, assuming component exists
import FabButton from '@/Components/FabButton.vue' // Adjust path

const props = defineProps({
  fish: Object,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
})

const totalTribesCount = computed(() => props.tribes?.length || 6)

const uniqueTribes = computed(() => {
  if (!props.fish.tribal_classifications) return []
  const tribes = props.fish.tribal_classifications.map((c) => c.tribe)
  return [...new Set(tribes)]
})

const isAllTribesRecorded = computed(() => {
  return uniqueTribes.value.length >= totalTribesCount.value
})

function onClassificationUpdated() {
  router.reload({ only: ['fish'], preserveScroll: true })
}

function onClassificationDeleted() {
  router.reload({ only: ['fish'], preserveScroll: true })
}
</script>
