<template>
  <div v-if="variant === 'desktop'" class="flex w-full items-center justify-end gap-3">
    <SearchToggleButton label="搜尋魚類" @toggle="$emit('toggle', $event)" />
  </div>
  <div v-else-if="variant === 'mobile'" class="flex w-full items-center justify-end">
    <SearchToggleButton @toggle="$emit('toggle', $event)" />
  </div>
</template>

<script setup>
import SearchToggleButton from '@/Components/UI/SearchToggleButton.vue'

defineProps({
  variant: {
    type: String,
    required: true,
    validator: (value) => ['desktop', 'mobile'].includes(value),
  },
})

defineEmits(['toggle'])
</script>
