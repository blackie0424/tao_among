<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="編輯魚類尺寸" :showSubmit="true" :submitNote="submitEdit" />
    <div class="pt-16">
      <FishSizeSelector :fishSize="fishSize.parts" />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import FishSizeSelector from '../Components/FishSizeSelector.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  fishSize: Object, // 從父元件或頁面取得 fish 物件，內含 size 屬性
})

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

function submitEdit() {
  // 這裡可以觸發 FishSizeForm 的提交邏輯
  if (fishSizeFormRef.value) {
    fishSizeFormRef.value.submitEditForm()
  }
}
</script>
