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

    <div v-if="uploadError" class="text-red-600 mt-2">{{ uploadError }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const emit = defineEmits(['uploaded'])

const selectedFile = ref(null)
const uploading = ref(false)
const uploadError = ref('')

// 讓父元件可以直接存取 selectedFile 與 uploadError
defineExpose({ uploadImage, selectedFile, uploadError })

function onFileChange(e) {
  const file = e.target.files[0]
  uploadError.value = ''
  if (file && file.name.toLowerCase().endsWith('.heic')) {
    uploadError.value = '偵測到 HEIC 檔案，將自動轉換為 JPEG 上傳。'
    selectedFile.value = file
  } else {
    selectedFile.value = file
  }
}

async function uploadImage() {
  if (!selectedFile.value) return
  uploading.value = true
  uploadError.value = ''

  let uploadFile = selectedFile.value
  let fileName = Date.now() + '_' + uploadFile.name.replace(/[^a-zA-Z0-9._-]/g, '_')

  // HEIC 自動轉檔流程（使用 CDN 方式）
  if (uploadFile.name.toLowerCase().endsWith('.heic')) {
    try {
      if (!window.heic2any) {
        uploadError.value = '找不到 heic2any 函式庫，請確認 CDN 已載入。'
        uploading.value = false
        return
      }
      const convertedBlob = await window.heic2any({ blob: uploadFile, toType: 'image/jpeg' })
      fileName = fileName.replace(/\.heic$/i, '.jpg')
      uploadFile = new File([convertedBlob], fileName, { type: 'image/jpeg' })
    } catch (err) {
      uploadError.value = 'HEIC 轉檔失敗，請手動轉換後再上傳。'
      uploading.value = false
      return
    }
  }

  try {
    const res = await fetch('/prefix/api/storage/signed-upload-url', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ filename: fileName }),
    })
    const data = await res.json()
    if (!res.ok) {
      let detail = ''
      if (data.errors) {
        detail = Object.values(data.errors).flat().join('；')
      }
      throw new Error((data.message || '取得上傳網址失敗') + (detail ? `：${detail}` : ''))
    }
    if (!data.url || !data.filename) throw new Error(data.message || '取得上傳網址失敗')

    const uploadRes = await fetch(data.url, {
      method: 'PUT',
      body: uploadFile,
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
