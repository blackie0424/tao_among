<template>
  <form @submit.prevent class="space-y-6 md:space-y-8 text-xl leading-relaxed">
    <!-- 當前照片和上傳新照片 -->
    <div>
      <!-- 當前照片 -->
      <div v-if="record.image_url && !imagePreview" class="mb-4">
        <p class="text-sm text-gray-600 mb-2">當前照片：</p>
        <div class="relative inline-block">
          <LazyImage
            :src="record.image_url"
            :alt="'當前捕獲照片'"
            wrapperClass="fish-image-wrapper"
            imgClass="fish-image"
          />
        </div>
      </div>

      <!-- 上傳新照片 -->
      <div
        class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors"
      >
        <div class="space-y-1 text-center">
          <div v-if="!imagePreview">
            <svg
              class="mx-auto h-12 w-12 text-gray-400"
              stroke="currentColor"
              fill="none"
              viewBox="0 0 48 48"
            >
              <path
                d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <div class="flex text-sm text-gray-600">
              <label
                for="image"
                class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500"
              >
                <span>{{ record.image_url ? '更換照片' : '上傳照片' }}</span>
                <input
                  id="image"
                  name="image"
                  type="file"
                  accept="image/*"
                  class="sr-only"
                  @change="handleImageChange"
                />
              </label>
              <p class="pl-1">或拖拽到此處</p>
            </div>
            <p class="text-xs text-gray-500">PNG, JPG, WEBP 最大 10MB</p>
          </div>
          <div v-else class="relative">
            <img :src="imagePreview" alt="預覽圖片" class="mx-auto h-32 w-auto rounded-lg" />
            <button
              type="button"
              @click="removeImage"
              class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
              :disabled="uploading"
            >
              ×
            </button>
            <p class="mt-2 text-sm text-gray-600">{{ selectedFileName }}</p>
            <!-- 上傳狀態 -->
            <div v-if="uploading" class="mt-2 text-sm text-blue-600">
              <div class="flex items-center">
                <svg
                  class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                正在上傳圖片...
              </div>
            </div>
            <div v-else-if="uploadedImageFilename" class="mt-2 text-sm text-green-600">
              ✓ 圖片上傳成功
            </div>
          </div>
        </div>
      </div>
      <div v-if="errors.image" class="text-red-500 text-base mt-1">{{ errors.image }}</div>
    </div>

    <!-- 部落選擇 -->
    <div>
      <label for="tribe" class="block text-xl font-medium text-gray-700 mb-2">
        部落 <span class="text-red-500">*</span>
      </label>
      <select
        id="tribe"
        v-model="form.tribe"
        class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      >
        <option value="">請選擇部落</option>
        <option v-for="tribe in tribes" :key="tribe" :value="tribe">
          {{ tribe }}
        </option>
      </select>
      <div v-if="errors.tribe" class="text-red-500 text-base mt-1">{{ errors.tribe }}</div>
    </div>

    <!-- 捕獲地點 -->
    <div>
      <label for="location" class="block text-xl font-medium text-gray-700 mb-2">
        捕獲地點 <span class="text-red-500">*</span>
      </label>
      <input
        id="location"
        v-model="form.location"
        type="text"
        class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="例如：太魯閣溪上游、立霧溪出海口"
        required
      />
      <div v-if="errors.location" class="text-red-500 text-base mt-1">{{ errors.location }}</div>
    </div>

    <!-- 捕獲方式 -->
    <div>
      <label for="capture_method" class="block text-xl font-medium text-gray-700 mb-2"
        >捕獲方式 <span class="text-red-500">*</span></label
      >
      <select
        id="capture_method"
        v-model="form.capture_method"
        class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">請選擇捕獲方式</option>
        <option v-for="(label, value) in capture_methods" :key="value" :value="value">
          {{ label }}
        </option>
      </select>
      <div v-if="errors.capture_method" class="text-red-500 text-base mt-1">
        {{ errors.capture_method }}
      </div>
    </div>

    <!-- 捕獲日期 -->
    <div>
      <label for="capture_date" class="block text-xl font-medium text-gray-700 mb-2">
        捕獲日期 <span class="text-red-500">*</span>
      </label>
      <input
        id="capture_date"
        v-model="form.capture_date"
        type="date"
        :max="today"
        class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      />
      <div v-if="errors.capture_date" class="text-red-500 text-base mt-1">
        {{ errors.capture_date }}
      </div>
    </div>

    <!-- 備註 -->
    <div>
      <label for="notes" class="block text-xl font-medium text-gray-700 mb-2"> 備註 </label>
      <textarea
        id="notes"
        v-model="form.notes"
        rows="3"
        class="w-full px-3 py-2 text-xl border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        placeholder="請輸入相關備註資訊"
      ></textarea>
      <div v-if="errors.notes" class="text-red-500 text-base mt-1">{{ errors.notes }}</div>
    </div>
  </form>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import LazyImage from '@/Components/UI/LazyImage.vue'
import { useImageUpload } from '@/composables/useImageUpload'
import { useCaptureFormFields } from '@/composables/useCaptureFormFields'

const props = defineProps({
  record: Object,
  tribes: Array,
  capture_methods: Array,
  fishId: Number,
  fishName: String,
  fishImage: String,
})

const emit = defineEmits(['submitted', 'statusChange'])

const processing = ref(false)
const selectedFileName = ref('')
const canSubmit = ref(true)

const today = computed(() => new Date().toISOString().split('T')[0])

const {
  imagePreview,
  uploading,
  uploadedFilename: uploadedImageFilename,
  imageError,
  handleImageChange: baseHandleImageChange,
  removeImage: baseRemoveImage,
} = useImageUpload({ autoUpload: true })

const { form, errors, buildFormData } = useCaptureFormFields()

onMounted(() => {
  if (props.record) {
    form.tribe = props.record.tribe || ''
    form.location = props.record.location || ''
    form.capture_method = props.record.capture_method || ''
    form.capture_date = props.record.capture_date
      ? new Date(props.record.capture_date).toISOString().split('T')[0]
      : ''
    form.notes = props.record.notes || ''
  }
})

async function handleImageChange(event) {
  const file = event.target.files?.[0]
  if (!file) return
  selectedFileName.value = file.name
  canSubmit.value = false
  errors.value = {}
  emit('statusChange', { canSubmit: false, uploading: true })

  try {
    await baseHandleImageChange(event)
    canSubmit.value = true
    emit('statusChange', { canSubmit: true, uploading: false })
  } catch {
    errors.value = { image: imageError.value }
    canSubmit.value = true
    emit('statusChange', { canSubmit: true, uploading: false })
  }
}

function removeImage() {
  baseRemoveImage()
  selectedFileName.value = ''
  canSubmit.value = true
  const input = document.getElementById('image')
  if (input) input.value = ''
}

function submitForm() {
  processing.value = true
  errors.value = {}

  const formData = { ...buildFormData(), _method: 'PUT' }
  if (uploadedImageFilename.value) {
    formData.image_filename = uploadedImageFilename.value
  }

  router.post(`/fish/${props.fishId}/capture-records/${props.record.id}`, formData, {
    onSuccess: () => {},
    onError: (e) => { errors.value = e },
    onFinish: () => { processing.value = false },
  })
}

defineExpose({ submitForm, canSubmit, uploading })
</script>
