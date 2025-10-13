<template>
  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow">
    <div class="flex justify-between items-start mb-2">
      <span class="text-sm font-medium text-blue-600 bg-blue-50 px-2 py-1 rounded">
        {{ note.note_type || '一般知識' }}
      </span>
      <OverflowMenu
        :apiUrl="`/fish/${fishId}/knowledge/${note.id}`"
        :fishId="fishId.toString()"
        :editUrl="`/fish/${fishId}/knowledge/${note.id}/edit`"
        @deleted="$emit('deleted')"
      />
    </div>

    <div class="mb-3">
      <p class="text-gray-800 leading-relaxed">{{ note.note }}</p>
    </div>

    <!-- 位置資訊 -->
    <div v-if="note.locate" class="mb-2">
      <span class="text-xs font-medium text-gray-500">位置</span>
      <p class="text-sm text-gray-700">{{ note.locate }}</p>
    </div>

    <div class="text-xs text-gray-400">記錄時間: {{ formatDateTime(note.created_at) }}</div>
  </div>
</template>

<script setup>
import OverflowMenu from './OverflowMenu.vue'

const props = defineProps({
  note: Object,
  fishId: Number,
})

const emit = defineEmits(['updated', 'deleted'])

function formatDateTime(dateString) {
  return new Date(dateString).toLocaleString('zh-TW')
}
</script>
