<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar :goBack="goBack" title="編輯魚類名稱" :showSubmit="true" :submitNote="submitEdit" />
    <div class="pt-16">
      <FishNameForm :fishId="fish.id" :fishNameInit="fish.name" ref="fishNameFormRef" />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import FishNameForm from '../Components/FishNameForm.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  fish: Object,
})

const fishNameFormRef = ref(null)

function goBack() {
  window.history.length > 1 ? window.history.back() : router.visit('/fishs')
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitEdit() {
  if (fishNameFormRef.value) {
    fishNameFormRef.value.submitEditForm()
  }
}
</script>
