<template>
  <div
    data-testid="fish-tab-bar"
    class="grid grid-cols-6 gap-2 md:flex md:flex-nowrap"
  >
    <button
      v-for="(tab, index) in tabs"
      :key="tab.key"
      class="w-full min-w-0 whitespace-nowrap rounded-lg bg-paper-raised px-1 py-2 text-elder-body min-h-touch-secondary transition-colors hover:border-brand hover:text-elder-text md:w-auto md:shrink-0 md:px-4"
      :class="[
        mobileGridClass(index),
        activeTab === tab.key
          ? 'border-2 border-brand text-brand font-bold'
          : 'border border-rule-strong text-elder-subtext',
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
