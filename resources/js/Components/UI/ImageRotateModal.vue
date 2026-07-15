<template>
  <Teleport to="body">
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-black/75 p-4"
        @click.self="$emit('close')"
      >
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden" @click.stop>
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">旋轉圖片</h3>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-600 p-1 rounded"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Canvas preview -->
          <div class="flex items-center justify-center bg-gray-50 p-4" style="min-height: 240px;">
            <canvas
              ref="canvasRef"
              class="max-w-full max-h-64 object-contain rounded shadow"
              style="max-height: 240px;"
            />
            <div v-if="loading" class="flex items-center gap-2 text-gray-500">
              <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              載入中…
            </div>
          </div>

          <!-- Rotation buttons -->
          <div class="flex justify-center gap-3 px-5 py-3 border-t border-gray-100">
            <button
              type="button"
              :disabled="loading || submitting"
              class="flex flex-col items-center gap-1 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-50 text-sm text-gray-700 transition-colors"
              @click="rotate(-90)"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              逆時針 90°
            </button>
            <button
              type="button"
              :disabled="loading || submitting"
              class="flex flex-col items-center gap-1 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-50 text-sm text-gray-700 transition-colors"
              @click="rotate(180)"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              180°
            </button>
            <button
              type="button"
              :disabled="loading || submitting"
              class="flex flex-col items-center gap-1 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 disabled:opacity-50 text-sm text-gray-700 transition-colors"
              @click="rotate(90)"
            >
              <svg class="w-6 h-6 scale-x-[-1]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              順時針 90°
            </button>
          </div>

          <!-- Error -->
          <p v-if="error" class="text-red-500 text-sm text-center px-5 pb-2">{{ error }}</p>

          <!-- Action buttons -->
          <div class="flex justify-end gap-3 px-5 py-3 border-t border-gray-200">
            <button
              type="button"
              :disabled="submitting"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors disabled:opacity-50"
              @click="$emit('close')"
            >
              取消
            </button>
            <button
              type="button"
              :disabled="loading || submitting || totalRotation === 0"
              class="px-4 py-2 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-2"
              @click="confirm"
            >
              <svg v-if="submitting" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              確認旋轉
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
  open: { type: Boolean, required: true },
  imageUrl: { type: String, required: true },
  fishId: { type: Number, required: true },
  recordId: { type: Number, default: null },
  mimeType: { type: String, default: 'image/jpeg' },
})

const emit = defineEmits(['close', 'rotated'])

const canvasRef = ref(null)
const loading = ref(false)
const submitting = ref(false)
const error = ref('')
const totalRotation = ref(0)

let originalImage = null

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      totalRotation.value = 0
      error.value = ''
      originalImage = null
      return
    }
    await nextTick()
    await loadImage()
  }
)

async function loadImage() {
  if (!canvasRef.value) return
  loading.value = true
  error.value = ''

  try {
    const img = new Image()
    img.crossOrigin = 'anonymous'
    await new Promise((resolve, reject) => {
      img.onload = resolve
      img.onerror = () => reject(new Error('圖片載入失敗'))
      img.src = props.imageUrl
    })
    originalImage = img
    drawRotated(0)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function rotate(degrees) {
  totalRotation.value = ((totalRotation.value + degrees) % 360 + 360) % 360
  drawRotated(totalRotation.value)
}

function drawRotated(degrees) {
  if (!originalImage || !canvasRef.value) return
  const canvas = canvasRef.value
  const img = originalImage
  const rad = (degrees * Math.PI) / 180

  const swap = degrees === 90 || degrees === 270
  canvas.width = swap ? img.naturalHeight : img.naturalWidth
  canvas.height = swap ? img.naturalWidth : img.naturalHeight

  const ctx = canvas.getContext('2d')
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  ctx.translate(canvas.width / 2, canvas.height / 2)
  ctx.rotate(rad)
  ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2)
}

async function confirm() {
  if (!canvasRef.value || totalRotation.value === 0) return
  submitting.value = true
  error.value = ''

  try {
    const blob = await new Promise((resolve, reject) => {
      const quality = props.mimeType === 'image/png' ? undefined : 1.0
      canvasRef.value.toBlob(
        (b) => (b ? resolve(b) : reject(new Error('canvas 匯出失敗'))),
        props.mimeType,
        quality
      )
    })

    const ext = props.mimeType === 'image/png' ? 'png' : 'jpg'
    const formData = new FormData()
    formData.append('image', blob, `rotated.${ext}`)

    const url = props.recordId
      ? `/prefix/api/fish/${props.fishId}/capture-records/${props.recordId}/image/rotate`
      : `/prefix/api/fish/${props.fishId}/image/rotate`

    const csrfToken = decodeURIComponent(
      document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1] ?? ''
    )

    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': csrfToken,
      },
      body: formData,
    })

    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error(data.message || '旋轉失敗，請稍後再試')
    }

    emit('rotated', `${props.imageUrl.split('?')[0]}?t=${Date.now()}`)
    emit('close')
  } catch (e) {
    error.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>
