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
        <section>
          <TribalClassificationSummary
            :classifications="tribalClassifications"
            :tribes="tribes"
            :fishId="fish.id"
          />
        </section>
      </template>

      <!-- 中欄：捕獲紀錄 -->
      <template #middle>
        <section v-if="captureRecords.length || user">
          <div class="rounded-xl bg-white shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
              <div class="flex items-center gap-3">
                <h3 class="text-2xl font-bold text-gray-900">捕獲紀錄</h3>
                <span class="text-sm font-bold bg-gray-100 text-gray-800 px-3 py-1 rounded-full">{{
                  captureRecords.length
                }}</span>
              </div>
            </div>

            <div
              v-if="captureRecords.length"
              class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            >
              <CaptureRecordDisplayCard
                v-for="(record, index) in captureRecords"
                :key="record.id"
                :record="record"
                :index="index"
                :fishName="fish.name"
              />
            </div>
          </div>
        </section>
      </template>

      <!-- 底部：進階知識（僅 editor / admin 可見） -->
      <template #bottom>
        <FishAdvancedKnowledgeSection
          :fishNotes="fishNotes"
          :isEditor="isEditor"
          :user="user"
        />
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
import CaptureRecordDisplayCard from '@/Components/CaptureRecordDisplayCard.vue'
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
