<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-6">
    <div class="flex flex-col items-center">
      <!-- 圖片與名字區塊（固定） -->
      <FishImage :image="fishImage" :name="fishName" />
      <FishName :name="fishName" />

      <!-- 地區與筆記區塊 -->
      <FishKnowledge
        :locates="locates"
        :fish-id="fishId"
        :current-locate="currentLocate"
        :notes="notes"
        @update:locateData="handleLocateData"
      />
    </div>
    <footer class="text-center text-secondary mt-8">Copyright © 2025 Chungyueh</footer>

    <!-- 左下角圓形新增知識按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="新增筆記"
      icon="＋"
      :to="`/fish/${fishId}/create`"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import FishImage from '@/Components/FishImage.vue';
import FishName from '@/Components/FishName.vue';
import FishKnowledge from '@/Components/FishKnowledge.vue';
import FabButton from '@/Components/FabButton.vue'; // 新增魚類按鈕


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

// 狀態：目前地區與筆記
const currentLocate = ref(props.initialLocate || 'iraraley');
const notes = ref(props.fish.notes || []);

function handleLocateData({ locate, notes:newNotes }) {
  currentLocate.value = locate; // 更新目前地區
  notes.value = newNotes;
}
</script>