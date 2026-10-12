<template>
  <div
    data-testid="fish-tab-bar"
    class="grid grid-cols-6 gap-1 md:flex md:flex-nowrap"
  >
    <button
      v-for="(tab, index) in tabs"
      :key="tab.key"
      class="w-full min-w-0 rounded-lg bg-gray-100 px-2 py-2 text-elder-body font-medium text-elder-subtext min-h-touch-secondary break-words transition-colors hover:bg-gray-200 hover:text-elder-text md:w-auto md:shrink-0 md:whitespace-nowrap"
      :class="[
        mobileGridClass(index),
        activeTab === tab.key ? 'bg-blue-100 text-blue-700 font-semibold' : '',
      ]"
      @click="$emit('select', tab.key)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<script setup>
const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  activeTab: {
    type: String,
    required: true,
  },
})

defineEmits(['select'])

function mobileGridClass(index) {
  if (props.tabs.length === 4) return 'col-span-3'
  if (props.tabs.length === 5) return index < 3 ? 'col-span-2' : 'col-span-3'
  if ([3, 6].includes(props.tabs.length)) return 'col-span-2'

  return 'col-span-6'
}
</script>
