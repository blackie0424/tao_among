<template>
  <section class="space-y-4" data-testid="topic-item-media-editor">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-gray-900">圖片與 YouTube 影片</h2>
        <p class="text-sm text-gray-500">最上方的媒體會優先顯示；主圖取排序最前的圖片。</p>
      </div>
      <button
        type="button"
        class="rounded-lg border border-blue-600 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
        data-testid="add-youtube"
        @click="addYouTube"
      >
        新增 YouTube 影片
      </button>
    </div>

    <label class="block rounded-lg border-2 border-dashed border-gray-300 p-4 text-center">
      <span class="block text-sm font-medium text-gray-700">上傳多張圖片</span>
      <input
        class="mt-2 block w-full text-sm text-gray-600"
        type="file"
        accept="image/*"
        multiple
        :disabled="isUploading || disabled"
        data-testid="media-image-input"
        @change="onFilesSelected"
      />
    </label>
    <p v-if="isUploading" class="text-sm text-blue-700">圖片上傳中...</p>
    <p v-if="imageError" class="text-sm text-red-700">{{ imageError }}</p>

    <ol v-if="modelValue.length" class="space-y-3">
      <li
        v-for="(media, index) in modelValue"
        :key="media.localKey ?? media.id ?? `${media.type}-${index}`"
        class="rounded-xl border border-gray-200 bg-gray-50 p-4"
        :data-testid="`media-row-${index}`"
      >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
          <div class="shrink-0">
            <img
              v-if="media.type === 'image' && media.image_url"
              :src="media.image_url"
              alt="媒體預覽"
              class="h-24 w-36 rounded-lg object-cover"
            />
            <div
              v-else-if="media.type === 'image'"
              class="flex h-24 w-36 items-center justify-center rounded-lg bg-gray-200 text-xs text-gray-600"
            >
              圖片 {{ index + 1 }}
            </div>
          </div>

          <div class="min-w-0 flex-1">
            <p class="mb-1 text-sm font-medium text-gray-700">
              {{ media.type === 'image' ? '圖片' : 'YouTube 影片' }} · 排序 {{ index + 1 }}
            </p>
            <input
              v-if="media.type === 'youtube'"
              :value="media.source"
              type="url"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="https://www.youtube.com/watch?v=..."
              :disabled="disabled"
              :data-testid="`youtube-url-${index}`"
              @input="updateSource(index, $event.target.value)"
            />
            <p v-else class="truncate text-sm text-gray-500">{{ media.source }}</p>
            <p v-if="errors[`media.${index}.source`]" class="mt-1 text-sm text-red-700">
              {{ errors[`media.${index}.source`] }}
            </p>
          </div>

          <div class="flex shrink-0 gap-2">
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-2 text-sm disabled:opacity-40"
              :disabled="disabled || index === 0"
              :aria-label="`上移第 ${index + 1} 筆媒體`"
              :data-testid="`move-up-${index}`"
              @click="move(index, -1)"
            >↑</button>
            <button
              type="button"
              class="rounded border border-gray-300 px-3 py-2 text-sm disabled:opacity-40"
              :disabled="disabled || index === modelValue.length - 1"
              :aria-label="`下移第 ${index + 1} 筆媒體`"
              :data-testid="`move-down-${index}`"
              @click="move(index, 1)"
            >↓</button>
            <button
              type="button"
              class="rounded border border-red-300 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
              :disabled="disabled"
              :aria-label="`刪除第 ${index + 1} 筆媒體`"
              :data-testid="`remove-media-${index}`"
              @click="remove(index)"
            >刪除</button>
          </div>
        </div>
      </li>
    </ol>

    <p v-else class="rounded-lg bg-gray-50 px-4 py-6 text-center text-sm text-gray-500">
      尚未新增媒體，可只保留文字內容。
    </p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useImageUpload } from '@/composables/useImageUpload'

const props = defineProps({
  modelValue: { type: Array, required: true },
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const batchUploading = ref(false)
const { uploading, imageError, uploadImage } = useImageUpload({ autoUpload: false })
const isUploading = computed(() => batchUploading.value || uploading.value)

function replace(items) {
  emit('update:modelValue', items)
}

function addYouTube() {
  replace([...props.modelValue, { localKey: crypto.randomUUID(), type: 'youtube', source: '' }])
}

async function onFilesSelected(event) {
  const files = Array.from(event.target.files ?? [])
  if (files.length === 0) return

  batchUploading.value = true
  const uploaded = []
  try {
    for (const file of files) {
      const filename = await uploadImage(file, { folder: 'topic-items' })
      uploaded.push({
        localKey: crypto.randomUUID(),
        type: 'image',
        source: `topic-items/${filename}`,
      })
    }
    replace([...props.modelValue, ...uploaded])
  } finally {
    batchUploading.value = false
    event.target.value = ''
  }
}

function updateSource(index, source) {
  const items = props.modelValue.map((media, mediaIndex) => (
    mediaIndex === index ? { ...media, source } : media
  ))
  replace(items)
}

function move(index, offset) {
  const target = index + offset
  if (target < 0 || target >= props.modelValue.length) return

  const items = [...props.modelValue]
  ;[items[index], items[target]] = [items[target], items[index]]
  replace(items)
}

function remove(index) {
  replace(props.modelValue.filter((_, mediaIndex) => mediaIndex !== index))
}
</script>
