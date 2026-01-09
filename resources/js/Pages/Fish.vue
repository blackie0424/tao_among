<template>
  <Head :title="`${fish.name}的基本資料`" />

  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 items-start">

    <!-- Left Column: Tribal Classification Summary -->
    <div class="space-y-8">
      <TribalClassificationSummary :classifications="tribalClassifications" :fishId="fish.id" />
    </div>

    <!-- Right Column: Advanced Knowledge / Notes Section -->
    <div class="space-y-8">
      <div class="rounded-2xl bg-white shadow-sm border border-stone-200 p-6">
          <div class="flex items-center justify-between mb-6 pb-4 border-b border-stone-100">
              <h3 class="text-2xl font-serif font-bold text-stone-800 flex items-center gap-2">
                  <span>進階知識</span>
                  <span class="text-sm font-sans font-normal text-stone-400 bg-stone-50 px-2 py-0.5 rounded-full border border-stone-100">Notes</span>
              </h3>
          </div>

          <div v-if="Object.keys(groupedNotes).length" class="space-y-8">
              <div v-for="(items, type) in groupedNotes" :key="type" class="relative pl-4 border-l-2 border-stone-200">
                  <h4 class="text-lg font-bold text-stone-700 mb-4 -ml-4 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-stone-300 ring-4 ring-white"></span>
                        {{ type }}
                        <span class="text-stone-400 text-sm font-normal">({{ items.length }})</span>
                  </h4>

                  <ul class="space-y-4">
                      <li v-for="note in items" :key="note.id" class="group">
                            <div class="bg-stone-50 hover:bg-stone-100 rounded-xl p-4 transition-colors duration-200">
                              <div class="flex flex-col gap-2">
                                  <!-- Location Badge -->
                                  <div class="flex items-center gap-2">
                                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-white text-stone-600 border border-stone-200 shadow-sm">
                                          📍 {{ getLocateLabel(note.locate) }}
                                      </span>
                                      <span class="text-xs text-stone-400">{{ formatDate(note.created_at) }}</span>
                                  </div>

                                  <!-- Note Content -->
                                  <div class="text-base text-stone-800 leading-relaxed font-serif">
                                      {{ note.note }}
                                  </div>
                              </div>
                            </div>
                      </li>
                  </ul>
              </div>
          </div>

          <div v-else class="text-center py-12 bg-stone-50 rounded-xl border border-dashed border-stone-200">
              <p class="text-stone-400">尚無進階筆記</p>
          </div>
      </div>
    </div>
  </div>
</template>

<script>
import FishLayout from '@/Layouts/FishLayout.vue'

export default {
  layout: FishLayout,
}
</script>

<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import TribalClassificationSummary from '@/Components/TribalClassificationSummary.vue'

const props = defineProps({
  fish: Object,
  initialLocate: String,
  tribalClassifications: {
    type: Array,
    default: () => [],
  },
  captureRecords: {
    type: Array,
    default: () => [],
  },
  fishNotes: {
    type: Object,
    default: () => ({}),
  },
})

// Check if fishNotes is provided directly (from updated backend).
// If not, fallback to grouping fish.notes on the client side.
const groupedNotes = computed(() => {
    if (props.fishNotes && Object.keys(props.fishNotes).length > 0) {
        return props.fishNotes;
    }

    // Fallback: Group by note_type manually from props.fish.notes
    const notes = props.fish?.notes || [];
    if (!Array.isArray(notes)) return {};

    return notes.reduce((acc, note) => {
        const type = note.note_type || '其他';
        if (!acc[type]) acc[type] = [];
        acc[type].push(note);
        return acc;
    }, {});
})

const locates = [
  { value: 'iraraley', label: 'Iraraley' },
  { value: 'iranmeylek', label: 'Iranmeylek' },
  { value: 'ivalino', label: 'Ivalino' },
  { value: 'imorod', label: 'Imorod' },
  { value: 'iratay', label: 'Iratay | Iratey' },
  { value: 'yayo', label: 'Yayo' },
]

function getLocateLabel(value) {
    const found = locates.find(l => l.value === value)
    return found ? found.label : value
}

function formatDate(dt) {
  if (!dt) return ''
  try {
    return new Date(dt).toLocaleDateString()
  } catch (e) {
    return dt
  }
}
</script>
