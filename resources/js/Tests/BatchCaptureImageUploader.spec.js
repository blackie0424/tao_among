import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BatchCaptureImageUploader from '@/Components/CaptureRecord/BatchCaptureImageUploader.vue'

// Mock fetch
global.fetch = vi.fn()

// Helper：建立假 File 物件
function makeFile(name = 'fish.jpg', type = 'image/jpeg', size = 1024) {
  const file = new File(['x'.repeat(size)], name, { type })
  return file
}

// Helper：建立假 FileList（直接回傳 File 陣列，測試時透過 addFiles 注入）
function makeFileList(files) {
  return files
}

describe('BatchCaptureImageUploader', () => {
  const defaultProps = {
    maxFiles: 5,
  }

  beforeEach(() => {
    vi.clearAllMocks()
    // 預設 signed URL 回應
    global.fetch.mockResolvedValue({
      ok: true,
      json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded_fish.jpg' }),
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  // ==================== 渲染 ====================

  it('預設顯示上傳區域', () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    expect(wrapper.find('[data-testid="upload-area"]').exists()).toBe(true)
  })

  it('尚未選擇照片時顯示空清單訊息', () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    expect(wrapper.find('[data-testid="empty-hint"]').exists()).toBe(true)
  })

  it('顯示目前已選 / 上限數量', () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    expect(wrapper.text()).toContain('0')
    expect(wrapper.text()).toContain('5')
  })

  // ==================== 選檔 ====================

  it('選擇單張圖片後出現縮圖', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    expect(wrapper.findAll('[data-testid="thumbnail-item"]').length).toBe(1)
  })

  it('選擇多張圖片後全部出現縮圖', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg'), makeFile('b.jpg'), makeFile('c.jpg')])
    await nextTick()

    expect(wrapper.findAll('[data-testid="thumbnail-item"]').length).toBe(3)
  })

  it('超過上限時忽略多餘的檔案', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: { maxFiles: 2 } })
    await wrapper.vm.addFiles([makeFile('a.jpg'), makeFile('b.jpg'), makeFile('c.jpg')])
    await nextTick()

    expect(wrapper.findAll('[data-testid="thumbnail-item"]').length).toBe(2)
  })

  it('達到上限時隱藏「繼續加入」按鈕', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: { maxFiles: 1 } })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    expect(wrapper.find('[data-testid="add-more-btn"]').exists()).toBe(false)
  })

  it('未達上限時顯示「繼續加入」按鈕', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: { maxFiles: 3 } })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    expect(wrapper.find('[data-testid="add-more-btn"]').exists()).toBe(true)
  })

  // ==================== 移除 ====================

  it('點擊移除按鈕可刪除對應縮圖', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg'), makeFile('b.jpg')])
    await nextTick()

    const removeBtn = wrapper.find('[data-testid="remove-btn"]')
    await removeBtn.trigger('click')
    await nextTick()

    expect(wrapper.findAll('[data-testid="thumbnail-item"]').length).toBe(1)
  })

  // ==================== 上傳 ====================

  it('呼叫 uploadAll 後觸發 signed URL 請求', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    await wrapper.vm.uploadAll()

    expect(global.fetch).toHaveBeenCalledWith(
      '/prefix/api/storage/signed-upload-url',
      expect.objectContaining({ method: 'POST' })
    )
  })

  it('uploadAll 成功後 emit uploaded 事件並帶入檔名陣列', async () => {
    // signed URL 請求
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded_a.jpg' }),
      })
      // PUT 上傳至 S3
      .mockResolvedValueOnce({ ok: true })

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    await wrapper.vm.uploadAll()

    expect(wrapper.emitted('uploaded')).toBeTruthy()
    expect(wrapper.emitted('uploaded')[0][0]).toEqual(['uploaded_a.jpg'])
  })

  it('上傳失敗時 emit upload-error 事件', async () => {
    global.fetch
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ url: 'https://s3.example.com/upload', filename: 'uploaded_a.jpg' }),
      })
      .mockResolvedValueOnce({ ok: false }) // PUT 失敗

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    await wrapper.vm.uploadAll()

    expect(wrapper.emitted('upload-error')).toBeTruthy()
  })

  it('上傳中顯示進度狀態', async () => {
    // 讓 fetch 永遠 pending（不 resolve）
    global.fetch.mockImplementation(() => new Promise(() => {}))

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    await wrapper.vm.addFiles([makeFile('a.jpg')])
    await nextTick()

    wrapper.vm.uploadAll() // 不 await，保持 pending
    await nextTick()

    expect(wrapper.find('[data-testid="uploading-indicator"]').exists()).toBe(true)
  })

  // ==================== LINE 降級模式 ====================

  it('isLineApp=true 時 file input 不帶 multiple 屬性', () => {
    const wrapper = mount(BatchCaptureImageUploader, {
      props: { ...defaultProps, isLineApp: true },
    })
    const input = wrapper.find('[data-testid="file-input"]')
    expect(input.attributes('multiple')).toBeUndefined()
  })

  it('isLineApp=false 時 file input 帶 multiple 屬性', () => {
    const wrapper = mount(BatchCaptureImageUploader, {
      props: { ...defaultProps, isLineApp: false },
    })
    const input = wrapper.find('[data-testid="file-input"]')
    expect(input.attributes('multiple')).toBeDefined()
  })

  // ==================== HEIC 轉檔 ====================

  it('加入 HEIC 檔案時 item.isHeic 為 true', async () => {
    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    const heicFile = makeFile('photo.HEIC', 'image/heic')
    await wrapper.vm.addFiles([heicFile])
    await nextTick()

    expect(wrapper.vm.items[0].isHeic).toBe(true)
  })

  it('uploadAll 時 HEIC 檔案會被轉換後再上傳', async () => {
    const convertedBlob = new Blob(['converted'], { type: 'image/jpeg' })
    window.heic2any = vi.fn().mockResolvedValue(convertedBlob)

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    const heicFile = makeFile('photo.heic', 'image/heic')
    await wrapper.vm.addFiles([heicFile])
    await nextTick()

    await wrapper.vm.uploadAll()

    // heic2any 應被呼叫
    expect(window.heic2any).toHaveBeenCalledWith({ blob: heicFile, toType: 'image/jpeg' })
    // 上傳時使用的 filename 應為 .jpg
    const bodyArg = JSON.parse(global.fetch.mock.calls[0][1].body)
    expect(bodyArg.filename).toBe('photo.jpg')
    // 上傳成功後 emit uploaded
    expect(wrapper.emitted('uploaded')).toBeTruthy()

    delete window.heic2any
  })

  it('uploadAll 時若 heic2any 未載入則 emit upload-error', async () => {
    delete window.heic2any

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    const heicFile = makeFile('photo.heic', 'image/heic')
    await wrapper.vm.addFiles([heicFile])
    await nextTick()

    await wrapper.vm.uploadAll()

    expect(wrapper.emitted('upload-error')).toBeTruthy()
    expect(wrapper.vm.items[0].status).toBe('error')
  })

  it('uploadAll 時 heic2any 轉檔失敗則 emit upload-error', async () => {
    window.heic2any = vi.fn().mockRejectedValue(new Error('轉檔失敗'))

    const wrapper = mount(BatchCaptureImageUploader, { props: defaultProps })
    const heicFile = makeFile('photo.heic', 'image/heic')
    await wrapper.vm.addFiles([heicFile])
    await nextTick()

    await wrapper.vm.uploadAll()

    expect(wrapper.emitted('upload-error')).toBeTruthy()
    expect(wrapper.vm.items[0].status).toBe('error')

    delete window.heic2any
  })
})
