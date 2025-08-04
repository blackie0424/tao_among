<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="編輯魚類尺寸" :showSubmit="true" :submitNote="submitEdit" />
    <div class="pt-16">
      <FishSizeSelector :fishSize="props.fishSize.parts" v-model="selectedParts" />
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

// 只在初始化時設定 selectedParts，之後都以 v-model 為主，不再 watch 外部資料
const selectedParts = ref(props.fishSize.parts ? [...props.fishSize.parts] : [])

const submitError = ref('')
const submitSuccess = ref(false)

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

async function submitEdit() {
  submitError.value = ''
  submitSuccess.value = false
  console.log(selectedParts.value)
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
    // 可依需求導頁
    router.visit(`/fish/${props.fishSize.fish_id}`)
  } catch (e) {
    submitError.value = e.message || '尺寸更新失敗'
  }
}
</script>
