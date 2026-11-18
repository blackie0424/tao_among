<template>
  <button
    type="button"
    data-testid="floating-search"
    :aria-label="filtered ? '搜尋魚類，已套用篩選' : '搜尋魚類'"
    class="relative flex h-14 w-14 items-center justify-center rounded-full border border-brand bg-brand text-white hover:bg-brand-dark focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-brand/30"
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
    <span
      v-if="filtered"
      data-testid="floating-search-dot"
      aria-hidden="true"
      class="absolute right-0 top-0 h-4 w-4 rounded-full border-2 border-white bg-amber-500"
    ></span>
  </button>
</template>

<script setup>
defineProps({
  // 目前列表是否已套用篩選
  filtered: { type: Boolean, default: false },
})
</script>
