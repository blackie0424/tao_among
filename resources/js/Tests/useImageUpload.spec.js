import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useImageUpload } from '@/composables/useImageUpload'

// Mock apiFetch
vi.mock('@/utils/apiFetch', () => ({
  apiFetch: vi.fn(),
}))

// Mock global fetch
global.fetch = vi.fn()

describe('useImageUpload composable', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('uploadImage sends CSRF token via apiFetch', async () => {
    const { apiFetch } = await import('@/utils/apiFetch')
    
    // Mock successful signed URL response
    apiFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        url: 'https://s3.example.com/signed-url',
        filename: 'test-uuid.jpg',
        path: 'images/test-uuid.jpg',
      }),
    })

    // Mock successful S3 upload
    global.fetch.mockResolvedValueOnce({ ok: true })

    const { uploadImage } = useImageUpload()
    const mockFile = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

    await uploadImage(mockFile)

    // Verify apiFetch was called (which includes CSRF token)
    expect(apiFetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ filename: 'test.jpg' }),
      })
    )
  })

  it('uploadImage supports folder parameter', async () => {
    const { apiFetch } = await import('@/utils/apiFetch')
    
    apiFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        url: 'https://s3.example.com/signed-url',
        filename: 'slide-uuid.jpg',
        path: 'intro-slides/slide-uuid.jpg',
      }),
    })

    global.fetch.mockResolvedValueOnce({ ok: true })

    const { uploadImage } = useImageUpload()
    const mockFile = new File(['test'], 'slide.jpg', { type: 'image/jpeg' })

    await uploadImage(mockFile, { folder: 'intro-slides' })

    // Verify folder parameter is passed
    expect(apiFetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ 
          filename: 'slide.jpg',
          folder: 'intro-slides',
        }),
      })
    )
  })

  it('uploadImage returns filename on success', async () => {
    const { apiFetch } = await import('@/utils/apiFetch')
    
    apiFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        url: 'https://s3.example.com/signed-url',
        filename: 'returned-filename.jpg',
        path: 'images/returned-filename.jpg',
      }),
    })

    global.fetch.mockResolvedValueOnce({ ok: true })

    const { uploadImage } = useImageUpload()
    const mockFile = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

    const result = await uploadImage(mockFile)

    expect(result).toBe('returned-filename.jpg')
  })

  it('uploadImage throws error when signed URL request fails', async () => {
    const { apiFetch } = await import('@/utils/apiFetch')
    
    apiFetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({ message: '驗證失敗' }),
    })

    const { uploadImage } = useImageUpload()
    const mockFile = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

    await expect(uploadImage(mockFile)).rejects.toThrow('驗證失敗')
  })

  it('uploadImage throws error when S3 upload fails', async () => {
    const { apiFetch } = await import('@/utils/apiFetch')
    
    apiFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        url: 'https://s3.example.com/signed-url',
        filename: 'test-uuid.jpg',
        path: 'images/test-uuid.jpg',
      }),
    })

    global.fetch.mockResolvedValueOnce({ ok: false })

    const { uploadImage } = useImageUpload()
    const mockFile = new File(['test'], 'test.jpg', { type: 'image/jpeg' })

    await expect(uploadImage(mockFile)).rejects.toThrow('圖片上傳失敗')
  })
})
