<template>
  <section
    v-if="isEditor && (hasKnowledge || user)"
    class="border border-rule-strong bg-paper-raised p-4"
  >
    <div class="flex items-center justify-between mb-4 pb-2 border-b border-rule">
      <h2 class="font-serif text-elder-name font-bold flex items-center gap-2 text-elder-text">
        <span>📚</span> 文獻知識
      </h2>
    </div>

    <div v-if="hasKnowledge" class="space-y-6">
      <article
        v-for="group in groupedReferenceKnowledge"
        :key="group.key"
        data-testid="reference-group"
        class="overflow-hidden border border-rule-strong"
      >
        <div class="flex items-start gap-4 border-b border-rule p-4">
          <div
            data-testid="reference-group-cover"
            class="aspect-[3/4] w-32 flex-shrink-0 overflow-hidden border border-rule-strong bg-paper sm:w-40"
          >
            <LazyImage
              v-if="group.reference.image_url"
              :src="group.reference.image_url"
              :alt="group.reference.name"
              wrapperClass="h-full w-full"
              imgClass="h-full w-full object-cover"
            />
            <div
              v-else
              class="flex h-full items-center justify-center px-2 text-center text-elder-body font-medium text-elder-subtext"
            >
              暫無封面
            </div>
          </div>
          <div class="min-w-0 space-y-1">
            <h3 class="font-serif text-elder-body font-bold text-elder-text">{{ group.reference.name }}</h3>
            <p class="text-elder-body text-elder-subtext">共 {{ group.items.length }} 筆文獻知識</p>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div
            v-for="item in group.items"
            :key="item.id"
            class="border border-rule p-4"
          >
            <div class="mb-3 flex flex-wrap items-center gap-2">
              <span data-testid="reference-pages" class="text-elder-body font-medium text-elder-subtext">頁碼：{{ item.pages }}</span>
              <span
                v-if="item.tribe"
                data-testid="reference-tribe-tag"
                class="inline-flex items-center rounded-full border border-rule-strong px-2.5 py-1 text-elder-body font-medium text-elder-text"
              >
                部落：{{ item.tribe }}
              </span>
            </div>
            <div class="whitespace-pre-line leading-relaxed text-elder-body text-elder-text">
              {{ item.content }}
            </div>
            <div v-if="item.note" data-testid="reference-note" class="mt-3 text-elder-body text-elder-subtext">
              備註：{{ item.note }}
            </div>
          </div>
        </div>
      </article>
    </div>
    <div
      v-else
      data-testid="reference-empty"
      class="text-elder-body text-elder-subtext text-center py-8 border border-dashed border-rule-strong"
    >
      目前沒有文獻知識的紀錄
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { groupReferenceKnowledgeByReference } from '@/utils/referenceKnowledge'

const props = defineProps({
  referenceKnowledge: { type: Array, default: () => [] },
  isEditor: { type: Boolean, default: false },
  user: { type: Object, default: null },
})

const hasKnowledge = computed(() => props.referenceKnowledge.length > 0)
const groupedReferenceKnowledge = computed(() => groupReferenceKnowledgeByReference(props.referenceKnowledge))
</script>
