<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar
      :goBack="goBack"
      title="批次新增魚類"
      :showSubmit="canSubmit"
      :submitNote="handleSubmit"
      :submitLabel="submitLabel"
      :showLoading="isSubmitting"
    />

    <div class="pt-16 space-y-6 max-w-2xl mx-auto">

      <!-- Step 1：選擇照片 -->
      <section v-if="step === 1" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">
          第一步：選擇照片
          <span class="text-sm font-normal text-gray-500 ml-1">（最多 {{ maxFiles }} 張）</span>
        </h2>
        <p class="text-sm text-gray-500 mb-4">請選擇同一個物種的多張照片，上傳後統一填寫魚類資訊。</p>

        <BatchCaptureImageUploader
          :maxFiles="maxFiles"
          :isLineApp="isLineApp"
          ref="uploaderRef"
          @uploaded="onUploaded"
          @upload-error="onUploadError"
        />

        <p v-if="uploadError" data-testid="upload-error" class="mt-3 text-sm text-red-600">
          {{ uploadError }}
        </p>
      </section>

      <!-- Step 2：填寫魚類 + 捕獲資訊 -->
      <section v-if="step === 2" data-testid="step-2" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">第二步：填寫資訊</h2>
        <p class="text-sm text-gray-500 mb-4">
          已選擇 <span class="font-semibold text-gray-700">{{ uploadedFilenames.length }}</span> 張照片，
          以下資訊將套用至所有照片的捕獲紀錄。
        </p>

        <div class="space-y-4">
          <!-- 魚類名稱 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">魚類名稱</label>
            <input
              v-model="fishName"
              data-testid="fish-name-input"
              type="text"
              placeholder="我不知道"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <!-- 部落 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              部落 <span class="text-red-500">*</span>
            </label>
            <select
              v-model="sharedForm.tribe"
              data-testid="tribe-select"
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
              data-testid="location-input"
              type="text"
              placeholder="請輸入捕獲地點"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="formErrors.location" class="mt-1 text-sm text-red-600">{{ formErrors.location }}</p>
          </div>

          <!-- 捕獲日期 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              捕獲日期 <span class="text-red-500">*</span>
            </label>
            <input
              v-model="sharedForm.capture_date"
              data-testid="capture-date-input"
              type="date"
              :max="today"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p v-if="formErrors.capture_date" class="mt-1 text-sm text-red-600">{{ formErrors.capture_date }}</p>
          </div>

          <!-- 捕獲方式 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              捕獲方式 <span class="text-red-500">*</span>
            </label>
            <select
              v-model="sharedForm.capture_method"
              data-testid="capture-method-select"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">請選擇捕獲方式</option>
              <option
                v-for="(label, value) in capture_methods"
                :key="value"
                :value="value"
              >
                {{ label }}
              </option>
            </select>
            <p v-if="formErrors.capture_method" class="mt-1 text-sm text-red-600">{{ formErrors.capture_method }}</p>
          </div>

          <!-- 備註 -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">備註（選填）</label>
            <textarea
              v-model="sharedForm.notes"
              data-testid="notes-textarea"
              rows="2"
              placeholder="相關備註"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
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

const props = defineProps({
  tribes: Array,
  capture_methods: [Array, Object],
  upload_limits: {
    type: Object,
    default: () => ({ max_files_desktop: 10, max_files_mobile: 5 }),
  },
})

// ── 平台判斷 ──────────────────────────────────────────────────────────────
const isLineApp = /Line\//i.test(navigator.userAgent)
const isMobile  = window.innerWidth < 768
const maxFiles  = isMobile
  ? props.upload_limits.max_files_mobile
  : props.upload_limits.max_files_desktop

// ── 狀態 ──────────────────────────────────────────────────────────────────
const step              = ref(1)
const uploaderRef       = ref(null)
const uploadedFilenames = ref([])
const uploadError       = ref('')
const isSubmitting      = ref(false)
const fishName          = ref('')
const formErrors        = ref({})

const today = computed(() => new Date().toLocaleDateString('en-CA'))

const sharedForm = reactive({
  tribe:          '',
  location:       '',
  capture_date:   today.value,
  capture_method: '',
  notes:          '',
})

// ── 計算屬性 ──────────────────────────────────────────────────────────────
const canSubmit = computed(() => {
  if (step.value === 1) return uploaderRef.value?.items?.length > 0
  if (step.value === 2) return !isSubmitting.value
  return false
})

const submitLabel = computed(() => {
  if (step.value === 1) return isSubmitting.value ? '上傳中...' : '下一步'
  if (step.value === 2) return isSubmitting.value ? '送出中...' : `新增（${uploadedFilenames.value.length} 張）`
  return '完成'
})

// ── 操作 ──────────────────────────────────────────────────────────────────
function goBack() {
  if (step.value === 2) {
    step.value = 1
    return
  }
  router.visit('/fishs')
}

async function handleSubmit() {
  if (step.value === 1) {
    await doUpload()
  } else if (step.value === 2) {
    await doSubmit()
  }
}

async function doUpload() {
  if (!uploaderRef.value) return
  isSubmitting.value = true
  uploadError.value  = ''
  await uploaderRef.value.uploadAll()
  isSubmitting.value = false
}

function onUploaded(filenames) {
  uploadedFilenames.value = filenames
  isSubmitting.value      = false
  step.value              = 2
}

function onUploadError(errors) {
  uploadError.value  = `上傳失敗：${errors.join('、')}`
  isSubmitting.value = false
}

function validateForm() {
  const errors = {}
  if (!sharedForm.tribe)          errors.tribe          = '請選擇部落'
  if (!sharedForm.location)       errors.location       = '請輸入捕獲地點'
  if (!sharedForm.capture_date)   errors.capture_date   = '請選擇捕獲日期'
  if (!sharedForm.capture_method) errors.capture_method = '請選擇捕獲方式'
  formErrors.value = errors
  return Object.keys(errors).length === 0
}

async function doSubmit() {
  if (!validateForm()) return

  isSubmitting.value = true

  router.post(
    '/fish/batch-create',
    {
      name:           fishName.value || '我不知道',
      filenames:      uploadedFilenames.value,
      tribe:          sharedForm.tribe,
      location:       sharedForm.location,
      capture_date:   sharedForm.capture_date,
      capture_method: sharedForm.capture_method,
      notes:          sharedForm.notes,
    },
    {
      onSuccess: () => {
        isSubmitting.value = false
      },
      onError: () => {
        isSubmitting.value = false
      },
    }
  )
}

// 供測試呼叫
defineExpose({ onUploaded, onUploadError, doSubmit, fishName, sharedForm, step })
</script>
