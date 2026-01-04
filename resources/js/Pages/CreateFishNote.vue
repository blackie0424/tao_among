<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :title="`新增${fish.name}的進階知識`"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="'儲存'"
    />
    <div class="pt-16">
      <FishNoteForm
        :tribes="tribes"
        :noteTypes="noteTypes"
        :fishId="fish.id"
        :fishName="fish.name"
        :fishImage="fish.image"
        @submitted="onNoteSubmitted"
        ref="formRef"
      />
    </div>
  </div>
</template>

<script setup>
import TopNavBar from '../Components/Global/TopNavBar.vue'
import FishNoteForm from '../Components/FishNoteForm.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  noteTypes: Array,
})

const formRef = ref(null)

function goBack() {
  router.visit(`/fish/${props.fish.id}/knowledge-list`)
}

function onNoteSubmitted() {
  // 返回進階知識列表頁面
  router.visit(`/fish/${props.fish.id}/knowledge-list`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (formRef.value) {
    formRef.value.submitForm()
  }
}
</script>
