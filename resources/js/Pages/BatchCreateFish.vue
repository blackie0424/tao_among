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
        <p class="text-sm text-gray-500 mb-4">
          請選擇同一個物種的多張照片，上傳後統一填寫魚類資訊。
        </p>

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

      <!-- Step 2：選擇情境 -->
      <section v-if="step === 2" data-testid="step-2" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-1">第二步：選擇情境</h2>
        <p class="text-sm text-gray-500 mb-4">
          已選擇 <span class="font-semibold text-gray-700">{{ uploadedFilenames.length }}</span> 張照片。
        </p>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">魚類名稱</label>
          <input v-model="fishName" data-testid="fish-name-input" type="text" placeholder="我不知道" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
        </div>
        <CaptureRecordSessionSelector
          :selectable-sessions="selectable_sessions"
          :legacy-combos="legacy_combos"
          :tribes="tribes"
          :capture-methods="capture_methods"
          @select="onSessionSelect"
        />
        <p v-if="formErrors.session_id" class="mb-3 text-sm text-red-600">{{ formErrors.session_id }}</p>
        <p v-if="formErrors.legacy_combo" class="mb-3 text-sm text-red-600">{{ formErrors.legacy_combo }}</p>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">備註（選填）</label>
          <textarea v-model="notes" data-testid="notes-textarea" rows="2" placeholder="相關備註" class="w-full px-3 py-2 border border-gray-300 rounded-md" />
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import FormActionBar from '@/Components/Global/FormActionBar.vue'
import BatchCaptureImageUploader from '@/Components/CaptureRecord/BatchCaptureImageUploader.vue'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'
import { markFishCreated } from '@/utils/fishListCache'

const props = defineProps({
  tribes: Array,
  capture_methods: [Array, Object],
  upload_limits: {
    type: Object,
    default: () => ({ max_files_desktop: 10, max_files_mobile: 5 }),
  },
  selectable_sessions: { type: Array, default: () => [] },
  legacy_combos: { type: Array, default: () => [] },
})

// ── 平台判斷 ──────────────────────────────────────────────────────────────
const isLineApp = /Line\//i.test(navigator.userAgent)
const isMobile = window.innerWidth < 768
const maxFiles = isMobile
  ? props.upload_limits.max_files_mobile
  : props.upload_limits.max_files_desktop

// ── 狀態 ──────────────────────────────────────────────────────────────────
const step = ref(1)
const uploaderRef = ref(null)
const uploadedFilenames = ref([])
const uploadError = ref('')
const isSubmitting = ref(false)
const fishName = ref('')
const formErrors = ref({})

const selection = ref(null)
const notes = ref('')

// ── 計算屬性 ──────────────────────────────────────────────────────────────
const canSubmit = computed(() => {
  if (step.value === 1) return uploaderRef.value?.items?.length > 0
  if (step.value === 2) return !!selection.value && !isSubmitting.value
  return false
})

const submitLabel = computed(() => {
  if (step.value === 1) return isSubmitting.value ? '上傳中...' : '下一步'
  if (step.value === 2)
    return isSubmitting.value ? '送出中...' : `新增（${uploadedFilenames.value.length} 張）`
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
  uploadError.value = ''
  await uploaderRef.value.uploadAll()
  isSubmitting.value = false
}

function onUploaded(filenames) {
  uploadedFilenames.value = filenames
  isSubmitting.value = false
  step.value = 2

}

function onSessionSelect(value) {
  selection.value = value
  formErrors.value = {}
}

function onUploadError(errors) {
  uploadError.value = `上傳失敗：${errors.join('、')}`
  isSubmitting.value = false
}

function validateForm() {
  if (selection.value) return true
  formErrors.value = { session_id: '請選擇情境' }
  return false
}

async function doSubmit() {
  if (!validateForm()) return

  isSubmitting.value = true

  router.post(
    '/fish/batch-create',
    {
      name: fishName.value || '我不知道',
      filenames: uploadedFilenames.value,
      session_id: selection.value.session_id,
      legacy_combo: selection.value.legacy_combo,
      notes: notes.value,
    },
    {
      onSuccess: (page) => {
        const fishId = page.props.fish?.id
        if (fishId) {
          markFishCreated(fishId)
        }
        isSubmitting.value = false
      },
      onError: () => {
        isSubmitting.value = false
      },
    }
  )
}

// 供測試呼叫
defineExpose({ onUploaded, onUploadError, doSubmit, fishName, selection, step })
</script>
