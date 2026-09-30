<template>
  <button
    type="button"
    class="inline-flex min-h-touch-secondary items-center gap-2 rounded-xl bg-blue-700 px-4 text-elder-body font-bold text-white hover:bg-blue-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
    aria-label="展開搜尋篩選"
    @click="$emit('toggle', $event)"
  >
    <svg
      aria-hidden="true"
      class="h-6 w-6"
      fill="none"
      stroke="currentColor"
      stroke-width="2"
      viewBox="0 0 24 24"
    >
      <circle cx="11" cy="11" r="8" />
      <line x1="21" y1="21" x2="16.65" y2="16.65" />
    </svg>
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
defineProps({
  label: { type: String, default: '搜尋' },
})

defineEmits(['toggle'])
</script>
