import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CreateFishAudio from '@/Pages/CreateFishAudio.vue'
import { router } from '@inertiajs/vue3'
vi.mock('@inertiajs/vue3', () => ({ router: { visit: vi.fn() } }))
vi.mock('@/utils/fishListCache', () => ({ markFishStale: vi.fn() }))
let wrapper
beforeEach(() => {
  vi.clearAllMocks()
  window.history.replaceState({}, '', '/fish/4/audio/create')
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
async function recordAndSubmit() {
  wrapper = mount(CreateFishAudio, { props: { fish: { id: 4, name: '測試魚' } }, global: { stubs: { LazyImage: true } } })
  await wrapper.findAll('button').find(b => b.text() === '開始錄音').trigger('click')
  await flushPromises()
  await wrapper.findAll('button').find(b => b.text() === '停止錄音').trigger('click')
  await wrapper.findAll('button').find(b => b.text() === '下一步').trigger('click')
  await flushPromises()
}
it('新增發音上傳成功回發音 tab', async () => {
  fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://example.test/upload' }) }).mockResolvedValueOnce({ ok: true })
  await recordAndSubmit()
  expect(router.visit).toHaveBeenCalledWith('/fish/4?tab=audio')
  expect(wrapper.text()).not.toContain('上傳失敗')
})
it('新增發音上傳失敗留頁顯示錯誤', async () => {
  fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ url: 'https://example.test/upload' }) }).mockResolvedValueOnce({ ok: false })
  await recordAndSubmit()
  expect(wrapper.text()).toContain('音訊上傳失敗')
  expect(router.visit).not.toHaveBeenCalled()
})
