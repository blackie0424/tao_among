<template>
  <form @submit.prevent="submitFish" class="bg-white p-6 rounded shadow-md max-w-md mx-auto">
    <div class="mb-4">
      <!-- 只在建立模式顯示圖片檔名 -->
      <div v-if="!props.fishId && uploadedFileName" class="text-green-600 mb-2">
        圖片已上傳，檔名：{{ uploadedFileName }}
      </div>
      <label for="name" class="block font-semibold mb-2">魚類名稱</label>
      <input
        type="text"
        id="name"
        v-model="fishName"
        :placeholder="placeholderText"
        :class="['w-full border rounded px-3 py-2', fishName ? 'text-black' : 'text-gray-400']"
        required
      />
    </div>
    <div v-if="submitError" class="text-red-600 mt-2">{{ submitError }}</div>
    <div v-if="submitSuccess" class="text-green-600 mt-2">魚類新增成功！</div>
    <div v-if="submitEditSuccess" class="text-green-600 mt-2">魚類更新成功！</div>
  </form>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

// 建立模式用 uploadedFileName，編輯模式用 fishId、fishNameInit
const props = defineProps({
  uploadedFileName: String, // 建立模式用
  fishId: { type: [String, Number], default: null }, // 編輯模式用
  fishNameInit: { type: String, default: '' }, // 編輯模式用
})
const emit = defineEmits(['submitted'])

const fishName = ref('')
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)
const submitEditSuccess = ref(false)

const placeholderText = computed(() => (fishName.value ? '' : '我不知道'))

onMounted(() => {
  // 編輯模式預設帶入名稱
  if (props.fishNameInit) {
    fishName.value = props.fishNameInit
  }
})

// 建立模式：送出新增魚類資訊
async function submitForm() {
  // 如果有 fishId，代表是編輯模式，請用 submitEditForm
  if (props.fishId) return

  const nameToSend = fishName.value || '我不知道'
  if (!props.uploadedFileName) return
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false

  // 使用 Inertia router.post 發送到 /fish/create（由後端處理 flash 與導向）
  router.post(
    '/fish',
    { name: nameToSend, image: props.uploadedFileName },
    {
      headers: { Accept: 'application/json' },
      // 成功時（後端可回傳 props.data.id 或直接 redirect）
      onSuccess: (fish) => {
        submitSuccess.value = true
        fishName.value = ''
        // 若後端回傳 id 在 props，主動導向；否則後端若 redirect，Inertia 已處理導向
        const fishId = fish.props.fish.id
        // 通知上層（仍 emit），讓上層元件也能反應
        emit('submitted', fishId ?? null)
      },
      onError: (errors) => {
        submitError.value = errors?.message || '新增失敗'
      },
      onFinish: () => {
        submitting.value = false
      },
    }
  )
}

// 編輯模式：送出更新魚類名稱
async function submitEditForm() {
  // 只有編輯模式才會執行
  if (!props.fishId) return

  const nameToSend = fishName.value || '我不知道'
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false
  submitEditSuccess.value = false
  // 使用 Inertia router.put 發送到 /fish/${props.fishId}/name（由後端處理 flash 與導向）
  router.put(
    `/fish/${props.fishId}/name`,
    { name: nameToSend },
    {
      headers: { Accept: 'application/json' },
      // 成功時（後端可回傳 props.data.id 或直接 redirect）
      onSuccess: (fish) => {
        submitEditSuccess.value = true
        fishName.value = ''
        // 若後端回傳 id 在 props，主動導向；否則後端若 redirect，Inertia 已處理導向
        const fishId = fish.props.fish.id
        // 通知上層（仍 emit），讓上層元件也能反應
        emit('submitted', fishId ?? null)
      },
      onError: (errors) => {
        submitError.value = errors?.message || '更新失敗'
      },
      onFinish: () => {
        submitting.value = false
      },
    }
  )
}

// 只 expose 兩個函式，避免混用
defineExpose({ submitForm, submitEditForm })
</script>
