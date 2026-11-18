<template>
  <div v-if="items.length" class="flex flex-wrap gap-x-6 gap-y-1 mb-4" data-testid="tab-edit-actions">
    <Link
      v-for="item in items"
      :key="item.href"
      :href="item.href"
      class="inline-flex items-center min-h-touch-primary py-3 text-elder-body font-medium text-brand underline underline-offset-4 hover:text-brand-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"
      >{{ item.label }}</Link
    >
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
defineProps({ items: { type: Array, default: () => [] } })
</script>
