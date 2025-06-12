<template>
  <div class="container mx-auto p-4">
    <!-- 色調切換按鈕 -->
    <button
      id="theme-toggle"
      class="fixed top-4 right-4 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 rounded"
      @click="toggleDarkMode"
    >
      版面色調切換
    </button>
    <HeaderComponent />


    <div class="main grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="fish in fishes"
        :key="fish.id"
        class="card flex flex-col items-center"
      >
        <div class="image w-full h-48 overflow-hidden">
          <img
            :src="fish.image"
            :alt="fish.name"
            loading="lazy"
            class="w-full h-full object-contain rounded-lg"
          />
        </div>
        <div class="info w-full flex justify-center">
          <div class="textFrame">
            <a
              :href="`/fish/${fish.id}`"
              class="text-lg font-bold text-gray-800 dark:text-gray-100"
            >
              {{ fish.name }}
            </a>
          </div>
        </div>
      </div>
    </div>

    <footer class="mt-8">Copyright © 2025 Chungyueh</footer>

    <!-- 固定左下角新增魚類按鈕 -->
    <a
      href="/fish/create"
      class="fixed bottom-6 right-6 group z-50"
      style="background:transparent; box-shadow:0 4px 16px rgba(0,0,0,0.15);"
    >
      <!-- 展開的膠囊形按鈕 -->
      <span
        class="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white text-lg font-bold rounded-full transition-all duration-300 px-0 w-14 h-14 group-hover:px-6 group-hover:w-auto group-hover:h-14"
        style="box-shadow:0 4px 16px rgba(0,0,0,0.15); min-width:3.5rem;"
      >
        <span class="transition-all duration-300">
          ＋<span class="ml-0 group-hover:ml-2 group-hover:inline hidden">新增魚類</span>
        </span>
      </span>
    </a>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import HeaderComponent from '@/Components/HeaderComponent.vue'; // 請根據你的 header 實際路徑調整


defineProps({
  fishes: {
    type: Array,
    required: true
  }
});

function toggleDarkMode() {
  document.documentElement.classList.toggle('dark');
  localStorage.setItem(
    'theme',
    document.documentElement.classList.contains('dark') ? 'dark' : 'light'
  );
}

onMounted(() => {
  // 載入時檢查使用者偏好
  if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
  }
});
</script>