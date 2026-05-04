<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      :fishName="fish.name"
      title="批次新增捕獲紀錄"
      :showSubmit="canSubmit"
      :submitNote="handleSubmit"
      :submitLabel="submitLabel"
      :showLoading="isSubmitting"
    />

    <div class="pt-16 space-y-6 max-w-2xl mx-auto">
      <!-- 魚類資訊 -->
      <div class="flex items-center gap-3 p-3 bg-white rounded-xl shadow-sm border border-gray-100">
        <div
          class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 border border-gray-200"
        >
          <img
            :src="fish.display_image_url || fish.image_url"
            :alt="fish.name"
            class="w-full h-full object-contain"
          />
        </div>
        <div>
          <p class="font-semibold text-gray-900">{{ fish.name }}</p>
          <p class="text-xs text-gray-500">批次新增多筆捕獲紀錄</p>
        </div>
      </div>

      <!-- Step 1：選擇照片 -->
      <section v-if="step === 1" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">
          第一步：選擇照片
          <span class="text-sm font-normal text-gray-500 ml-1">（最多 {{ maxFiles }} 張）</span>
        </h2>
        <BatchCaptureImageUploader
          :maxFiles="maxFiles"
          :isLineApp="isLineApp"
          ref="uploaderRef"
          @uploaded="onUploaded"
          @upload-error="onUploadError"
        />
        <p v-if="uploadError" class="mt-3 text-sm text-red-600">{{ uploadError }}</p>
      </section>

      <!-- Step 2：共用捕獲資訊 -->
      <section v-if="step === 2" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">第二步：填寫共用捕獲資訊</h2>
        <p class="text-sm text-gray-500 mb-4">
          以下資訊將套用至本批次所有
          <span class="font-semibold text-gray-700">{{ uploadedFilenames.length }}</span>
          張照片
        </p>

        <!-- 過去捕獲資訊選擇器 -->
        <CaptureRecordSessionSelector
          v-if="sessionSelectorVisible"
          :sessions="recent_sessions"
          @select="onSessionSelect"
        />

        <div v-if="!sessionSelectorVisible" class="space-y-4">
          <!-- 部落 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              部落 <span class="text-red-500">*</span>
            </label>
            <select
              v-model="sharedForm.tribe"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">請選擇部落</option>
              <option v-for="tribe in tribes" :key="tribe" :value="tribe">{{ tribe }}</option>
            </select>
            <p v-if="formErrors.tribe" class="mt-1 text-sm text-red-600">{{ formErrors.tribe }}</p>
          </div>

          <!-- 捕獲地點 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              捕獲地點 <span class="text-red-500">*</span>
            </label>
            <input
              v-model="sharedForm.location"
              type="text"
              placeholder="請輸入捕獲地點"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="formErrors.location" class="mt-1 text-sm text-red-600">
              {{ formErrors.location }}
            </p>
          </div>

          <!-- 捕獲日期 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              捕獲日期 <span class="text-red-500">*</span>
            </label>
            <input
              v-model="sharedForm.capture_date"
              type="date"
              :max="today"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="formErrors.capture_date" class="mt-1 text-sm text-red-600">
              {{ formErrors.capture_date }}
            </p>
          </div>

          <!-- 捕獲方式 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              捕獲方式 <span class="text-red-500">*</span>
            </label>
            <select
              v-model="sharedForm.capture_method"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">請選擇捕獲方式</option>
              <option v-for="(label, value) in capture_methods" :key="value" :value="value">
                {{ label }}
              </option>
            </select>
            <p v-if="formErrors.capture_method" class="mt-1 text-sm text-red-600">
              {{ formErrors.capture_method }}
            </p>
          </div>

          <!-- 備註（選填） -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">備註（選填）</label>
            <textarea
              v-model="sharedForm.notes"
              rows="2"
              placeholder="相關備註"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
        </div>
      </section>

      <!-- Step 3：送出進度 -->
      <section v-if="step === 3" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">第三步：新增中</h2>
        <ul class="space-y-2">
          <li
            v-for="(result, index) in submitResults"
            :key="index"
            class="flex items-center gap-3 text-sm"
          >
            <!-- 成功 -->
            <span v-if="result.status === 'done'" class="text-green-600 flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              照片 {{ index + 1 }} 新增成功
            </span>
            <!-- 進行中 -->
            <span
              v-else-if="result.status === 'pending'"
              class="text-blue-600 flex items-center gap-1"
            >
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                />
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                />
              </svg>
              照片 {{ index + 1 }} 新增中...
            </span>
            <!-- 失敗 -->
            <span v-else class="text-red-600 flex items-center gap-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              照片 {{ index + 1 }} 失敗：{{ result.error }}
            </span>
          </li>
        </ul>

        <!-- 全部完成 -->
        <div v-if="allDone" class="mt-4 p-3 bg-green-50 rounded-lg text-sm text-green-800">
          全部 {{ uploadedFilenames.length }} 筆捕獲紀錄新增完成！
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import FormActionBar from '@/Components/Global/FormActionBar.vue'
import BatchCaptureImageUploader from '@/Components/CaptureRecord/BatchCaptureImageUploader.vue'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  capture_methods: [Array, Object],
  upload_limits: {
    type: Object,
    default: () => ({ max_files_desktop: 10, max_files_mobile: 5 }),
  },
  recent_sessions: {
    type: Array,
    default: () => [],
  },
})

// ==================== 平台判斷 ====================
const isLineApp = /Line\//i.test(navigator.userAgent)
const isMobile = window.innerWidth < 768
const maxFiles = isMobile
  ? props.upload_limits.max_files_mobile
  : props.upload_limits.max_files_desktop

// ==================== 步驟狀態 ====================
const step = ref(1)
const sessionSelectorVisible = ref(false)
const uploaderRef = ref(null)
const uploadedFilenames = ref([])
const uploadError = ref('')
const isSubmitting = ref(false)
const submitResults = ref([])

const today = computed(() => new Date().toLocaleDateString('en-CA'))

const sharedForm = reactive({
  tribe: '',
  location: '',
  capture_date: today.value,
  capture_method: '',
  notes: '',
})

const formErrors = ref({})

// ==================== 計算屬性 ====================
const canSubmit = computed(() => {
  if (step.value === 1) return uploaderRef.value?.items?.length > 0
  if (step.value === 2) return true
  return false
})

const submitLabel = computed(() => {
  if (step.value === 1) return isSubmitting.value ? '上傳中...' : '下一步'
  if (step.value === 2)
    return isSubmitting.value ? '送出中...' : `送出（${uploadedFilenames.value.length} 筆）`
  return '完成'
})

const allDone = computed(
  () => submitResults.value.length > 0 && submitResults.value.every((r) => r.status === 'done')
)

// ==================== 事件處理 ====================
function goBack() {
  if (step.value > 1 && step.value < 3) {
    step.value--
    return
  }
  router.visit(`/fish/${props.fish.id}/media-manager`)
}

async function handleSubmit() {
  if (step.value === 1) {
    await doUpload()
  } else if (step.value === 2) {
    await doSubmitAll()
  } else if (allDone.value) {
    router.visit(`/fish/${props.fish.id}/media-manager`)
  }
}

async function doUpload() {
  if (!uploaderRef.value) return
  isSubmitting.value = true
  uploadError.value = ''
  await uploaderRef.value.uploadAll()
  isSubmitting.value = false
}

function onUploaded(filenames) {
  uploadedFilenames.value = filenames
  step.value = 2
  // 若有過去捕獲資訊，先顯示 selector
  if (props.recent_sessions && props.recent_sessions.length > 0) {
    sessionSelectorVisible.value = true
  }
}

function onSessionSelect(session) {
  if (session) {
    sharedForm.tribe = session.tribe
    sharedForm.location = session.location
    sharedForm.capture_date = session.capture_date
    sharedForm.capture_method = session.capture_method
  }
  sessionSelectorVisible.value = false
}

function onUploadError(errors) {
  uploadError.value = `上傳失敗：${errors.join('、')}`
  isSubmitting.value = false
}

function validateForm() {
  const errors = {}
  if (!sharedForm.tribe) errors.tribe = '請選擇部落'
  if (!sharedForm.location) errors.location = '請輸入捕獲地點'
  if (!sharedForm.capture_date) errors.capture_date = '請選擇捕獲日期'
  if (!sharedForm.capture_method) errors.capture_method = '請選擇捕獲方式'
  formErrors.value = errors
  return Object.keys(errors).length === 0
}

async function doSubmitAll() {
  if (!validateForm()) return

  isSubmitting.value = true
  step.value = 3

  // 初始化進度列表
  submitResults.value = uploadedFilenames.value.map(() => ({ status: 'pending', error: null }))

  for (let i = 0; i < uploadedFilenames.value.length; i++) {
    const filename = uploadedFilenames.value[i]
    try {
      await new Promise((resolve, reject) => {
        router.post(
          `/fish/${props.fish.id}/capture-records`,
          {
            image_filename: filename,
            tribe: sharedForm.tribe,
            location: sharedForm.location,
            capture_date: sharedForm.capture_date,
            capture_method: sharedForm.capture_method,
            notes: sharedForm.notes,
          },
          {
            onSuccess: () => resolve(),
            onError: (errors) => reject(new Error(Object.values(errors)[0] || '新增失敗')),
            preserveState: true,
            preserveScroll: true,
          }
        )
      })
      submitResults.value[i].status = 'done'
    } catch (e) {
      submitResults.value[i].status = 'error'
      submitResults.value[i].error = e.message
    }
  }

  isSubmitting.value = false
}

defineExpose({ sharedForm, onUploaded })
</script>
