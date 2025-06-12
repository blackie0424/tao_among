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
      <div class="show_image w-full max-w-3xl mx-auto mb-6 p-4 rounded-lg shadow-custom">
        <img :src="fish.image" :alt="fish.name" loading="lazy" class="w-full h-auto rounded-lg object-contain" />
      </div>

      <!-- 魚類名稱 -->
      <div class="section section-name w-full max-w-md text-center p-4 rounded-lg shadow-custom mb-4">
        <div class="text text-xl text-secondary">ngaran no among</div>
        <div class="section-title text-2xl font-bold text-primary mb-2">{{ fish.name }}</div>
      </div>

      <!-- 按鈕區塊 -->
      <div class="button-container">
        <div class="section-buttons flex flex-wrap md:flex-nowrap gap-2 md:gap-4 my-4 p-4 rounded-lg">
          <a
            v-for="locate in locates"
            :key="locate.value"
            :href="`?locate=${locate.value}`"
            class="locate-filter block w-full sm:w-1/2 md:w-auto md:min-w-[120px] px-6 py-2 min-h-[48px] flex items-center justify-center bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 rounded-full hover:bg-blue-200 dark:hover:bg-blue-700"
            :class="{'bg-yellow-500 dark:bg-yellow-600': currentLocate === locate.value}"
          >
            {{ locate.label }}
          </a>
        </div>
      </div>

      <!-- 筆記區塊 -->
      <div v-if="fish.notes && fish.notes.length" class="w-full flex flex-col items-center">
        <div
          v-for="note in fish.notes"
          :key="note.id"
          class="section w-full max-w-md p-4 bg-beige-100 rounded-lg shadow-custom mb-4"
        >
          <div class="section-title text-xl font-semibold text-primary mb-2">{{ note.note_type }}</div>
          <div class="text text-secondary">{{ note.note }}</div>
        </div>
      </div>
    </div>
    <footer class="text-center text-secondary mt-8">Copyright © 2025 Chungyueh</footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

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