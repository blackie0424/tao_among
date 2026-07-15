<template>
  <button
    class="inline-flex flex-col items-center justify-center bg-white dark:bg-gray-800 rounded-xl shadow px-3 min-h-touch-secondary hover:bg-blue-100 dark:hover:bg-blue-900 transition border border-gray-300 dark:border-gray-700"
    @click="emitToggle"
    aria-label="展開搜尋篩選"
  >
    <svg
      class="w-6 h-6 text-blue-700 dark:text-gray-200"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      viewBox="0 0 24 24"
    >
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
    <span class="text-elder-aux text-blue-700 dark:text-gray-200 leading-tight">搜尋</span>
  </button>
</template>

<script setup>
const emit = defineEmits(['toggle'])
const emitToggle = () => emit('toggle')
</script>
