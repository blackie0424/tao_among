<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      title="新增地方知識"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16">
      <TribalClassificationForm
        :tribes="tribes"
        :foodCategories="foodCategories"
        :processingMethods="processingMethods"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.image"
        @submitted="onClassificationSubmitted"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import TribalClassificationForm from '../Components/TribalClassificationForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  foodCategories: Array,
  processingMethods: Array,
})

const formRef = ref(null)

function goBack() {
  router.visit(`/fish/${props.fish.id}/tribal-classifications`)
}

function onClassificationSubmitted() {
  // 返回地方知識列表頁面
  router.visit(`/fish/${props.fish.id}/tribal-classifications`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
