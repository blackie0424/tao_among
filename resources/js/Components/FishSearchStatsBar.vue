<template>
  <div class="mb-4 flex items-center justify-between">
    <div
      class="flex flex-wrap items-center gap-x-2 gap-y-1 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded-lg px-3 py-2 shadow-sm text-xl"
    >
      <!-- 資料筆數統計 -->
      <div class="inline-flex items-center gap-2 shrink-0">
        <span class="text-amber-700 dark:text-amber-300">資料筆數</span>
        <span class="text-amber-900 dark:text-amber-200 font-semibold">{{ totalCount }}</span>
      </div>

      <!-- 已套用的搜尋條件 chips（與資料筆數同列，空間不足時自動換行） -->
      <div
        v-if="appliedFilters.length"
        class="flex flex-row flex-wrap items-center gap-x-2 gap-y-1 ml-2"
      >
        <span
          v-for="f in appliedFilters"
          :key="f.key + ':' + f.value"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-amber-300/80 bg-white/70 text-amber-800 dark:bg-amber-900/40 dark:text-amber-100 dark:border-amber-700/70 text-xl"
        >
          <span class="truncate max-w-[16rem]">{{ f.label }}：{{ f.value }}</span>
          <button
            type="button"
            class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded hover:bg-amber-200/60 dark:hover:bg-amber-700/60 text-amber-800 dark:text-amber-100"
            :aria-label="`移除條件 ${f.label}`"
            @click="$emit('remove-filter', f.key)"
          >
            ×
          </button>
        </span>
      </div>
    </div>

    <!-- 搜尋切換按鈕 -->
    <div class="flex items-center gap-2">
      <slot name="actions">
        <!-- 預設插槽：可自訂右側按鈕 -->
      </slot>
    </div>
  </div>
</template>

<script setup>
defineProps({
  totalCount: {
    type: Number,
    required: true,
  },
  appliedFilters: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['remove-filter'])
</script>
