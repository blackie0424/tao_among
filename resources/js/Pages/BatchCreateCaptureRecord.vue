<template>
  <Head :title="title" />
  <FishAppLayout
    :pageTitle="title"
    :mobileBackUrl="afterSaveUrl"
    :mobileBackText="fromWorkspace ? '回工作區' : '回管理捕獲紀錄'"
  >
    <div class="mx-auto max-w-2xl pb-8">
      <h1 class="font-serif text-elder-title text-elder-text">{{ title }}</h1>

      <section v-if="step === 1" class="mt-6 border-t border-rule pt-4">
        <h2 class="mb-4 font-serif text-elder-name font-bold text-elder-text">
          1　選擇照片
          <span class="text-elder-aux font-normal text-elder-subtext"
            >（最多 {{ maxFiles }} 張）</span
          >
        </h2>
        <BatchCaptureImageUploader
          :maxFiles="maxFiles"
          :isLineApp="isLineApp"
          ref="uploaderRef"
          @uploaded="onUploaded"
          @upload-error="onUploadError"
        />
        <p v-if="uploadError" class="mt-3 text-elder-aux text-red-700">{{ uploadError }}</p>
      </section>

      <section v-if="step === 2" class="mt-6 border-t border-rule pt-4">
        <h2 class="mb-4 font-serif text-elder-name font-bold text-elder-text">2　選擇情境</h2>
        <p class="mb-4 text-elder-aux text-elder-subtext">
          選擇的情境會套用至本批次
          <span class="font-bold text-elder-text">{{ uploadedFilenames.length }}</span> 張照片。
        </p>
        <CaptureRecordSessionSelector
          :selectable-sessions="selectable_sessions"
          :legacy-combos="legacy_combos"
          :tribes="tribes"
          :capture-methods="capture_methods"
          @select="onSessionSelect"
        />
        <p v-if="formErrors.session_id" class="mb-3 text-elder-aux text-red-700">
          {{ formErrors.session_id }}
        </p>
        <p v-if="formErrors.legacy_combo" class="mb-3 text-elder-aux text-red-700">
          {{ formErrors.legacy_combo }}
        </p>
        <div>
          <label for="batch-notes" class="mb-1 block text-elder-body font-bold text-elder-text"
            >備註（選填）</label
          >
          <textarea
            id="batch-notes"
            v-model="notes"
            rows="2"
            placeholder="相關備註"
            class="w-full border border-rule-strong bg-paper-raised px-3 py-2 text-elder-body text-elder-text focus:outline-none focus:ring-2 focus:ring-brand"
          />
        </div>
        <button
          type="button"
          data-testid="back"
          class="mt-4 min-h-touch-secondary text-elder-body text-brand underline underline-offset-4"
          :disabled="isSubmitting"
          @click="goPreviousStep"
        >
          上一步：重選照片
        </button>
      </section>

      <section v-if="step === 3" class="mt-6 border-t border-rule pt-4">
        <h2 class="mb-4 font-serif text-elder-name font-bold text-elder-text">3　新增中</h2>
        <ul class="space-y-2 text-elder-body">
          <li v-for="(result, index) in submitResults" :key="index">
            <span v-if="result.status === 'done'" class="text-brand"
              >照片 {{ index + 1 }} 新增成功</span
            >
            <span v-else-if="result.status === 'pending'" class="text-elder-subtext"
              >照片 {{ index + 1 }} 新增中...</span
            >
            <span v-else class="text-red-700">照片 {{ index + 1 }} 失敗：{{ result.error }}</span>
          </li>
        </ul>
        <p
          v-if="allDone"
          class="mt-4 border border-rule-strong bg-paper-raised p-3 text-elder-body text-elder-text"
        >
          全部 {{ uploadedFilenames.length }} 筆捕獲紀錄新增完成！
        </p>
      </section>

      <button
        v-if="canSubmit"
        type="button"
        data-testid="submit-btn"
        class="mt-6 inline-flex min-h-touch-secondary items-center bg-brand px-6 text-elder-body font-bold text-white hover:bg-brand-dark disabled:opacity-50"
        :disabled="isSubmitting"
        @click="handleSubmit"
      >
        {{ submitLabel }}
      </button>
    </div>
  </FishAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { apiFetch } from '@/utils/apiFetch'
import { Head, router } from '@inertiajs/vue3'
import FishAppLayout from '@/Layouts/FishAppLayout.vue'
import BatchCaptureImageUploader from '@/Components/CaptureRecord/BatchCaptureImageUploader.vue'
import CaptureRecordSessionSelector from '@/Components/CaptureRecord/CaptureRecordSessionSelector.vue'

const props = defineProps({
  fish: Object,
  tribes: Array,
  capture_methods: [Array, Object],
  upload_limits: { type: Object, default: () => ({ max_files_desktop: 10, max_files_mobile: 5 }) },
  selectable_sessions: { type: Array, default: () => [] },
  legacy_combos: { type: Array, default: () => [] },
  from: { type: String, default: null },
})

const fromWorkspace = computed(() => props.from === 'workspace')
const title = computed(() => `${props.fish.name}・新增捕獲紀錄`)
const afterSaveUrl = computed(() =>
  fromWorkspace.value ? '/workspace' : `/fish/${props.fish.id}/capture-records`
)

const isLineApp = /Line\//i.test(navigator.userAgent)
const isMobile = window.innerWidth < 768
const maxFiles = isMobile
  ? props.upload_limits.max_files_mobile
  : props.upload_limits.max_files_desktop
const step = ref(1)
const uploaderRef = ref(null)
const uploadedFilenames = ref([])
const uploadError = ref('')
const isSubmitting = ref(false)
const submitResults = ref([])
const selection = ref(null)
const notes = ref('')
const formErrors = ref({})

const canSubmit = computed(() =>
  step.value === 1
    ? uploaderRef.value?.items?.length > 0
    : step.value === 2
      ? !!selection.value && !isSubmitting.value
      : false
)
const submitLabel = computed(() =>
  step.value === 1
    ? isSubmitting.value
      ? '上傳中...'
      : '下一步'
    : step.value === 2
      ? isSubmitting.value
        ? '送出中...'
        : '送出（' + uploadedFilenames.value.length + ' 筆）'
      : '完成'
)
const allDone = computed(
  () =>
    submitResults.value.length > 0 &&
    submitResults.value.every((result) => result.status === 'done')
)

function goPreviousStep() {
  if (isSubmitting.value || step.value !== 2) return
  step.value--
}

async function handleSubmit() {
  if (step.value === 1) await doUpload()
  else if (step.value === 2) await doSubmitAll()
  else if (allDone.value) router.visit(afterSaveUrl.value)
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
}
function onSessionSelect(value) {
  selection.value = value
  formErrors.value = {}
}
function onUploadError(errors) {
  uploadError.value = '上傳失敗：' + errors.join('、')
  isSubmitting.value = false
}
function validateForm() {
  if (selection.value) return true
  formErrors.value = { session_id: '請選擇情境' }
  return false
}

async function doSubmitAll() {
  if (!validateForm()) return
  isSubmitting.value = true
  step.value = 3
  submitResults.value = uploadedFilenames.value.map(() => ({ status: 'pending', error: null }))
  for (let i = 0; i < uploadedFilenames.value.length; i++) {
    try {
      const response = await apiFetch('/fish/' + props.fish.id + '/capture-records', {
        method: 'POST',
        body: JSON.stringify({
          image_filename: uploadedFilenames.value[i],
          session_id: selection.value.session_id,
          legacy_combo: selection.value.legacy_combo,
          notes: notes.value,
        }),
      })
      const result = await response.json()
      if (!response.ok || !result.success) {
        const firstError = Object.values(result.errors || {}).flat()[0]
        throw new Error(firstError || result.message || '新增失敗')
      }
      submitResults.value[i] = { status: 'done', error: null }
    } catch (error) {
      submitResults.value[i] = { status: 'error', error: error.message }
    }
  }
  isSubmitting.value = false
  if (allDone.value) {
    router.visit(afterSaveUrl.value, {
      onSuccess: () => alert('捕獲紀錄新增成功'),
    })
  }
}
</script>
