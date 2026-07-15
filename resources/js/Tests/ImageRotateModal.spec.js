import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ImageRotateModal from '@/Components/UI/ImageRotateModal.vue'

// Mock Teleport to render inline for testing
const Teleport = { template: '<slot />' }

// Mock fetch
global.fetch = vi.fn()

// Mock canvas
function makeCanvasMock() {
  const ctx = {
    clearRect: vi.fn(),
    translate: vi.fn(),
    rotate: vi.fn(),
    drawImage: vi.fn(),
  }
  const canvas = {
    getContext: vi.fn(() => ctx),
    toBlob: vi.fn((cb, type) => cb(new Blob(['data'], { type }))),
    toDataURL: vi.fn(() => 'data:image/jpeg;base64,mock'),
    width: 0,
    height: 0,
  }
  return { canvas, ctx }
}

// Mock Image loading
global.Image = class {
  constructor() {
    this.naturalWidth = 100
    this.naturalHeight = 80
    setTimeout(() => this.onload?.(), 0)
  }
}

// Mock document.cookie
Object.defineProperty(document, 'cookie', {
  get: () => 'XSRF-TOKEN=test-token',
  configurable: true,
})

describe('ImageRotateModal', () => {
  const defaultProps = {
    open: true,
    imageUrl: 'https://example.com/fish.jpg',
    fishId: 1,
    recordId: 42,
    mimeType: 'image/jpeg',
  }

  beforeEach(() => {
    vi.clearAllMocks()
    global.fetch.mockResolvedValue({ ok: true, json: async () => ({ message: 'success' }) })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('closed 時不渲染 modal 內容', () => {
    const wrapper = mount(ImageRotateModal, {
      props: { ...defaultProps, open: false },
      global: { stubs: { Teleport } },
    })
    expect(wrapper.find('h3').exists()).toBe(false)
  })

  it('open 時顯示旋轉圖片標題', async () => {
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()
    expect(wrapper.find('h3').text()).toBe('旋轉圖片')
  })

  it('顯示三個旋轉按鈕', async () => {
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()
    const buttons = wrapper.findAll('button').filter((b) =>
      ['逆時針 90°', '180°', '順時針 90°'].some((t) => b.text().includes(t))
    )
    expect(buttons.length).toBe(3)
  })

  it('未旋轉時確認按鈕為 disabled', async () => {
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()
    const confirmBtn = wrapper.findAll('button').find((b) => b.text().includes('確認旋轉'))
    expect(confirmBtn?.attributes('disabled')).toBeDefined()
  })

  it('點擊取消按鈕 emit close', async () => {
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()
    await wrapper.findAll('button').find((b) => b.text().includes('取消'))?.trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('recordId 存在時 API 呼叫捕獲紀錄路由', async () => {
    const { canvas } = makeCanvasMock()
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()

    // Inject mock canvas
    wrapper.vm.canvasRef = canvas
    wrapper.vm.totalRotation = 90

    await wrapper.vm.confirm()
    await flushPromises()

    const [url] = global.fetch.mock.calls[0]
    expect(url).toContain('/capture-records/42/image/rotate')
  })

  it('recordId 為 null 時 API 呼叫魚類主圖路由', async () => {
    const { canvas } = makeCanvasMock()
    const wrapper = mount(ImageRotateModal, {
      props: { ...defaultProps, recordId: null },
      global: { stubs: { Teleport } },
    })
    await flushPromises()

    wrapper.vm.canvasRef = canvas
    wrapper.vm.totalRotation = 90

    await wrapper.vm.confirm()
    await flushPromises()

    const [url] = global.fetch.mock.calls[0]
    expect(url).toBe('/prefix/api/fish/1/image/rotate')
  })

  it('API 成功後 emit rotated 與 close', async () => {
    const { canvas } = makeCanvasMock()
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()

    wrapper.vm.canvasRef = canvas
    wrapper.vm.totalRotation = 90

    await wrapper.vm.confirm()
    await flushPromises()

    expect(wrapper.emitted('rotated')).toBeTruthy()
    expect(wrapper.emitted('rotated')[0][0]).toContain('?t=')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('API 失敗時顯示錯誤訊息', async () => {
    global.fetch.mockResolvedValue({
      ok: false,
      json: async () => ({ message: '旋轉失敗' }),
    })
    const { canvas } = makeCanvasMock()
    const wrapper = mount(ImageRotateModal, {
      props: defaultProps,
      global: { stubs: { Teleport } },
    })
    await flushPromises()

    wrapper.vm.canvasRef = canvas
    wrapper.vm.totalRotation = 90

    await wrapper.vm.confirm()
    await flushPromises()

    expect(wrapper.text()).toContain('旋轉失敗')
    expect(wrapper.emitted('close')).toBeFalsy()
  })
})
