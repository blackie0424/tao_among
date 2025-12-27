<template>
  <div class="container mx-auto p-4 relative">
    <TopNavBar
      :goBack="goBack"
      :fishName="fish.name"
      title="新增捕獲紀錄"
      :showSubmit="true"
      :submitNote="submitForm"
      :submitLabel="submitLabel"
      :steps="steps"
      :currentStep="currentStepVal"
      :showLoading="showLoadingVal"
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
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  prefill_image: {
    type: String,
    default: '',
  },
})

const formRef = ref(null)

const submitLabel = computed(() => {
  const step = currentStepVal.value
  if (step === 1) return '下一步'
  if (step === 2) return '下一步'
  return '送出'
})

const steps = ['上傳照片', '填寫部落/地點/時間', '選擇捕獲方法']

// 安全地從 child ref 或原始值拆箱 step
const currentStepVal = computed(() => {
  const f = formRef.value
  if (!f) return 1
  const s = f.step
  if (s == null) return 1
  // 如果 child 暴露的是 ref，則取 .value，否則直接回傳
  if (typeof s === 'object' && 'value' in s) return s.value ?? 1
  return s
})

const showLoadingVal = computed(() => {
  const f = formRef.value
  if (!f) return false
  const up = f.uploading
  const proc = f.processing
  const upVal = up && typeof up === 'object' && 'value' in up ? up.value : up
  const procVal = proc && typeof proc === 'object' && 'value' in proc ? proc.value : proc
  return !!(upVal || procVal)
})

function goBack() {
  // 如果子元件在中間步驟，先回到上一步；否則回到列表
  const step = formRef.value?.step ?? 1
  if (step > 1 && formRef.value?.prevStep) {
    formRef.value.prevStep()
    return
  }
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

function onRecordSubmitted() {
  // 返回捕獲紀錄列表頁面
  router.visit(`/fish/${props.fish.id}/capture-records`)
}

// 整合送出到 TopNavBar 的 @submit 事件
function submitForm() {
  if (!formRef.value) return
  const step = formRef.value.step ?? 1
  // step 控制：呼叫 child 暴露的方法
  if (step === 1 && formRef.value.submitForm) {
    // for step1 we want to trigger upload and next
    formRef.value.submitForm()
    return
  }
  if (step === 2 && formRef.value.nextStep) {
    formRef.value.nextStep()
    return
  }
  if (step === 3 && formRef.value.finalSubmit) {
    formRef.value.finalSubmit()
    return
  }
}

onMounted(() => {
  // 如果有預填圖片，通知子元件
  if (props.prefill_image && formRef.value) {
    formRef.value.setPrefillImage(props.prefill_image)
  }
})
</script>
