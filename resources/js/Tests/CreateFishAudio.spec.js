import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CreateFishAudio from '@/Pages/CreateFishAudio.vue'
import { router } from '@inertiajs/vue3'
vi.mock('@/Components/UI/Volume.vue', () => ({ default: { template: '<span data-testid="volume" />' } }))

vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<div />' },
  Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
  router: { visit: vi.fn() },
}))
vi.mock('@/utils/fishListCache', () => ({ markFishStale: vi.fn() }))

const TRIBES = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley']
const Layout = { props: ['mobileBackUrl', 'mobileBackText'], template: '<main><a data-testid="back" :href="mobileBackUrl">{{ mobileBackText }}</a><slot /></main>' }
let wrapper

beforeEach(() => {
  vi.clearAllMocks()
  vi.stubGlobal('fetch', vi.fn())
  vi.stubGlobal('MediaRecorder', class {
    static isTypeSupported() { return true }
    start() {}
    stop() { this.ondataavailable({ data: new Blob(['audio']) }); this.onstop() }
  })
  Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => ({ getTracks: () => [{ stop() {} }] }) } })
  vi.stubGlobal('AudioContext', class {
    createAnalyser() { return { getByteTimeDomainData() {} } }
    createMediaStreamSource() { return { connect() {} } }
    close() {}
  })
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ clearRect() {}, fillRect() {}, beginPath() {}, moveTo() {}, lineTo() {}, stroke() {} })
  URL.createObjectURL = vi.fn(() => 'blob:audio')
  URL.revokeObjectURL = vi.fn()
})
afterEach(() => { wrapper?.unmount(); vi.restoreAllMocks(); vi.unstubAllGlobals() })

const mountPage = (props = {}) => {
  wrapper = mount(CreateFishAudio, {
    props: { fish: { id: 4, name: 'mivazlt、mivanivang' }, names: ['mivazlt', 'mivanivang'], spokenName: 'mivazlt', tribes: TRIBES, maxSeconds: 5, ...props },
    global: { stubs: { FishAppLayout: Layout } },
  })
  return wrapper
}
const button = (label) => wrapper.findAll('button').find((b) => b.attributes('aria-label') === label || b.text() === label)
async function record() {
  await button('開始錄音').trigger('click')
  await flushPromises()
  await button('停止錄音').trigger('click')
  await flushPromises()
}
async function recordAndSave(tribe = 'yayo') {
  await wrapper.get(`[data-tribe="${tribe}"]`).trigger('click')
  await record()
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}
const signedOk = () => ({ ok: true, json: async () => ({ url: 'https://example.test/upload', filename: '11111111-2222-3333-4444-555555555555.m4a' }) })

describe('新增發音頁', () => {
  it('標題是「{名稱}・新增發音」，返回回管理發音頁', () => {
    mountPage()
    expect(wrapper.get('h1').text()).toBe('mivazlt・新增發音')
    expect(wrapper.get('[data-testid="back"]').attributes('href')).toBe('/fish/4/audio-list')
  })

  it('錄音後依序：取得暫存簽名 → PUT → 確認（帶名稱、部落、長度），成功回管理發音頁', async () => {
    fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: true, json: async () => ({ id: 9 }) })
    mountPage()

    await recordAndSave('iratay')

    expect(fetch.mock.calls[0][0]).toBe('/prefix/api/fish/4/storage/signed-upload-audio-url')
    expect(fetch.mock.calls[1][0]).toBe('https://example.test/upload')
    expect(fetch.mock.calls[1][1].method).toBe('PUT')
    const [confirmUrl, confirmOptions] = fetch.mock.calls[2]
    expect(confirmUrl).toBe('/prefix/api/fish/4/audio/confirm')
    expect(JSON.parse(confirmOptions.body)).toMatchObject({
      filename: '11111111-2222-3333-4444-555555555555.m4a',
      spoken_name: 'mivazlt',
      tribe: 'iratay',
    })
    expect(JSON.parse(confirmOptions.body).duration_ms).toBeGreaterThan(0)
    expect(router.visit).toHaveBeenCalledWith('/fish/4/audio-list')
  })

  it('瀏覽器只支援 AAC 時：錄成 .aac，簽名、確認都帶 aac 檔名', async () => {
    vi.stubGlobal('MediaRecorder', class {
      static isTypeSupported(type) { return type === 'audio/aac' }
      constructor(stream, options) { this.mimeType = options?.mimeType }
      start() {}
      stop() { this.ondataavailable({ data: new Blob(['audio'], { type: 'audio/aac' }) }); this.onstop() }
    })
    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://example.test/upload', filename: '11111111-2222-3333-4444-555555555555.aac' }) })
      .mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: true, json: async () => ({}) })
    mountPage()

    await recordAndSave()

    expect(JSON.parse(fetch.mock.calls[0][1].body).filename).toMatch(/\.aac$/)
    expect(JSON.parse(fetch.mock.calls[2][1].body).filename).toBe('11111111-2222-3333-4444-555555555555.aac')
    expect(router.visit).toHaveBeenCalledWith('/fish/4/audio-list')
  })

  it('請求帶 CSRF token 與 JSON 標頭', async () => {
    document.cookie = 'XSRF-TOKEN=' + encodeURIComponent('token-abc=') + '; path=/'
    fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: true, json: async () => ({}) })
    mountPage()

    await recordAndSave()

    const [, options] = fetch.mock.calls[0]
    expect(options.headers['X-XSRF-TOKEN']).toBe('token-abc=')
    expect(options.headers['Accept']).toBe('application/json')
  })

  it('沒選部落或沒錄音不會送出', async () => {
    mountPage()
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('請選擇部落')

    await wrapper.get('[data-tribe="yayo"]').trigger('click')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('請先錄音')
    expect(fetch).not.toHaveBeenCalled()
  })

  it('上傳失敗留在頁面顯示錯誤，不導頁', async () => {
    fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: false })
    mountPage()

    await recordAndSave()

    expect(wrapper.text()).toContain('音訊上傳失敗')
    expect(router.visit).not.toHaveBeenCalled()
  })

  it('確認被伺服器拒絕時顯示伺服器的訊息', async () => {
    fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({ ok: false, json: async () => ({ errors: { recording: ['這段錄音已使用過，請重新錄音'] } }) })
    mountPage()

    await recordAndSave()

    expect(wrapper.text()).toContain('這段錄音已使用過，請重新錄音')
    expect(router.visit).not.toHaveBeenCalled()
  })

  describe('從魚頁麥克風進來（from=fish，#176 AC 5）', () => {
    it('返回鈕回魚頁、文字「回魚類資料」', () => {
      mountPage({ from: 'fish' })
      expect(wrapper.get('[data-testid="back"]').attributes('href')).toBe('/fish/4')
      expect(wrapper.get('[data-testid="back"]').text()).toBe('回魚類資料')
    })

    it('存檔成功回魚頁（不帶 tab），不是管理發音頁', async () => {
      fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: true, json: async () => ({ id: 9 }) })
      mountPage({ from: 'fish' })

      await recordAndSave()

      expect(router.visit).toHaveBeenCalledTimes(1)
      expect(router.visit).toHaveBeenCalledWith('/fish/4')
    })

    it('多個拼字沒帶名稱時仍先選拼字，選完存檔回魚頁', async () => {
      fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: true }).mockResolvedValueOnce({ ok: true, json: async () => ({ id: 9 }) })
      mountPage({ from: 'fish', spokenName: null })
      expect(wrapper.find('form').exists()).toBe(false)

      await wrapper.findAll('button').find((b) => b.text() === 'mivanivang').trigger('click')
      await recordAndSave()

      expect(JSON.parse(fetch.mock.calls[2][1].body).spoken_name).toBe('mivanivang')
      expect(router.visit).toHaveBeenCalledWith('/fish/4')
    })

    it('上傳失敗時留在頁面，不導向魚頁', async () => {
      fetch.mockResolvedValueOnce(signedOk()).mockResolvedValueOnce({ ok: false })
      mountPage({ from: 'fish' })

      await recordAndSave()

      expect(router.visit).not.toHaveBeenCalled()
    })
  })

  it('沒帶名稱而魚有多個名稱時先請使用者選名稱', async () => {
    mountPage({ spokenName: null })
    expect(wrapper.find('form').exists()).toBe(false)

    await wrapper.findAll('button').find((b) => b.text() === 'mivanivang').trigger('click')

    expect(wrapper.get('h1').text()).toBe('mivanivang・新增發音')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('只有一個名稱時自動帶入', () => {
    mountPage({ fish: { id: 4, name: 'Angsa' }, names: ['Angsa'], spokenName: null })
    expect(wrapper.get('h1').text()).toBe('Angsa・新增發音')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('魚名為空時提示先編輯名稱，不顯示表單', () => {
    mountPage({ fish: { id: 4, name: '' }, names: [], spokenName: null })
    expect(wrapper.text()).toContain('這隻魚還沒有名稱')
    expect(wrapper.get('a[href="/fish/4/edit"]').text()).toBe('編輯名稱')
    expect(wrapper.find('form').exists()).toBe(false)
  })
})
