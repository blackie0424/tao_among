<template>
  <div class="bg-white p-6 rounded shadow-md max-w-md mx-auto mb-6">
    <div class="mb-4">
      <label for="image" class="block font-semibold mb-2">魚類圖片</label>
      <input
        type="file"
        id="image"
        @change="onFileChange"
        accept="image/*"
        class="w-full border rounded px-3 py-2"
      />
      <div v-if="selectedFile" class="text-gray-700 mt-2">已選擇檔案：{{ selectedFile.name }}</div>
    </div>
    <button
      type="button"
      class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700"
      @click="uploadImage"
      :disabled="!selectedFile || uploading"
    >
      <span v-if="uploading">上傳中...</span>
      <span v-else>上傳圖片</span>
    </button>
    <div v-if="uploadError" class="text-red-600 mt-2">{{ uploadError }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const emit = defineEmits(['uploaded'])

const selectedFile = ref(null)
const uploading = ref(false)
const uploadError = ref('')

function onFileChange(e) {
  selectedFile.value = e.target.files[0]
  uploadError.value = ''
}

async function uploadImage() {
  if (!selectedFile.value) return
  uploading.value = true
  uploadError.value = ''

  const fileName = Date.now() + '_' + selectedFile.value.name.replace(/[^a-zA-Z0-9._-]/g, '_')
  try {
    const res = await fetch('/prefix/api/supabase/signed-upload-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: fileName }),
    })
    const data = await res.json()
    if (!res.ok) {
      // 組合主訊息與所有錯誤細節
      let detail = ''
      if (data.errors) {
        detail = Object.values(data.errors).flat().join('；')
      }
      throw new Error((data.message || '取得上傳網址失敗') + (detail ? `：${detail}` : ''))
    }
    if (!data.url || !data.filename) throw new Error(data.message || '取得上傳網址失敗')

    const uploadRes = await fetch(data.url, {
      method: 'PUT',
      body: selectedFile.value,
    })
    if (!uploadRes.ok) throw new Error('圖片上傳失敗')

    emit('uploaded', data.filename)
  } catch (e) {
    uploadError.value = e.message || '上傳失敗'
  } finally {
    uploading.value = false
  }
}
</script>
