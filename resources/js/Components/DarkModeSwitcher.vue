<template>
  <button
    v-if="showButton"
    @click="toggleDarkMode"
    class="fixed top-4 right-4 z-50 bg-white/90 dark:bg-gray-800/90 border border-gray-300 dark:border-gray-700 shadow px-4 py-2 rounded"
  >
    顯示模式：{{ modeLabel }}
  </button>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  showButton: { type: Boolean, default: true },
})

const mode = ref('auto') // 'auto' | 'light' | 'dark'

const modeLabel = computed(() => {
  if (mode.value === 'auto') return '自動'
  if (mode.value === 'dark') return '深色'
  return '淺色'
})

function applyTheme(selectedMode) {
  if (selectedMode === 'auto') {
    const hour = new Date().getHours()
    if (hour >= 18 || hour < 6) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  } else if (selectedMode === 'dark') {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

function toggleDarkMode() {
  if (mode.value === 'auto') {
    mode.value = 'light'
  } else if (mode.value === 'light') {
    mode.value = 'dark'
  } else {
    mode.value = 'auto'
  }
  localStorage.setItem('theme', mode.value)
  applyTheme(mode.value)
}

onMounted(() => {
  mode.value = localStorage.getItem('theme') || 'auto'
  applyTheme(mode.value)
})
</script>
