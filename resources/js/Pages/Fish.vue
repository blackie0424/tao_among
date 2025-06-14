<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-8">
    <Breadcrumb :fishName="fish.name" />
    <div class="flex flex-col md:flex-row gap-8 items-start justify-center">
      <FishDetailLeft :fish="fish" />
      <FishDetailRight
        :locates="locates"
        :fish-id="fish.id"
        :current-locate="currentLocate"
        :notes="notes"
        :handle-locate-data="handleLocateData"
      />
    </div>
    <FabButton
      :to="`/fish/${fish.id}/create`"
      label="新增知識"
      icon="＋"
      bgClass="bg-green-600"
      hoverClass="hover:bg-green-700"
      textClass="text-white"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '@/Components/Breadcrumb.vue';
import FishDetailLeft from '@/Components/FishDetailLeft.vue';
import FishDetailRight from '@/Components/FishDetailRight.vue';
import FabButton from '@/Components/FabButton.vue';

const props = defineProps({
  fish: Object,
  initialLocate: String,
});

const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmeylek', label: 'Iranmeylek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
];

const currentLocate = ref(props.initialLocate || locates[0].value);
const notes = ref(props.fish.notes || []);

function handleLocateData({ locate, notes: newNotes }) {
  currentLocate.value = locate;
  notes.value = newNotes;
}
</script>