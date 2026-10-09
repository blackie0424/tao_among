<template>
  <div class="mb-4" data-testid="capture-session-selector">
    <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">選擇這次記錄的情境</p>
    <p class="mb-3 text-sm text-gray-500">日期、地名、部落與方式會由情境帶入。</p>

    <button type="button" data-testid="open-inline-session-form" class="mb-3 rounded-lg border border-teal-600 px-4 py-2 text-sm font-medium text-teal-700 hover:bg-teal-50" @click="openInlineForm">
      ＋新增情境
    </button>

    <form v-if="isInlineFormOpen" data-testid="inline-session-form" class="mb-4 space-y-4 rounded-lg border border-teal-200 bg-teal-50 p-4" @submit.prevent="createSession">
      <SessionFields
        :form="inlineForm"
        :errors="formErrors"
        :tribes="tribes"
        :capture-methods="captureMethods"
        :disabled="processing"
      />
      <p v-if="firstError" role="alert" class="text-sm text-red-600">{{ firstError }}</p>
      <div class="flex gap-2">
        <button type="submit" data-testid="submit-inline-session" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" :disabled="processing">
          {{ processing ? '建立中…' : '建立情境' }}
        </button>
        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700" :disabled="processing" @click="isInlineFormOpen = false">取消</button>
      </div>
    </form>

    <div v-if="hasOptions" class="space-y-4">
      <div v-if="allSelectableSessions.length" class="flex flex-col gap-2">
        <button v-for="session in allSelectableSessions" :key="'session-' + session.id" data-testid="session-option" type="button" :class="optionClass(selectionKey === 'session-' + session.id)" @click="selectSession(session)">
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
      <p class="mt-1">可直接新增情境後繼續記錄。</p>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import SessionFields from '@/Components/CaptureSessions/SessionFields.vue'
import { apiFetch } from '@/utils/apiFetch'
import { formatLocalDate } from '@/utils/localDate'
import { firstValidationError } from '@/utils/validationErrors'

const props = defineProps({
  selectableSessions: { type: Array, default: () => [] },
  legacyCombos: { type: Array, default: () => [] },
  tribes: { type: Array, default: () => [] },
  captureMethods: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['select'])
const selectionKey = ref(null)
const isInlineFormOpen = ref(false)
const processing = ref(false)
const formErrors = ref({})
const createdSessions = ref([])
const inlineForm = reactive({
  capture_date: formatLocalDate(),
  tribe: '',
  capture_method: '',
  place_id: null,
  place_name: '',
  notes: '',
})
const allSelectableSessions = computed(() => [...createdSessions.value, ...props.selectableSessions])
const hasOptions = computed(() => allSelectableSessions.value.length > 0 || props.legacyCombos.length > 0)
const firstError = computed(() => firstValidationError(formErrors.value))

function optionClass(selected) {
  return [
    'w-full rounded-lg border px-4 py-2 text-left text-sm',
    selected ? 'border-blue-600 bg-blue-100 text-blue-900' : 'border-blue-300 bg-blue-50 text-blue-800 hover:bg-blue-100',
  ]
}

function openInlineForm() {
  formErrors.value = {}
  isInlineFormOpen.value = true
}

async function createSession() {
  if (processing.value) return

  processing.value = true
  formErrors.value = {}
  try {
    const response = await apiFetch('/capture-sessions', {
      method: 'POST',
      body: JSON.stringify(inlineForm),
    })
    const payload = await response.json()

    if (!response.ok) {
      formErrors.value = payload.errors || { form: payload.message || '建立情境失敗' }
      return
    }

    createdSessions.value.unshift(payload.session)
    selectSession(payload.session)
    isInlineFormOpen.value = false
  } catch {
    formErrors.value = { form: '建立情境失敗，請稍後再試' }
  } finally {
    processing.value = false
  }
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
