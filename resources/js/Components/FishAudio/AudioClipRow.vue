<template>
  <div class="flex items-center gap-3 border-t border-rule py-3" data-testid="audio-clip">
    <Volume :audioUrl="audio.url" :audioId="audio.id" class="!ml-0 border border-brand" />
    <div class="min-w-0 flex-1">
      <div class="text-elder-body font-bold" :class="audio.locate ? 'text-elder-text capitalize' : 'text-elder-subtext'">
        {{ audio.locate || '未標部落' }}
      </div>
      <div class="text-elder-aux text-elder-subtext">
        <span v-if="durationLabel">{{ durationLabel }}・</span>錄音者：{{ audio.recorder_name || '未記錄' }}
        <span v-if="isCurrent">・魚頁目前使用</span>
      </div>
    </div>
    <slot name="actions">
      <Link
        :href="`/fish/${fishId}/audio/${audio.id}/edit`"
        class="inline-flex min-h-touch-secondary items-center text-elder-body text-brand underline"
      >
        編輯
      </Link>
    </slot>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Volume from '@/Components/UI/Volume.vue'

const props = defineProps({
  audio: { type: Object, required: true },
  fishId: { type: [Number, String], required: true },
  isCurrent: { type: Boolean, default: false },
})

// 長度為空或 0 時不顯示（不顯示「0:00」）
const durationLabel = computed(() => {
  const seconds = Math.round((props.audio.duration || 0) / 1000)
  if (seconds <= 0) return ''
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
})
</script>
