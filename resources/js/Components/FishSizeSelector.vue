<template>
  <div class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
    <h3 class="text-xl font-bold mb-4">選擇魚的尺寸</h3>
    <ArmSelector v-model="selectedParts" :readonly="false" />
    <button
      class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 mt-4"
      @click="submitFishSize"
      :disabled="sizeSubmitting"
    >
      <span v-if="sizeSubmitting">送出中...</span>
      <span v-else>送出尺寸</span>
    </button>
    <div v-if="sizeSubmitError" class="text-red-600 mt-2">{{ sizeSubmitError }}</div>
    <div v-if="sizeSubmitSuccess" class="text-green-600 mt-2">尺寸新增成功！</div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import ArmSelector from '@/Components/ArmSelector.vue'
const props = defineProps({ fishId: Number })
const emit = defineEmits(['finished'])
const selectedParts = ref([])
const sizeSubmitting = ref(false)
const sizeSubmitError = ref('')
const sizeSubmitSuccess = ref(false)

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
