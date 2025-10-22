<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <Head :title="`${fish.name}的基本資料`" />
  <!-- 增加 padding-bottom 以避開底部固定工具列；行動裝置包含 safe-area -->
  <div
    class="container mx-auto py-8"
    style="padding-bottom: calc(3.5rem + env(safe-area-inset-bottom))"
  >
    <div class="flex flex-col md:flex-row gap-4 md:gap-8 items-start justify-center">
      <!-- 左欄：魚資訊 -->
      <div class="w-full md:w-1/2">
        <FishDetailLeft :fish="fish" />
      </div>

      <!-- 右欄：部落分類區塊 -->
      <div class="w-full md:w-1/2">
        <TribalClassificationSummary :classifications="tribalClassifications" :fishId="fish.id" />
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

import { ref, onMounted } from 'vue'
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
</script>
