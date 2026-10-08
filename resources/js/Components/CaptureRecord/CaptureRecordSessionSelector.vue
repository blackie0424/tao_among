<template>
  <div class="mb-4" data-testid="capture-session-selector">
    <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">選擇這次記錄的情境</p>
    <p class="mb-3 text-sm text-gray-500">日期、地名、部落與方式會由情境帶入。</p>
    <div v-if="hasOptions" class="space-y-4">
      <div v-if="selectableSessions.length" class="flex flex-col gap-2">
        <button v-for="session in selectableSessions" :key="'session-' + session.id" data-testid="session-option" type="button" :class="optionClass(selectionKey === 'session-' + session.id)" @click="selectSession(session)">
          <span class="font-medium">{{ session.capture_date }}</span>
          · {{ session.place_name || session.location_hint || '未標地點' }}
          · {{ session.tribe }} · {{ session.capture_method }}
          <span class="ml-1 text-xs">（{{ session.record_count }} 筆）</span>
        </button>
      </div>
      <div v-if="legacyCombos.length">
        <p class="mb-2 text-sm font-medium text-gray-600">由舊資料建立情境</p>
        <div class="flex flex-col gap-2">
          <button v-for="combo in legacyCombos" :key="legacyKey(combo)" data-testid="legacy-combo-option" type="button" :class="optionClass(selectionKey === 'legacy-' + legacyKey(combo))" @click="selectLegacy(combo)">
            <span class="font-medium">{{ combo.capture_date }}</span>
            · {{ combo.location || '未標地點' }}
            · {{ combo.tribe }} · {{ combo.capture_method }}
            <span class="ml-1 text-xs">（{{ combo.record_count }} 筆・舊資料）</span>
          </button>
        </div>
      </div>
    </div>
    <div v-else data-testid="session-empty-state" class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
      <p class="font-medium">還沒有情境</p>
      <p class="mt-1">請先建立情境，再回來新增紀錄。</p>
      <a class="mt-2 inline-block text-blue-600 underline" href="/capture-sessions/create">建立情境</a>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  selectableSessions: { type: Array, default: () => [] },
  legacyCombos: { type: Array, default: () => [] },
})

const emit = defineEmits(['select'])
const selectionKey = ref(null)
const hasOptions = computed(() => props.selectableSessions.length > 0 || props.legacyCombos.length > 0)

function optionClass(selected) {
  return [
    'w-full rounded-lg border px-4 py-2 text-left text-sm',
    selected ? 'border-blue-600 bg-blue-100 text-blue-900' : 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100',
  ]
}

function legacyKey(combo) {
  return [combo.capture_date, combo.tribe, combo.capture_method, combo.location ?? ''].join('|')
}

function selectSession(session) {
  selectionKey.value = 'session-' + session.id
  emit('select', { session_id: session.id, legacy_combo: null })
}

function selectLegacy(combo) {
  selectionKey.value = 'legacy-' + legacyKey(combo)
  emit('select', {
    session_id: null,
    legacy_combo: {
      capture_date: combo.capture_date,
      tribe: combo.tribe,
      capture_method: combo.capture_method,
      location: combo.location,
    },
  })
}
</script>