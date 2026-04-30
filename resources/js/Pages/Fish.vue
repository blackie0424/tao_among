<template>
  <Head :title="`${fish.name}的基本資料`" />

  <FishAppLayout
    :pageTitle="fish.name"
    mobileBackUrl="/fishs"
    :mobileBackText="mobileBackText"
    :showBottomNav="!!user"
  >
    <FishGridLayout>
      <!-- 頂部額外內容：地方知識摘要 -->
      <template #top-extra>
        <TribalClassificationSummary
          :classifications="tribalClassifications"
          :tribes="tribes"
          :fishId="fish.id"
        />
      </template>

      <!-- 中欄：捕獲紀錄 -->
      <template #middle>
        <CaptureRecordSection
          :captureRecords="captureRecords"
          :fishName="fish.name"
          :user="user"
        />
      </template>

      <!-- 底部：進階知識（僅 editor / admin 可見） -->
      <template #bottom>
        <FishAdvancedKnowledgeSection :fishNotes="fishNotes" :isEditor="isEditor" :user="user" />
      </template>
    </FishGridLayout>
  </FishAppLayout>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import FishGridLayout from '@/Layouts/FishGridLayout.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import CaptureRecordSection from '@/Components/CaptureRecordSection.vue'
import FishAdvancedKnowledgeSection from '@/Components/FishAdvancedKnowledgeSection.vue'

// Removed persistent layout to support dynamic props
// defineOptions({
//   layout: FishAppLayout
// })

const props = defineProps({
  fish: Object,
  tribalClassifications: { type: Array, default: () => [] },
  captureRecords: { type: Array, default: () => [] },
  fishNotes: { type: Object, default: () => ({}) },
  tribes: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const isEditor = computed(() => ['editor', 'admin'].includes(user.value?.role))
// 動態決定手機版麵包屑中間層級文字
// 若魚名太長 (> 12 字元)，則縮減中間層級為 "..." 以爭取空間
const mobileBackText = computed(() => {
  return (props.fish?.name?.length || 0) > 12 ? '...' : 'among no tao'
})
</script>
