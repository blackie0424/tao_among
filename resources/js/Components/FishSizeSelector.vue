<template>
  <div class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
    <h3 class="text-xl font-bold mb-4">選擇魚的尺寸</h3>
    <ArmSelector v-model="selectedParts" :readonly="false" />
    <div v-if="sizeSubmitError" class="text-red-600 mt-2">{{ sizeSubmitError }}</div>
    <div v-if="sizeSubmitSuccess" class="text-green-600 mt-2">尺寸新增成功！</div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import ArmSelector from '@/Components/ArmSelector.vue'

// 新增 fishSize prop，編輯模式下傳入已選尺寸
const props = defineProps({
  fishId: Number,
  fishSize: {
    type: Array,
    default: () => [],
  },
})
const emit = defineEmits(['finished'])
const selectedParts = ref([]) // ArmSelector 綁定的尺寸資料
const sizeSubmitting = ref(false)
const sizeSubmitError = ref('')
const sizeSubmitSuccess = ref(false)

// 編輯模式：有 fishSize 傳入時，還原選擇的尺寸
watch(
  () => props.fishSize,
  (newVal) => {
    if (Array.isArray(newVal) && newVal.length > 0) {
      selectedParts.value = [...newVal]
    }
  },
  { immediate: true }
)

async function submitSize() {
  if (!props.fishId || !selectedParts.value.length) {
    sizeSubmitError.value = '請選擇尺寸'
    return
  }
  sizeSubmitting.value = true
  sizeSubmitError.value = ''
  sizeSubmitSuccess.value = false
  try {
    const res = await fetch('/prefix/api/fishSize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        fish_id: props.fishId,
        parts: selectedParts.value,
      }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || '尺寸新增失敗')
    sizeSubmitSuccess.value = true
    setTimeout(() => {
      emit('finished')
    }, 1000)
  } catch (e) {
    sizeSubmitError.value = e.message || '尺寸新增失敗'
  } finally {
    sizeSubmitting.value = false
  }
}

defineExpose({ submitSize })
</script>
