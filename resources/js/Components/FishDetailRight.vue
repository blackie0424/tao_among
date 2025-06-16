<!-- filepath: resources/js/Components/FishDetailRight.vue -->
<template>
  <div class="w-full flex flex-col items-center">
    <div class="fish-section-card">
      <FishKnowledge
        :locates="locates"
        :fish-id="fishId"
        :current-locate="currentLocate"
        :notes="notes"
        mode="dropdown"
        @update:locateData="handleLocateData"
      />
    </div>
  </div>
</template>

<script setup>
import FishKnowledge from '@/Components/FishKnowledge.vue'

defineProps({
  locates: Array,
  fishId: [String, Number],
  currentLocate: String,
  notes: Array,
  handleLocateData: Function,
})
</script>
