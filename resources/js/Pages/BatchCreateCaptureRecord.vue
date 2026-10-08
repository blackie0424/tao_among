<template>
  <div class="container mx-auto p-4 relative">
    <FormActionBar :goBack="goBack" :fishName="fish.name" title="批次新增捕獲紀錄" :showSubmit="canSubmit" :submitNote="handleSubmit" :submitLabel="submitLabel" :showLoading="isSubmitting" />
    <div class="pt-16 space-y-6 max-w-2xl mx-auto">
      <div class="flex items-center gap-3 p-3 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="w-14 h-14 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
          <img :src="fish.display_image_url || fish.image_url" :alt="fish.name" class="w-full h-full object-contain" />
        </div>
        <div><p class="font-semibold text-gray-900">{{ fish.name }}</p><p class="text-xs text-gray-500">批次新增多筆捕獲紀錄</p></div>
      </div>
      <section v-if="step === 1" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">第一步：選擇照片 <span class="text-sm font-normal text-gray-500 ml-1">（最多 {{ maxFiles }} 張）</span></h2>
        <BatchCaptureImageUploader :maxFiles="maxFiles" :isLineApp="isLineApp" ref="uploaderRef" @uploaded="onUploaded" @upload-error="onUploadError" />
        <p v-if="uploadError" class="mt-3 text-sm text-red-600">{{ uploadError }}</p>
      </section>
      <section v-if="step === 2" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">第二步：選擇情境</h2>
        <p class="text-sm text-gray-500 mb-4">選擇的情境會套用至本批次 <span class="font-semibold text-gray-700">{{ uploadedFilenames.length }}</span> 張照片。</p>
        <CaptureRecordSessionSelector :selectable-sessions="selectable_sessions" :legacy-combos="legacy_combos" @select="onSessionSelect" />
        <p v-if="formErrors.session_id" class="mb-3 text-sm text-red-600">{{ formErrors.session_id }}</p>
        <p v-if="formErrors.legacy_combo" class="mb-3 text-sm text-red-600">{{ formErrors.legacy_combo }}</p>
        <div><label class="block text-sm font-medium text-gray-700 mb-1">備註（選填）</label><textarea v-model="notes" rows="2" placeholder="相關備註" class="w-full px-3 py-2 border border-gray-300 rounded-md" /></div>
      </section>
      <section v-if="step === 3" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-base font-semibold text-gray-800 mb-4">第三步：新增中</h2>
        <ul class="space-y-2"><li v-for="(result, index) in submitResults" :key="index" class="text-sm"><span v-if="result.status === 'done'" class="text-green-600">照片 {{ index + 1 }} 新增成功</span><span v-else-if="result.status === 'pending'" class="text-blue-600">照片 {{ index + 1 }} 新增中...</span><span v-else class="text-red-600">照片 {{ index + 1 }} 失敗：{{ result.error }}</span></li></ul>
        <div v-if="allDone" class="mt-4 p-3 bg-green-50 rounded-lg text-sm text-green-800">全部 {{ uploadedFilenames.length }} 筆捕獲紀錄新增完成！</div>
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

const props = defineProps({
  fish: Object,
  tribes: Array,
  capture_methods: [Array, Object],
  upload_limits: { type: Object, default: () => ({ max_files_desktop: 10, max_files_mobile: 5 }) },
  selectable_sessions: { type: Array, default: () => [] },
  legacy_combos: { type: Array, default: () => [] },
})

const isLineApp = /Line\//i.test(navigator.userAgent)
const isMobile = window.innerWidth < 768
const maxFiles = isMobile ? props.upload_limits.max_files_mobile : props.upload_limits.max_files_desktop
const step = ref(1)
const uploaderRef = ref(null)
const uploadedFilenames = ref([])
const uploadError = ref('')
const isSubmitting = ref(false)
const submitResults = ref([])
const selection = ref(null)
const notes = ref('')
const formErrors = ref({})

const canSubmit = computed(() => step.value === 1 ? uploaderRef.value?.items?.length > 0 : step.value === 2 ? !!selection.value && !isSubmitting.value : false)
const submitLabel = computed(() => step.value === 1 ? (isSubmitting.value ? '上傳中...' : '下一步') : step.value === 2 ? (isSubmitting.value ? '送出中...' : '送出（' + uploadedFilenames.value.length + ' 筆）') : '完成')
const allDone = computed(() => submitResults.value.length > 0 && submitResults.value.every((result) => result.status === 'done'))

function goBack() {
  if (step.value > 1 && step.value < 3) { step.value--; return }
  router.visit('/fish/' + props.fish.id + '/media-manager')
}

async function handleSubmit() {
  if (step.value === 1) await doUpload()
  else if (step.value === 2) await doSubmitAll()
  else if (allDone.value) router.visit('/fish/' + props.fish.id + '/media-manager')
}

async function doUpload() {
  if (!uploaderRef.value) return
  isSubmitting.value = true
  uploadError.value = ''
  await uploaderRef.value.uploadAll()
  isSubmitting.value = false
}

function onUploaded(filenames) { uploadedFilenames.value = filenames; step.value = 2 }
function onSessionSelect(value) { selection.value = value; formErrors.value = {} }
function onUploadError(errors) { uploadError.value = '上傳失敗：' + errors.join('、'); isSubmitting.value = false }
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
      await new Promise((resolve, reject) => {
        router.post('/fish/' + props.fish.id + '/capture-records', {
          image_filename: uploadedFilenames.value[i],
          session_id: selection.value.session_id,
          legacy_combo: selection.value.legacy_combo,
          notes: notes.value,
        }, {
          onSuccess: resolve,
          onError: (errors) => reject(new Error(Object.values(errors)[0] || '新增失敗')),
          preserveState: true,
        })
      })
      submitResults.value[i] = { status: 'done', error: null }
    } catch (error) {
      submitResults.value[i] = { status: 'error', error: error.message }
    }
  }
  isSubmitting.value = false
}
</script>