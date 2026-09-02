<template>
  <Head title="編輯投影片" />

  <AdminLayout title="編輯投影片">
    <div class="mx-auto max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h1 class="mb-6 text-2xl font-bold text-gray-900">編輯首頁投影片</h1>

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
          <label class="block text-sm font-medium text-gray-700 mb-1">說明文字</label>
          <textarea
            v-model="form.body"
            rows="3"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">分類</label>
          <select
            v-model="form.category_id"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option :value="null">無分類</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">媒體類型 <span class="text-red-500">*</span></label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="form.media_type" type="radio" value="photo" /> 圖片
            </label>
            <label class="flex items-center gap-2 text-sm cursor-pointer">
              <input v-model="form.media_type" type="radio" value="youtube" /> YouTube
            </label>
          </div>
        </div>

        <div v-if="form.media_type === 'photo'">
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
          <p v-if="slide.media_path && !imagePreview" class="mt-1 text-xs text-gray-400">目前：{{ slide.media_path }}</p>
          <p v-if="errors.photo" class="mt-1 text-xs text-red-600">{{ errors.photo }}</p>
          <div v-if="imagePreview" class="mt-3">
            <img :src="imagePreview" alt="預覽" class="max-h-40 rounded border" />
          </div>
        </div>

        <div v-if="form.media_type === 'youtube'">
          <label class="block text-sm font-medium text-gray-700 mb-1">YouTube 網址</label>
          <input
            v-model="form.media_path"
            type="url"
            placeholder="https://www.youtube.com/watch?v=..."
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'border-red-500': errors.media_path }"
          />
          <p v-if="errors.media_path" class="mt-1 text-xs text-red-600">{{ errors.media_path }}</p>
        </div>

        <div class="flex gap-4">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">排序</label>
            <input
              v-model.number="form.sort_order"
              type="number"
              min="0"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="flex items-end pb-1">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
              <input v-model="form.is_published" type="checkbox" class="rounded" />
              已發布
            </label>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button
            type="submit"
            :disabled="processing"
            class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            儲存變更
          </button>
          <Link href="/admin/intro-slides" class="text-sm text-gray-500 hover:text-gray-700">取消</Link>
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
  slide: Object,
  categories: Array,
})

const form = reactive({
  title: props.slide.title,
  body: props.slide.body ?? '',
  category_id: props.slide.category_id ?? null,
  media_type: props.slide.media_type,
  media_path: props.slide.media_path ?? '',
  sort_order: props.slide.sort_order,
  is_published: props.slide.is_published,
})
const errors = ref({})
const processing = ref(false)

// 使用 useImageUpload composable
const {
  imagePreview,
  uploading,
  uploadedFilename,
  imageError,
} = useImageUpload({ autoUpload: false })

async function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return

  // 建立預覽
  const reader = new FileReader()
  reader.onload = (event) => { imagePreview.value = event.target.result }
  reader.readAsDataURL(file)

  // 上傳圖片到 intro-slides 資料夾
  try {
    const filename = await uploadToIntroSlides(file)
    form.media_path = `intro-slides/${filename}`
  } catch (err) {
    errors.value = { ...errors.value, photo: err.message || '上傳失敗' }
  }
}

async function uploadToIntroSlides(file) {
  uploading.value = true
  imageError.value = null
  try {
    const signedUrlRes = await fetch('/prefix/api/storage/signed-upload-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: file.name, folder: 'intro-slides' }),
    })
    const signedUrlData = await signedUrlRes.json()
    if (!signedUrlRes.ok) {
      throw new Error(signedUrlData.message || '取得上傳網址失敗')
    }

    const uploadRes = await fetch(signedUrlData.url, {
      method: 'PUT',
      body: file,
    })
    if (!uploadRes.ok) {
      throw new Error('圖片上傳失敗')
    }

    uploadedFilename.value = signedUrlData.filename
    return signedUrlData.filename
  } catch (e) {
    imageError.value = e.message || '上傳失敗'
    throw e
  } finally {
    uploading.value = false
  }
}

function submit() {
  processing.value = true
  errors.value = {}

  const data = {
    title: form.title,
    body: form.body || '',
    media_type: form.media_type,
    media_path: form.media_path,
    sort_order: form.sort_order,
    is_published: form.is_published,
  }
  if (form.category_id) {
    data.category_id = form.category_id
  }

  router.put(`/admin/intro-slides/${props.slide.id}`, data, {
    onError: (e) => { errors.value = e },
    onFinish: () => { processing.value = false },
  })
}
</script>
