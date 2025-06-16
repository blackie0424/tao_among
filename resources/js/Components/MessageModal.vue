<template>
  <div
    v-if="visible"
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50"
  >
    <div class="bg-white rounded-lg shadow-lg p-6 w-80 text-center">
      <div :class="iconClass" class="text-4xl mb-2">
        <span v-if="type === 'success'">✅</span>
        <span v-else-if="type === 'error'">❌</span>
        <span v-else>ℹ️</span>
      </div>
      <div class="mb-4 text-lg">{{ message }}</div>
      <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" @click="onConfirm">
        確認
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  message: String,
  type: {
    type: String,
    default: 'info', // 'success' | 'error' | 'info'
  },
  redirect: String, // 可選，若要跳轉
})

const emit = defineEmits(['update:modelValue'])

const visible = ref(props.modelValue)

watch(
  () => props.modelValue,
  (val) => {
    visible.value = val
  }
)

function onConfirm() {
  emit('update:modelValue', false)
  if (props.redirect) {
    window.location.href = props.redirect
  }
}

const iconClass = computed(() => {
  if (props.type === 'success') return 'text-green-500'
  if (props.type === 'error') return 'text-red-500'
  return 'text-blue-500'
})
</script>
