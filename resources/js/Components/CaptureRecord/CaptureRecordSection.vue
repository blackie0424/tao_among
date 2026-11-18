<template>
  <section
    v-if="captureRecords.length || user"
    class="border border-rule-strong bg-paper-raised p-4"
  >
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-rule">
      <div class="flex items-center gap-3">
        <h3 class="font-serif text-elder-name font-bold text-elder-text">捕獲紀錄</h3>
        <span
          data-testid="capture-count"
          class="rounded-full border border-rule-strong px-3 py-1 text-elder-body font-bold text-elder-text"
        >
          {{ captureRecords.length }}
        </span>
      </div>
    </div>

    <div v-if="captureRecords.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <CaptureRecordDisplayCard
        v-for="(record, index) in captureRecords"
        :key="record.id"
        :record="record"
        :index="index"
        :fishName="fishName"
        :fishId="fishId"
        :canEdit="canEdit"
      />
    </div>
  </section>
</template>

<script setup>
import CaptureRecordDisplayCard from '@/Components/CaptureRecord/CaptureRecordDisplayCard.vue'

defineProps({
  fishId: { type: [Number, String], default: null },
  canEdit: { type: Boolean, default: false },
  captureRecords: { type: Array, default: () => [] },
  fishName: { type: String, default: '' },
  user: { type: Object, default: null },
})
</script>
