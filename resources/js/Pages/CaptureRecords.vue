<template>
  <Head :title="`${fish.name}・管理捕獲紀錄`" />
  <FishAppLayout
    :pageTitle="`${fish.name}・管理捕獲紀錄`"
    :mobileBackUrl="`/fish/${fish.id}?tab=capture`"
    mobileBackText="回魚類資料"
  >
    <div class="mx-auto max-w-3xl pb-8">
      <h1 class="font-serif text-elder-title text-elder-text">{{ fish.name }}・管理捕獲紀錄</h1>

      <Link
        :href="`/fish/${fish.id}/capture-records/batch-create`"
        class="mt-4 inline-flex min-h-touch-secondary items-center bg-brand px-4 text-elder-body font-bold text-white hover:bg-brand-dark"
      >
        ＋ 新增捕獲紀錄
      </Link>

      <p v-if="!fish.captureRecords?.length" class="mt-6 text-elder-body text-elder-text">
        還沒有捕獲紀錄
      </p>
      <div v-else class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
        <CaptureRecordCard
          v-for="record in fish.captureRecords"
          :key="record.id"
          :record="record"
          :fishId="fish.id"
          :displayCaptureRecordId="fish.display_capture_record_id"
          @updated="reload"
        />
      </div>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import CaptureRecordCard from '../Components/CaptureRecord/CaptureRecordCard.vue'

defineProps({
  fish: Object,
  tribes: Array,
})

// 重新載入頁面以顯示更新的紀錄
function reload() {
  router.reload()
}
</script>
