<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      title="新增捕獲紀錄"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16">
      <CaptureRecordForm
        :tribes="tribes"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.image"
        @submitted="onRecordSubmitted"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import CaptureRecordForm from '../Components/CaptureRecordForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
})

const formRef = ref(null)

function goBack() {
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

function onRecordSubmitted() {
  // 返回捕獲紀錄列表頁面
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
