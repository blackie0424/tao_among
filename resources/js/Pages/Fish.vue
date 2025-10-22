<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <Head :title="`${fish.name}的基本資料`" />
  <!-- 增加 padding-bottom 以避開底部固定工具列；行動裝置包含 safe-area -->
  <div
    class="container mx-auto py-8"
    style="padding-bottom: calc(6rem + env(safe-area-inset-bottom))"
  >
    <div class="flex flex-col md:flex-row gap-4 md:gap-8 items-start justify-center">
      <!-- 左欄：魚資訊 -->
      <div class="w-full md:w-1/2">
        <FishDetailLeft :fish="fish" />
      </div>

      <!-- 右欄：部落分類區塊 + 筆記 -->
      <div class="w-full md:w-1/2 space-y-4">
        <TribalClassificationSummary :classifications="tribalClassifications" :fishId="fish.id" />

        <!-- 新增：依 note_type 分組顯示 fish_notes（樣式與地方知識一致） -->
        <div class="rounded-xl bg-white shadow-md border border-gray-200 p-4 mb-20 md:mb-10">
          <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">進階知識</h3>
          </div>

          <div v-if="Object.keys(groupedNotes).length">
            <div v-for="(items, type) in groupedNotes" :key="type" class="mb-4">
              <h4 class="font-medium">
                {{ type }} <span class="text-lg text-gray-500">({{ items.length }})</span>
              </h4>
              <ul>
                <li v-for="note in items" :key="note.id" class="border rounded p-2 mt-2">
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                      <!-- locate 圓角徽章 -->
                      <span
                        class="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-lg font-medium text-gray-700"
                      >
                        {{ note.locate }}
                      </span>

                      <!-- 筆記內容（同列顯示） -->
                      <div class="text-lg text-gray-700 leading-tight">
                        {{ note.note }}
                      </div>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <div v-else class="text-gray-500">尚無筆記</div>
        </div>
      </div>
    </div>

    <BottomNavBar
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      :knowledge="`/fish/${fish.id}/knowledge`"
      :audioList="`/fish/${fish.id}/audio-list`"
      :currentPage="'fishBasicInfo'"
    />
  </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'

import { ref, onMounted, computed } from 'vue'
import Breadcrumb from '@/Components/Global/Breadcrumb.vue'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'
import FishDetailRight from '@/Components/FishDetailRight.vue'
import FabButton from '@/Components/FabButton.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'
import CaptureRecordSummary from '@/Components/CaptureRecordSummary.vue'

const props = defineProps({
  fish: Object,
  initialLocate: String,
  tribalClassifications: {
    type: Array,
    default: () => [],
  },
  captureRecords: {
    type: Array,
    default: () => [],
  },
  // 新增：接收 controller 已依 note_type 分組好的資料（物件）
  fishNotes: {
    type: Object,
    default: () => ({}),
  },
})

const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmeylek', label: 'Iranmeylek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
]

const currentLocate = ref(props.initialLocate || locates[0].value)
const notes = ref(props.fish.notes || [])

function handleLocateData({ locate, notes: newNotes }) {
  currentLocate.value = locate
  notes.value = newNotes
}

import ArmSelector from '@/Components/ArmSelector.vue'

const selectedParts = ref([])
const fishId = ref(props.fish.id)

onMounted(async () => {
  const res = await fetch(`/prefix/api/fishSize/${fishId.value}`)
  const data = await res.json()
  if (res.ok && data.data?.parts) {
    selectedParts.value = data.data.parts
  }
})

// 將後端已分組的資料直接暴露為 computed（若未給予則為空物件）
const groupedNotes = computed(() => props.fishNotes || {})

// 簡單格式化日期（可以依需求調整）
function formatDate(dt) {
  if (!dt) return ''
  try {
    return new Date(dt).toLocaleString()
  } catch (e) {
    return dt
  }
}
</script>
