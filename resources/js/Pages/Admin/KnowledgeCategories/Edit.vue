<template>
  <Head title="編輯知識分類" />

  <AdminLayout title="編輯知識分類">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯知識分類</h1>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">標題 <span class="text-red-500">*</span></label>
          <input
            v-model="form.title"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-500': errors.title }"
          />
          <p v-if="errors.title" class="mt-1 text-xs text-red-600">{{ errors.title }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Slug（網址用）<span class="text-red-500">*</span></label>
          <input
            v-model="form.slug"
            type="text"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-500': errors.slug }"
            disabled
          />
          <p class="mt-1 text-xs text-gray-500">固定分類，不可更改</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">更換圖片（留空保留原圖）</label>
          <input
            type="file"
            accept="image/*"
            class="block w-full text-sm text-gray-600"
            :disabled="uploading"
            @change="onFileChange"
          />
          <p v-if="uploading" class="mt-1 text-xs text-blue-600">上傳中...</p>
          <p v-if="imageError" class="mt-1 text-xs text-red-600">{{ imageError }}</p>
          <p v-if="category.image_path && !imagePreview" class="mt-1 text-xs text-gray-400">目前：{{ category.image_path }}</p>
          <p v-if="errors.image_path" class="mt-1 text-xs text-red-600">{{ errors.image_path }}</p>
          <div v-if="imagePreview" class="mt-3">
            <img :src="imagePreview" alt="預覽" class="max-h-40 rounded border" />
          </div>
          <div v-else-if="category.image_url" class="mt-3">
            <img :src="category.image_url" alt="目前圖片" class="max-h-40 rounded border" />
          </div>
        </div>

        <div class="flex items-center gap-2 pt-2">
          <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input v-model="form.is_fish_category" type="checkbox" class="rounded" disabled />
            是魚類圖鑑分類
          </label>
          <span class="text-xs text-gray-500">（固定分類，不可更改）</span>
        </div>

        <div class="flex items-center gap-2">
          <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
            <input v-model="form.is_published" type="checkbox" class="rounded" />
            已發布
          </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="submit"
            :disabled="processing || uploading"
            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            儲存變更
          </button>
          <Link href="/admin/knowledge-categories" class="text-sm text-gray-500 hover:text-gray-700">取消</Link>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useImageUpload } from '@/composables/useImageUpload'

const props = defineProps({
  category: Object,
})

const form = reactive({
  title: props.category.title,
  slug: props.category.slug,
  image_path: props.category.image_path ?? '',
  is_fish_category: props.category.is_fish_category,
  is_published: props.category.is_published,
})
const errors = ref({})
const processing = ref(false)

const {
  imagePreview,
  uploading,
  uploadedFilename,
  imageError,
  uploadImage,
} = useImageUpload({ autoUpload: false })

async function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (event) => { imagePreview.value = event.target.result }
  reader.readAsDataURL(file)

  try {
    const filename = await uploadImage(file, { folder: 'knowledge-categories' })
    form.image_path = `knowledge-categories/${filename}`
  } catch (err) {
    errors.value = { ...errors.value, image_path: err.message || '上傳失敗' }
  }
}

function submit() {
  processing.value = true
  errors.value = {}

  const data = {
    title: form.title,
    is_published: form.is_published,
  }
  if (form.image_path) {
    data.image_path = form.image_path
  }

  router.put(`/admin/knowledge-categories/${props.category.id}`, data, {
    onError: (e) => { errors.value = e },
    onFinish: () => { processing.value = false },
  })
}
</script>
