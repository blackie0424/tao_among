<template>
  <section
    v-if="isEditor && (hasKnowledge || user)"
    class="bg-white rounded-xl shadow-sm border border-gray-200 p-4"
  >
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-100">
      <h2 class="text-xl font-bold flex items-center gap-2 text-gray-900">
        <span>📚</span> 文獻知識
      </h2>
    </div>

    <div v-if="hasKnowledge" class="space-y-4">
      <article
        v-for="item in referenceKnowledge"
        :key="item.id"
        class="bg-gray-50 rounded-xl p-5 border border-gray-200"
      >
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <span
            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800"
          >
            {{ item.reference?.name || '未指定文獻' }}
          </span>
          <span class="text-sm text-gray-500">頁碼：{{ item.pages }}</span>
        </div>
        <div class="text-gray-800 whitespace-pre-line leading-relaxed">
          {{ item.content }}
        </div>
        <div v-if="item.note" class="mt-3 text-sm text-gray-500">
          備註：{{ item.note }}
        </div>
      </article>
    </div>
    <div
      v-else
      class="text-gray-500 text-center py-8 border border-dashed border-gray-300 rounded-lg"
    >
      目前沒有文獻知識的紀錄
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  referenceKnowledge: { type: Array, default: () => [] },
  isEditor: { type: Boolean, default: false },
  user: { type: Object, default: null },
})

const hasKnowledge = computed(() => props.referenceKnowledge.length > 0)
</script>

