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
    <button
      @click="goToCreateNote"
      class="fixed right-6 bottom-6 z-50 w-16 h-16 rounded-full bg-green-600 hover:bg-green-700 text-white flex items-center justify-center shadow-lg text-3xl"
      title="新增魚類知識"
    >
      +
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import FishImage from '@/Components/FishImage.vue';
import FishName from '@/Components/FishName.vue';
import FishKnowledge from '@/Components/FishKnowledge.vue';

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

// 跳轉到新增魚類知識頁面
function goToCreateNote() {
  window.location.href = `/fish/${fishId}/create`;
}
</script>