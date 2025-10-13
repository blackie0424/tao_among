<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-8">
    <div class="flex flex-col md:flex-row gap-8 items-start justify-center">
      <!-- 左欄：魚資訊 -->
      <div class="w-full md:w-2/4">
        <FishDetailLeft :fish="fish" />
      </div>
      <!-- 中欄：ArmSelector -->
      <div class="w-full md:w-1/4">
        <ArmSelector
          v-model="selectedParts"
          :readonly="true"
          :fishId="fish.id"
          :showOverflowMenu="true"
        />
      </div>
      <!-- 右欄：知識 -->
      <div class="w-full md:w-1/4">
        <FishDetailRight
          :locates="locates"
          :fish-id="fish.id"
          :current-locate="currentLocate"
          :notes="notes"
          :handle-locate-data="handleLocateData"
        />
      </div>
    </div>

    <!-- 部落分類區塊 -->
    <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- 部落分類摘要 -->
      <TribalClassificationSummary :classifications="tribalClassifications" :fishId="fish.id" />
    </div>

    <BottomNavBar
      :to="`/fish/${fish.id}/create`"
      :audio="`/fish/${fish.id}/createAudio`"
      :fishBasicInfo="`/fish/${fish.id}`"
      :tribalKnowledge="`/fish/${fish.id}/tribal-classifications`"
      :captureRecords="`/fish/${fish.id}/capture-records`"
      label="新增知識"
      icon="＋"
      :currentPage="'fishBasicInfo'"
    />
  </div>
</template>

<script setup>
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
