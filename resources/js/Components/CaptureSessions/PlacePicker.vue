<template>
  <div class="space-y-2">
    <label class="mb-1 block text-sm font-medium text-gray-700">地名（可留空）</label>
    <input v-model="query" type="text" :disabled="!tribe" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100" :placeholder="tribe ? '輸入地名搜尋' : '請先選擇部落'" @input="search" />
    <div v-if="suggestions.length" class="divide-y rounded-lg border bg-white">
      <button v-for="place in suggestions" :key="place.id" type="button" class="w-full px-3 py-2 text-left hover:bg-blue-50" @click="select(place)">
        {{ place.name }}<span v-if="place.tao_name" class="text-gray-500">（{{ place.tao_name }}）</span>
        <span v-if="place.tribe === null" class="ml-2 text-xs text-gray-500">未指定部落</span>
      </button>
    </div>
    <p v-if="hasSelection" data-testid="selected-place" class="text-sm text-teal-700">已選：{{ selectedName }}</p>
    <p v-else-if="query.trim()" data-testid="provisional-place-hint" class="text-sm text-amber-700">將建立待確認地名：{{ query.trim() }}（管理者之後確認）</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: [Number, String, null], default: null },
  placeName: { type: String, default: '' },
  tribe: { type: String, default: '' },
  initialName: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'update:placeName'])
const query = ref(props.initialName || props.placeName)
const suggestions = ref([])
const selectedName = ref(props.initialName)
const hasSelection = ref(Boolean(props.modelValue && props.initialName))
let timer

watch(() => props.initialName, value => {
  if (!query.value) query.value = value || ''
})

watch(() => props.tribe, (value, previous) => {
  if (value === previous) return
  emit('update:modelValue', null)
  emit('update:placeName', query.value.trim())
  selectedName.value = ''
  hasSelection.value = false
  suggestions.value = []
  if (query.value.trim()) search()
})

function search() {
  emit('update:modelValue', null)
  emit('update:placeName', query.value.trim())
  selectedName.value = ''
  hasSelection.value = false
  clearTimeout(timer)
  if (!props.tribe || !query.value.trim()) {
    suggestions.value = []
    return
  }
  timer = setTimeout(async () => {
    const params = new URLSearchParams({ q: query.value, tribe: props.tribe })
    const response = await fetch(`/places/suggest?${params.toString()}`, { headers: { Accept: 'application/json' } })
    suggestions.value = (await response.json()).places || []
  }, 200)
}

function select(place) {
  emit('update:modelValue', place.id)
  emit('update:placeName', '')
  query.value = place.name
  selectedName.value = place.name
  suggestions.value = []
  hasSelection.value = true
}
</script>
