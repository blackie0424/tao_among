<template>
  <Head :title="`${fish.name}的捕獲紀錄`" />

  <div class="space-y-8">
      <!-- Section Header -->
      <div class="flex items-center justify-between">
         <div>
            <h2 class="text-2xl font-serif font-bold text-stone-800">捕獲紀錄</h2>
            <p class="text-stone-500 mt-1">累積的捕獲照片與相關資訊</p>
         </div>

         <!-- FAB / Action (Desktop) -->
         <Link
            :href="`/fish/${fish.id}/capture-records/create`"
            class="hidden md:inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm transition-colors text-sm font-medium"
         >
            <span class="mr-2 text-lg">+</span> 新增紀錄
         </Link>
      </div>

      <!-- Stats -->
      <div class="flex flex-wrap gap-4 text-sm">
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span class="w-2.5 h-2.5 bg-blue-500 rounded-full"></span>
             已記錄 {{ fish.captureRecords?.length || 0 }} 筆
          </div>
          <div class="bg-white border border-stone-200 px-3 py-1.5 rounded-full flex items-center gap-2 text-stone-600">
             <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
             涵蓋 {{ uniqueTribes.length }} 個部落
          </div>
      </div>

      <!-- Gallery Grid -->
      <div v-if="fish.captureRecords?.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <CaptureRecordCard
            v-for="record in fish.captureRecords"
            :key="record.id"
            :record="record"
            :fishId="fish.id"
            :displayCaptureRecordId="fish.display_capture_record_id"
            @updated="onRecordUpdated"
            @deleted="onRecordDeleted"
          />
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-16 bg-white rounded-2xl border border-dashed border-stone-200">
             <div class="text-stone-300 mb-4">
                <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
             </div>
             <h3 class="text-lg font-bold text-stone-700">尚未記錄捕獲照片</h3>
             <p class="text-stone-500 mt-2 mb-6">點擊下方按鈕開始記錄</p>
             <Link
                :href="`/fish/${fish.id}/capture-records/create`"
                class="inline-flex items-center px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg shadow-sm transition-colors font-medium"
             >
                立即新增
             </Link>
      </div>

  </div>

  <!-- FAB for Mobile -->
  <FabButton
    bgClass="bg-blue-600"
    hoverClass="hover:bg-blue-700"
    textClass="text-white"
    label="新增"
    icon="+"
    :to="`/fish/${fish.id}/capture-records/create`"
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
import CaptureRecordCard from '@/Components/CaptureRecordCard.vue'
import FabButton from '@/Components/FabButton.vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
})

const uniqueTribes = computed(() => {
  if (!props.fish.captureRecords) return []
  const tribes = props.fish.captureRecords.map((r) => r.tribe)
  return [...new Set(tribes)]
})

function onRecordUpdated() {
  router.reload()
}

function onRecordDeleted() {
  router.reload()
}
</script>
