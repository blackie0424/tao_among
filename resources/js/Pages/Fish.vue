<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-8">
    <Breadcrumb :fishName="fishName" />
    <div class="flex flex-col md:flex-row gap-8 items-start justify-center">
      <FishDetailLeft :fishName="fishName" :fishImage="fishImage" />
      <FishDetailRight
        :locates="locates"
        :fish-id="fishId"
        :current-locate="currentLocate"
        :notes="notes"
        :handle-locate-data="handleLocateData"
      />
    </div>
    <FabButton
      :to="`/fish/${fishId}/create`"
      label="新增知識"
      icon="＋"
      bgClass="bg-green-600"
      hoverClass="hover:bg-green-700"
      textClass="text-white"
    />
  </div>
</template>

<script setup>
import Breadcrumb from '@/Components/Breadcrumb.vue';
import FishDetailLeft from '@/Components/FishDetailLeft.vue';
import FishDetailRight from '@/Components/FishDetailRight.vue';
import FabButton from '@/Components/FabButton.vue';

const props = defineProps({
  fish: Object,
  initialLocate: String,
});

// 前端自行定義 locates
const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmeylek', label: 'Iranmeylek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
];

const fishId = props.fish.id;
const fishImage = props.fish.image;
const fishName = props.fish.name;

import { ref } from 'vue';
const currentLocate = ref(props.initialLocate || locates[0].value);
const notes = ref(props.fish.notes || []);

function handleLocateData({ locate, notes: newNotes }) {
  currentLocate.value = locate; // 更新目前地區
  notes.value = newNotes;
}
</script>