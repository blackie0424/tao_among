<template>
  <div class="container mx-auto p-4">
    <!-- 色調切換按鈕（改用 DarkModeSwitcher） -->
    <DarkModeSwitcher :show-button="false" />
    <main>
      <section class="main grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <FishCard v-for="fish in fishes" :key="fish.id" :fish="fish" />
      </section>
    </main>

    <footer class="mt-8 text-center text-gray-500">Copyright © 2025 Chungyueh</footer>
    <BottomNavBar label="新增魚類" icon="＋" to="/fish/create" />
  </div>
</template>

<script setup>
import FishCard from '@/Components/FishCard.vue'
import DarkModeSwitcher from '@/Components/DarkModeSwitcher.vue'
import BottomNavBar from '../Components/Global/BottomNavBar.vue'

defineProps({
  fishes: {
    type: Array,
    required: true,
  },
})
</script>
