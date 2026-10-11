<template>
  <div v-if="items.length" class="flex flex-wrap gap-3 mb-4" data-testid="tab-edit-actions">
    <Link
      v-for="item in items"
      :key="item.href"
      :href="item.href"
      class="inline-flex items-center justify-center min-h-touch-primary px-4 py-3 rounded-lg border border-blue-200 bg-white text-elder-body font-medium text-blue-700 hover:bg-blue-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-600"
      >{{ item.label }}</Link
    >
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({ items: { type: Array, default: () => [] } })
</script>
