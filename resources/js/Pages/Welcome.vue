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
      <FishCard
        v-for="fish in fishes"
        :key="fish.id"
        :fish="fish"
      />
    </div>

    <footer class="mt-8">Copyright © 2025 Chungyueh</footer>

    <!-- 固定左下角新增魚類按鈕 -->
    <FabButton
      bgClass="bg-blue-600"
      hoverClass="hover:bg-blue-700"
      textClass="text-white"
      label="新增魚類"
      icon="＋"
      to="/fish/create"
    />
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import HeaderComponent from '@/Components/HeaderComponent.vue';
import FabButton from '@/Components/FabButton.vue';
import FishCard from '@/Components/FishCard.vue';

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
  if (localStorage.getItem('theme') === 'dark') {
    document.documentElement.classList.add('dark');
  }
});
</script>