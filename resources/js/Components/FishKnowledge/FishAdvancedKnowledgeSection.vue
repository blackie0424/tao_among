<template>
  <section
    v-if="isEditor && (hasNotes || user)"
    class="border border-rule-strong bg-paper-raised p-4"
  >
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-rule">
      <h2 class="font-serif text-elder-name font-bold flex items-center gap-2 text-elder-text">
        <span>📖</span> 進階知識
      </h2>
    </div>

    <div v-if="hasNotes" class="space-y-8">
      <div v-for="(locates, type) in groupedNotesByTypeAndLocate" :key="type">
        <h4
          data-testid="type-tag"
          class="mb-4 inline-flex items-center rounded-full border border-rule-strong px-4 py-1 font-serif text-elder-body font-bold text-elder-text"
        >
          {{ type }}
        </h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="(notes, locate) in locates" :key="locate" class="border border-rule p-5">
            <span
              data-testid="locate-tag"
              class="inline-flex items-center px-3 py-1 rounded-full border border-rule-strong text-elder-body font-medium text-elder-text mb-3"
            >
              {{ locate }}
            </span>
            <ul class="space-y-4">
              <li v-for="note in notes" :key="note.id">
                <div class="text-elder-body text-elder-text whitespace-pre-line leading-relaxed">
                  {{ note.note }}
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <div
      v-else
      data-testid="advanced-empty"
      class="text-elder-body text-elder-subtext text-center py-8 border border-dashed border-rule-strong"
    >
      目前沒有進階地方知識的紀錄
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useFishNotes } from '@/composables/useFishNotes.js'

const props = defineProps({
  fishNotes: { type: Object, default: () => ({}) },
  isEditor: { type: Boolean, default: false },
  user: { type: Object, default: null },
})

const fishNotesRef = computed(() => props.fishNotes)
const { hasNotes, groupedNotesByTypeAndLocate } = useFishNotes(fishNotesRef)
</script>
