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

    <div class="mb-4" v-if="!props.fishId">
      <label for="captureMethod" class="block font-semibold mb-2">捕獲方式</label>
      <select
        id="captureMethod"
        v-model="selectedCaptureMethod"
        class="w-full border rounded px-3 py-2"
        required
      >
        <option
          v-for="(label, key) in props.captureMethods"
          :key="key"
          :value="key"
        >
          {{ label }}
        </option>
      </select>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { markFishStale } from '@/utils/fishListCache'

// 建立模式用 uploadedFileName，編輯模式用 fishId、fishNameInit
const props = defineProps({
  uploadedFileName: String, // 建立模式用
  fishId: { type: [String, Number], default: null }, // 編輯模式用
  fishNameInit: { type: String, default: '' }, // 編輯模式用
  captureMethods: { type: Object, default: () => ({}) }, // 建立模式用
})
const emit = defineEmits(['submitted'])

const fishName = ref('')
const selectedCaptureMethod = ref('mamasil')
const submitting = ref(false)

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

  // 使用 Inertia router.post 發送到 /fish（由後端處理 flash 與導向）
  router.post(
    '/fish',
    {
      name: nameToSend,
      image: props.uploadedFileName,
      capture_method: selectedCaptureMethod.value,
    },
    {
      onSuccess: (page) => {
        fishName.value = ''
        const fishId = page.props.fish?.id
        // 通知上層元件
        emit('submitted', fishId ?? null)
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
  const requestUrl = `/fish/${props.fishId}/name`

  submitting.value = true

  // 使用 Inertia router.put 發送到 /fish/${props.fishId}/name
  // 後端會 redirect 並帶 flash message，Inertia 自動處理導向
  router.put(
    requestUrl,
    { name: nameToSend },
    {
      onSuccess: () => {
        // 標記此魚類需要在 Fishs 頁面更新
        markFishStale(props.fishId)
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
