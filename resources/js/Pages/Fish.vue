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
        <section
          v-if="isEditor && (Object.keys(groupedNotes).length || user)"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-4"
        >
          <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
            <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
              <span>📖</span> 進階知識
            </h2>
          </div>

          <div v-if="Object.keys(groupedNotesByTypeAndLocate).length" class="space-y-8">
            <div v-for="(locates, type) in groupedNotesByTypeAndLocate" :key="type">
              <h4 class="text-lg font-bold text-gray-800 mb-4 px-1 flex items-center border-b pb-2">
                <span class="w-1.5 h-5 bg-teal-500 rounded-full mr-2"></span>
                {{ type }}
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- 針對每個部落標籤建一個區塊 -->
                <div
                  v-for="(notes, locate) in locates"
                  :key="locate"
                  class="bg-gray-50 rounded-xl p-5 border border-gray-200"
                >
                  <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 mb-3"
                  >
                    {{ locate }}
                  </span>
                  <ul class="space-y-4">
                    <li v-for="note in notes" :key="note.id">
                      <div class="text-gray-800 md:text-lg whitespace-pre-line leading-relaxed">
                        {{ note.note }}
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <div
            v-else
            class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg"
          >
            目前沒有進階地方知識的紀錄
          </div>
        </section>
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
const groupedNotes = computed(() => props.fishNotes || {})

// 將進階知識依據分類標籤 (note_type) 及部落標籤 (locate) 進行二次統整
const groupedNotesByTypeAndLocate = computed(() => {
  const result = {}
  for (const [type, notes] of Object.entries(props.fishNotes || {})) {
    result[type] = {}
    for (const note of notes) {
      const locate = note.locate || '未分類部落'
      if (!result[type][locate]) {
        result[type][locate] = []
      }
      result[type][locate].push(note)
    }
  }
  return result
})

// 動態決定手機版麵包屑中間層級文字
// 若魚名太長 (> 12 字元)，則縮減中間層級為 "..." 以爭取空間
const mobileBackText = computed(() => {
  return (props.fish?.name?.length || 0) > 12 ? '...' : 'among no tao'
})

</script>
