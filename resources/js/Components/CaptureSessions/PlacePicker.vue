<template>
  <div class="space-y-2">
    <label class="block text-sm font-medium text-gray-700">地名（可留空）</label>
    <input v-model="query" type="text" class="w-full rounded-lg border-gray-300" placeholder="輸入地名搜尋" @input="search" />
    <div v-if="suggestions.length" class="rounded-lg border bg-white divide-y">
      <button v-for="place in suggestions" :key="place.id" type="button" class="w-full text-left px-3 py-2 hover:bg-blue-50" @click="select(place)">
        {{ place.name }}<span v-if="place.tao_name" class="text-gray-500">（{{ place.tao_name }}）</span>
      </button>
    </div>
    <div v-if="query && !suggestions.length" class="rounded-lg border p-3 space-y-2">
      <p class="text-sm text-gray-600">找不到地名，可就地新增。</p>
      <input v-model="taoName" class="w-full rounded-lg border-gray-300" placeholder="族語名稱（可留空）" />
      <button type="button" class="px-3 py-2 rounded-lg bg-teal-600 text-white" @click="createPlace">新增並選取</button>
    </div>
    <p v-if="selectedName" class="text-sm text-teal-700">已選：{{ selectedName }}</p>
    <p v-if="message" role="alert" class="text-sm text-amber-700">{{ message }}</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({ modelValue: { type: [Number, String, null], default: null }, initialName: { type: String, default: '' } })
const emit = defineEmits(['update:modelValue'])
const query = ref(props.initialName)
const taoName = ref('')
const suggestions = ref([])
const selectedName = ref(props.initialName)
const message = ref('')
let timer

watch(() => props.initialName, value => { if (!query.value) query.value = value || '' })

function search() {
  emit('update:modelValue', null)
  selectedName.value = ''
  clearTimeout(timer)
  if (!query.value.trim()) { suggestions.value = []; return }
  timer = setTimeout(async () => {
    const response = await fetch(`/places/suggest?q=${encodeURIComponent(query.value)}`, { headers: { Accept: 'application/json' } })
    suggestions.value = (await response.json()).places || []
  }, 200)
}

function select(place) {
  emit('update:modelValue', place.id)
  query.value = place.name
  selectedName.value = place.name
  suggestions.value = []
  message.value = ''
}

async function createPlace() {
  const response = await fetch('/places', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
    body: JSON.stringify({ name: query.value, tao_name: taoName.value || null }),
  })
  const body = await response.json()
  if (response.status === 422 && body.existing_place) {
    message.value = body.message
    suggestions.value = [body.existing_place]
    return
  }
  if (response.ok) select(body.place)
}
</script>
