<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-8">
    <Breadcrumb :second="fish.name" />
    <div class="flex flex-col md:flex-row gap-8 items-start justify-center">
      <!-- 左欄：魚資訊 -->
      <div class="w-full md:w-2/4">
        <FishDetailLeft :fish="fish" />
      </div>
      <!-- 中欄：ArmSelector -->
      <div class="w-full md:w-1/4">
        <ArmSelector v-model="selectedParts" :readonly="true" />
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
    <FabButton
      :to="`/fish/${fish.id}/create`"
      label="新增知識"
      icon="＋"
      bgClass="bg-green-600"
      hoverClass="hover:bg-green-700"
      textClass="text-white"
      position="right-bottom"
    />

    <FabButton
      label="刪除資料"
      icon="x"
      bgClass="bg-red-600"
      hoverClass="hover:bg-red-700"
      textClass="text-white"
      position="right-top"
      @click="deleteFish"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Breadcrumb from '@/Components/Breadcrumb.vue'
import FishDetailLeft from '@/Components/FishDetailLeft.vue'
import FishDetailRight from '@/Components/FishDetailRight.vue'
import FabButton from '@/Components/FabButton.vue'

const props = defineProps({
  fish: Object,
  initialLocate: String,
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

async function deleteFish() {
  if (!confirm('確定要刪除這筆資料嗎？')) return
  try {
    const res = await fetch(`/prefix/api/fish/${props.fish.id}`, {
      method: 'DELETE',
    })
    if (!res.ok) throw new Error('刪除失敗')
    window.location.href = '/'
  } catch (e) {
    alert(e.message || '刪除失敗')
  }
}
</script>
