<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="編輯魚類尺寸" :showSubmit="true" :submitNote="submitEdit" />
    <div class="pt-16">
      <FishSizeSelector
        :fishId="props.fishSize.fish_id"
        :fishSize="props.fishSize.parts"
        :mode="'edit'"
        :modelValue="selectedParts"
        @update:modelValue="(val) => (selectedParts = val)"
      />
    </div>
    <div v-if="submitError" class="text-red-600 mt-2 text-center">{{ submitError }}</div>
    <div v-if="submitSuccess" class="text-green-600 mt-2 text-center">尺寸更新成功！</div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import FishSizeSelector from '../Components/FishSizeSelector.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  fishSize: Object, // 內含 fish_id, parts
})

// 編輯時預設為舊資料，使用者可再選擇
const selectedParts = ref(props.fishSize.parts ? [...props.fishSize.parts] : [])

const submitError = ref('')
const submitSuccess = ref(false)

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

async function submitEdit() {
  submitError.value = ''
  submitSuccess.value = false
  try {
    const res = await fetch(`/prefix/api/fish/${props.fishSize.fish_id}/editSize`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        parts: selectedParts.value, // 送出使用者最新選擇的部分
      }),
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || '尺寸更新失敗')
    submitSuccess.value = true
    router.visit(`/fish/${props.fishSize.fish_id}`)
  } catch (e) {
    submitError.value = e.message || '尺寸更新失敗'
  }
}
</script>
