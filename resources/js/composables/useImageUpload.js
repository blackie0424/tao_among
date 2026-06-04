import { ref } from 'vue'
import { apiFetch } from '@/utils/apiFetch'

/**
 * @param {{ autoUpload?: boolean }} options
 */
export function useImageUpload({ autoUpload = false } = {}) {
  const imagePreview = ref(null)
  const uploading = ref(false)
  const uploadedFilename = ref(null)
  const imageError = ref(null)

  function handleImageChange(event) {
    const file = event.target.files?.[0]
    if (!file) return Promise.resolve()

    // 建立預覽（非阻塞）
    const reader = new FileReader()
    reader.onload = (e) => { imagePreview.value = e.target.result }
    reader.readAsDataURL(file)

    if (autoUpload) {
      return uploadImage(file)
    }
    return Promise.resolve()
  }

  async function uploadImage(file) {
    uploading.value = true
    imageError.value = null
    try {
      const signedUrlRes = await apiFetch('/prefix/api/storage/signed-upload-url', {
        method: 'POST',
        body: JSON.stringify({ filename: file.name }),
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

  function removeImage() {
    imagePreview.value = null
    uploadedFilename.value = null
    imageError.value = null
  }

  return {
    imagePreview,
    uploading,
    uploadedFilename,
    imageError,
    handleImageChange,
    uploadImage,
    removeImage,
  }
}
