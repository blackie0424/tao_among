<template>
  <div v-if="variant !== 'header' || appliedFilters.length > 0" class="mb-4">
    <div class="flex flex-wrap items-center gap-2">
      <div v-if="showTotalCount" class="inline-flex min-h-touch-secondary items-center gap-2 text-elder-body text-elder-subtext">
        <span>資料筆數</span>
        <span class="font-bold text-elder-text">{{ totalCount }}</span>
      </div>

      <button
        v-for="filter in appliedFilters"
        :key="`${filter.key}:${filter.value}`"
        type="button"
        :aria-label="`移除條件 ${filter.label}：${filter.value}`"
        class="inline-flex min-h-touch-secondary items-center gap-2 rounded-xl border-2 border-blue-700 bg-white px-4 text-elder-body font-bold text-blue-700 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
        @click="$emit('remove-filter', filter.key)"
      >
        <span class="max-w-[16rem] truncate">{{ filter.label }}：{{ filter.value }}</span>
        <span aria-hidden="true">✕</span>
      </button>

      <button
        v-if="appliedFilters.length >= 2"
        type="button"
        class="min-h-touch-secondary px-3 text-elder-body text-elder-subtext underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"
        @click="$emit('clear-all')"
      >
        清除全部
      </button>

      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalCount: { type: Number, required: true },
  appliedFilters: { type: Array, default: () => [] },
  showTotalCount: { type: Boolean, default: true },
  variant: { type: String, default: 'default' },
})

defineEmits(['remove-filter', 'clear-all'])
</script>
