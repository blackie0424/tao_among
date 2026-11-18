<template>
  <button
    type="button"
    class="inline-flex h-[52px] min-h-touch-secondary w-full items-center gap-3 rounded-full border border-rule-strong bg-paper-raised px-5 text-elder-body text-elder-subtext hover:border-brand focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
