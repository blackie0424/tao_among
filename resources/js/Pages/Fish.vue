<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-8">
    <div class="flex flex-col md:flex-row gap-8 items-start justify-center">
      <!-- 左欄：魚名與圖片（圖片區塊佔2/3寬度） -->
      <div class="w-full md:w-2/3 flex flex-col items-center">
        <div class="w-full bg-gray-100 max-w-3xl mx-auto rounded-xl shadow p-6 mb-6 flex flex-col items-center">
          <FishName :name="fishName" class="w-full max-w-2xl text-2xl font-bold mb-4" />
          <FishImage :image="fishImage" :name="fishName" class="w-full max-w-2xl h-[32rem] object-cover rounded-xl" />
        </div>
      </div>
      <!-- 右欄：FishKnowledge -->
      <div class="w-full md:w-1/3 flex flex-col items-center">
        <div class="w-full bg-gray-100 rounded-xl shadow p-6 mb-6 flex flex-col items-center">
          <FishKnowledge
            :locates="locates"
            :fish-id="fishId"
            :current-locate="currentLocate"
            :notes="notes"
            mode="dropdown"
            @update:locateData="handleLocateData"
          />
        </div>
      </div>
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
import { ref } from 'vue';
import FishImage from '@/Components/FishImage.vue';
import FishName from '@/Components/FishName.vue';
import FishKnowledge from '@/Components/FishKnowledge.vue';
import FabButton from '@/Components/FabButton.vue';

const props = defineProps({
  fish: Object,
  initialLocate: String,
});

// 前端自行定義 locates
const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmailek', label: 'Iranmailek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
];

const fishId = props.fish.id;
const fishImage = props.fish.image;
const fishName = props.fish.name;

// 狀態：目前地區與筆記
const currentLocate = ref(props.initialLocate || locates[0].value);
const notes = ref(props.fish.notes || []);

function handleLocateData({ locate, notes: newNotes }) {
  currentLocate.value = locate; // 更新目前地區
  notes.value = newNotes;
}
</script>