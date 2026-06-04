import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { useImageUpload } from '@/composables/useImageUpload'

global.fetch = vi.fn()

// FileReader mock
const mockFileReaderResult = 'data:image/jpeg;base64,abc'
class MockFileReader {
  constructor() { this.onload = null }
  readAsDataURL() {
    // 同步觸發 onload，方便測試
    setTimeout(() => {
      if (this.onload) this.onload({ target: { result: mockFileReaderResult } })
    }, 0)
  }
}
vi.stubGlobal('FileReader', MockFileReader)

const makeFile = (name = 'fish.jpg') => new File(['x'], name, { type: 'image/jpeg' })

describe('useImageUpload', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ─── 初始狀態 ────────────────────────────────────────────────────────────

  it('初始狀態：imagePreview 為 null', () => {
    const { imagePreview } = useImageUpload()
    expect(imagePreview.value).toBeNull()
  })

  it('初始狀態：uploading 為 false', () => {
    const { uploading } = useImageUpload()
    expect(uploading.value).toBe(false)
  })

  it('初始狀態：uploadedFilename 為 null', () => {
    const { uploadedFilename } = useImageUpload()
    expect(uploadedFilename.value).toBeNull()
  })

  it('初始狀態：imageError 為 null', () => {
    const { imageError } = useImageUpload()
    expect(imageError.value).toBeNull()
  })

  // ─── handleImageChange ───────────────────────────────────────────────────

  it('handleImageChange 設定 imagePreview（透過 FileReader）', async () => {
    const { imagePreview, handleImageChange } = useImageUpload()
    const file = makeFile()
    await handleImageChange({ target: { files: [file] } })
    await new Promise((r) => setTimeout(r, 10))
    expect(imagePreview.value).toBe(mockFileReaderResult)
  })

  it('handleImageChange 在 autoUpload:false 時不呼叫 fetch', async () => {
    const { handleImageChange } = useImageUpload({ autoUpload: false })
    await handleImageChange({ target: { files: [makeFile()] } })
    await new Promise((r) => setTimeout(r, 10))
    expect(global.fetch).not.toHaveBeenCalled()
  })

  it('handleImageChange 在 autoUpload:true 時自動呼叫 apiFetch', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const { handleImageChange } = useImageUpload({ autoUpload: true })
    // autoUpload 模式下 uploadImage 立即觸發（不等待 FileReader 預覽）
    await handleImageChange({ target: { files: [makeFile()] } })

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({ method: 'POST' })
    )
  })

  // ─── uploadImage ─────────────────────────────────────────────────────────

  it('uploadImage：設定 uploading 狀態，成功後回傳 filename', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'result.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const { uploadImage, uploadedFilename, uploading } = useImageUpload()
    const file = makeFile()
    const promise = uploadImage(file)
    expect(uploading.value).toBe(true)

    const filename = await promise
    expect(filename).toBe('result.jpg')
    expect(uploadedFilename.value).toBe('result.jpg')
    expect(uploading.value).toBe(false)
  })

  it('uploadImage：S3 PUT 使用取得的 signed URL', async () => {
    const signedUrl = 'https://s3.example.com/bucket/upload?token=abc'
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: signedUrl, filename: 'result.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const { uploadImage } = useImageUpload()
    await uploadImage(makeFile())

    expect(global.fetch).toHaveBeenNthCalledWith(2, signedUrl, expect.objectContaining({ method: 'PUT' }))
  })

  it('uploadImage：apiFetch 失敗時設定 imageError，uploading 回到 false', async () => {
    global.fetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({ message: '認證失敗' }),
    })

    const { uploadImage, imageError, uploading } = useImageUpload()
    await expect(uploadImage(makeFile())).rejects.toThrow('認證失敗')
    expect(imageError.value).toBe('認證失敗')
    expect(uploading.value).toBe(false)
  })

  it('uploadImage：S3 PUT 失敗時設定 imageError', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'result.jpg' }),
      })
      .mockResolvedValueOnce({ ok: false })

    const { uploadImage, imageError } = useImageUpload()
    await expect(uploadImage(makeFile())).rejects.toThrow('圖片上傳失敗')
    expect(imageError.value).toBe('圖片上傳失敗')
  })

  // ─── removeImage ─────────────────────────────────────────────────────────

  it('removeImage 清除所有圖片狀態', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'result.jpg' }),
      })
      .mockResolvedValueOnce({ ok: true })

    const { uploadImage, uploadedFilename, imagePreview, removeImage } = useImageUpload()
    imagePreview.value = mockFileReaderResult
    await uploadImage(makeFile())

    removeImage()
    expect(imagePreview.value).toBeNull()
    expect(uploadedFilename.value).toBeNull()
  })
})
