<!-- filepath: /Users/chungyueh/Herd/tao_among/resources/js/Pages/Fish.vue -->
<template>
  <div class="container mx-auto py-6">
    <div class="flex flex-col items-center">
      <!-- 色調切換按鈕 -->
      <button
        id="theme-toggle"
        class="fixed top-4 right-4 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded"
        @click="toggleDarkMode"
      >
        版面色調切換
      </button>
      <a href="/" class="fixed top-4 left-4 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
        nivasilan ko a among
      </a>

      <!-- 圖片區塊 -->
      <FishImage :image="fish.image" :name="fish.name" />
      <!-- 魚類名稱 -->
      <FishName :name="fish.name" />
      <!-- 按鈕區塊 -->
      <FishLocateButtons :locates="locates" />
      <!-- 筆記區塊 -->
      <FishNotes :notes="fish.notes" />
    </div>

    <footer class="text-center text-secondary mt-8">Copyright © 2025 Chungyueh</footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

import FishImage from '@/Components/FishImage.vue';
import FishName from '@/Components/FishName.vue';
import FishLocateButtons from '@/Components/FishLocateButtons.vue';
import FishNotes from '@/Components/FishNotes.vue';



const props = defineProps({
  fish: {
    type: Object,
    required: true,
  },
});

const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmailek', label: 'Iranmailek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
];

const currentLocate = computed(() => {
  const url = new URL(window.location.href);
  return url.searchParams.get('locate') || 'iraraley';
});

function toggleDarkMode() {
  document.documentElement.classList.toggle('dark');
  localStorage.setItem(
    'theme',
    document.documentElement.classList.contains('dark') ? 'dark' : 'light'
  );
}

function autoDarkMode() {
  const now = new Date();
  const hour = now.getHours();
  const isDayTime = hour >= 6 && hour < 18;
  document.documentElement.classList.toggle('dark', !isDayTime);
}

onMounted(() => {
  if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    autoDarkMode();
  }
  setInterval(autoDarkMode, 60000);
});
</script>