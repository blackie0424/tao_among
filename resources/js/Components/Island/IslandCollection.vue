<template>
  <!-- #232 我的圖鑑：在海邊收集、自己記過的魚，依收集時間編第 N 條；每條列自己在自己部落記的三題。
       「改我的記錄」發出 edit（魚編號），「關閉」或 Esc 發出 close -->
  <div
    data-testid="collection"
    role="dialog"
    aria-modal="true"
    aria-labelledby="island-collection-title"
    class="fixed inset-0 z-30 flex items-end justify-center bg-black/40 p-3 min-[801px]:items-center"
    @keydown.esc="emit('close')"
  >
    <div class="max-h-full w-full max-w-xl overflow-y-auto rounded-island bg-island-sea p-4">
      <div class="flex items-center justify-between gap-3">
        <h2 id="island-collection-title" class="text-elder-title font-extrabold">
          已收集 {{ entries.length }} 條
        </h2>
        <button ref="closeButton" type="button" :class="secondaryButton" @click="emit('close')">
          關閉
        </button>
      </div>

      <p v-if="entries.length === 0" class="mt-4 text-elder-body">還沒有魚，去海邊找一條</p>
      <ul v-else class="mt-3 space-y-2">
        <li
          v-for="entry in entries"
          :key="entry.fish_id"
          data-testid="collection-card"
          class="rounded-island bg-white p-3"
        >
          <div class="flex gap-3">
            <img
              :src="entry.fish.image_url"
              :alt="`第 ${entry.number} 條`"
              width="96"
              height="72"
              class="aspect-[4/3] w-24 flex-none rounded-island bg-island-line object-cover"
            />
            <div class="min-w-0">
              <p class="text-elder-body font-extrabold text-island-accent">
                第 {{ entry.number }} 條
              </p>
              <dl class="text-elder-aux">
                <div v-for="(label, key) in LABELS" :key="key" class="flex gap-2">
                  <dt class="text-island-muted">{{ label }}</dt>
                  <dd class="font-bold">{{ entry.my[key] || NOT_RECORDED }}</dd>
                </div>
              </dl>
            </div>
          </div>
          <button
            type="button"
            :class="[secondaryButton, 'mt-2 w-full']"
            @click="emit('edit', entry.fish_id)"
          >
            改我的記錄
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { NOT_RECORDED } from '@/island3d/fishTalk'

const props = defineProps({
  // 圖鑑（collection）：[{ fish_id, number, my }]，依編號
  collection: { type: Array, required: true },
  // 魚編號 → 魚（照片）
  fishById: { type: Map, required: true },
})
const emit = defineEmits(['close', 'edit'])

const LABELS = { name: '叫法', food_category: '分類', processing_method: '處理' }
const secondaryButton =
  'flex min-h-touch-primary items-center justify-center rounded-island border-2 border-island-line bg-white px-4 text-elder-body font-bold'
const closeButton = ref(null)

// 魚清單裡找不到的（軟刪除後還沒重新整理）不列
const entries = computed(() =>
  props.collection
    .map((entry) => ({ ...entry, fish: props.fishById.get(entry.fish_id) }))
    .filter((entry) => entry.fish)
)

onMounted(() => closeButton.value?.focus())
</script>
