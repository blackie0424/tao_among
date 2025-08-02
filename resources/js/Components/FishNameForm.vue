<template>
  <form @submit.prevent="submitFish" class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
    <div class="mb-4">
      <div class="text-green-600 mb-2">圖片已上傳，檔名：{{ uploadedFileName }}</div>
      <label for="name" class="block font-semibold mb-2">魚類名稱</label>
      <input
        type="text"
        id="name"
        v-model="fishName"
        class="w-full border rounded px-3 py-2"
        required
      />
    </div>
    <div v-if="submitError" class="text-red-600 mt-2">{{ submitError }}</div>
    <div v-if="submitSuccess" class="text-green-600 mt-2">魚類新增成功！</div>
  </form>
</template>

<script setup>
import { ref } from 'vue'
const props = defineProps({ uploadedFileName: String })
const emit = defineEmits(['submitted'])

const fishName = ref('')
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

async function submitForm() {
  if (!fishName.value || !props.uploadedFileName) return
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false
  try {
    const res = await fetch('/prefix/api/fish', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: fishName.value,
        image: props.uploadedFileName,
      }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || '新增失敗')
    submitSuccess.value = true
    fishName.value = ''
    emit('submitted', data.data.id)
  } catch (e) {
    submitError.value = e.message || '新增失敗'
  } finally {
    submitting.value = false
  }
}

defineExpose({ submitForm })
</script>
