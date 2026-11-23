import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { useAudioRecorder, RecordingErrorKind } from '@/composables/useAudioRecorder.js'

function stubBrowser({ supported = ['audio/mp4'], mic = 'ok' } = {}) {
  vi.stubGlobal('MediaRecorder', class {
    static isTypeSupported(type) { return supported.includes(type) }
    start() {}
    stop() { this.ondataavailable({ data: new Blob(['audio']) }); this.onstop() }
  })
  Object.defineProperty(navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia: mic === 'denied' ? () => Promise.reject(new Error('denied')) : async () => ({ getTracks: () => [{ stop() {} }] }) },
  })
  URL.createObjectURL = vi.fn(() => 'blob:audio')
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('useAudioRecorder', () => {
  it('優先用 M4A：錄完得到 audio/mp4 的檔案，副檔名是 m4a', async () => {
    stubBrowser()
    const recorder = useAudioRecorder({ maxSeconds: 30 })
    await recorder.start()
    expect(recorder.isRecording.value).toBe(true)
    recorder.stop()
    expect(recorder.audioBlob.value.type).toBe('audio/mp4')
    expect(recorder.ext.value).toBe('m4a')
    expect(recorder.audioUrl.value).toBe('blob:audio')
  })

  it('不支援 M4A 時降級用 AAC，副檔名是 aac', async () => {
    stubBrowser({ supported: ['audio/aac'] })
    const recorder = useAudioRecorder({ maxSeconds: 30 })
    await recorder.start()
    recorder.stop()
    expect(recorder.audioBlob.value.type).toBe('audio/aac')
    expect(recorder.ext.value).toBe('aac')
  })

  it('兩種格式都不支援：回報不支援並釋放麥克風', async () => {
    stubBrowser({ supported: [] })
    const recorder = useAudioRecorder()
    await recorder.start()
    expect(recorder.errorKind.value).toBe(RecordingErrorKind.UNSUPPORTED)
    expect(recorder.error.value).toContain('不支援錄音格式')
    expect(recorder.isRecording.value).toBe(false)
  })

  it('沒有 MediaRecorder 或麥克風 API：回報不支援', async () => {
    vi.stubGlobal('MediaRecorder', undefined)
    const recorder = useAudioRecorder()
    await recorder.start()
    expect(recorder.errorKind.value).toBe(RecordingErrorKind.UNSUPPORTED)
  })

  it('沒有麥克風權限：回報權限問題', async () => {
    stubBrowser({ mic: 'denied' })
    const recorder = useAudioRecorder()
    await recorder.start()
    expect(recorder.errorKind.value).toBe(RecordingErrorKind.PERMISSION)
    expect(recorder.error.value).toBe('無法取得麥克風權限')
  })

  it('每秒倒數，滿最長秒數自動停止並標記 autoStopped；手動停止不標記', async () => {
    vi.useFakeTimers()
    stubBrowser()
    const auto = useAudioRecorder({ maxSeconds: 5 })
    await auto.start()
    vi.advanceTimersByTime(2000)
    expect(auto.remaining.value).toBe(3)
    vi.advanceTimersByTime(3000)
    await flushPromises()
    expect(auto.isRecording.value).toBe(false)
    expect(auto.autoStopped.value).toBe(true)
    expect(auto.audioBlob.value).not.toBeNull()

    const manual = useAudioRecorder({ maxSeconds: 5 })
    await manual.start()
    manual.stop()
    expect(manual.autoStopped.value).toBe(false)
  })

  it('reset 清掉錄音、錯誤並把倒數歸回最長秒數', async () => {
    stubBrowser()
    const recorder = useAudioRecorder({ maxSeconds: 30 })
    await recorder.start()
    recorder.stop()
    recorder.reset()
    expect(recorder.audioBlob.value).toBeNull()
    expect(recorder.audioUrl.value).toBe('')
    expect(recorder.remaining.value).toBe(30)
  })

  it('錄音中 reset：之後才結束的錄音不會寫回，麥克風也釋放', async () => {
    const stopped = vi.fn()
    vi.stubGlobal('MediaRecorder', class {
      static isTypeSupported() { return true }
      start() {}
      stop() { Promise.resolve().then(() => { this.ondataavailable({ data: new Blob(['audio']) }); this.onstop() }) }
    })
    Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => ({ getTracks: () => [{ stop: stopped }] }) } })
    URL.createObjectURL = vi.fn(() => 'blob:audio')
    const recorder = useAudioRecorder({ maxSeconds: 30 })
    await recorder.start()
    recorder.reset()
    await flushPromises()
    expect(recorder.audioBlob.value).toBeNull()
    expect(recorder.audioUrl.value).toBe('')
    expect(recorder.isRecording.value).toBe(false)
    expect(stopped).toHaveBeenCalled()
  })

  it('等麥克風授權時就 reset：授權通過也不會開始錄音，並釋放麥克風', async () => {
    const stopped = vi.fn()
    let allow
    vi.stubGlobal('MediaRecorder', class {
      static isTypeSupported() { return true }
      start() { throw new Error('不該開始錄音') }
    })
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: () => new Promise((resolve) => { allow = () => resolve({ getTracks: () => [{ stop: stopped }] }) }) },
    })
    const recorder = useAudioRecorder({ maxSeconds: 30 })
    const starting = recorder.start()
    recorder.reset()
    allow()
    await starting
    expect(recorder.isRecording.value).toBe(false)
    expect(recorder.audioBlob.value).toBeNull()
    expect(stopped).toHaveBeenCalled()
  })

  it('取消的錄音延遲送來的片段與結束事件，不會混進緊接著開始的新錄音', async () => {
    const recorders = []
    vi.stubGlobal('MediaRecorder', class {
      static isTypeSupported() { return true }
      constructor() { recorders.push(this) }
      start() {}
      stop() {}
    })
    const tracks = [{ stop: vi.fn() }, { stop: vi.fn() }]
    let opened = 0
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: { getUserMedia: async () => ({ getTracks: () => [tracks[opened++]] }) },
    })
    URL.createObjectURL = vi.fn(() => 'blob:audio')
    const recorder = useAudioRecorder({ maxSeconds: 30 })

    await recorder.start() // 錄音 A
    recorder.reset()
    await recorder.start() // 錄音 B
    const [a, b] = recorders
    a.ondataavailable({ data: new Blob(['AAAA']) }) // A 的延遲片段與結束事件在 B 錄音中才到
    a.onstop()
    expect(recorder.isRecording.value).toBe(true)
    expect(recorder.audioBlob.value).toBeNull()
    b.ondataavailable({ data: new Blob(['BB']) })
    recorder.stop()
    b.onstop()

    expect(recorder.audioBlob.value.size).toBe(2)
    expect(tracks[0].stop).toHaveBeenCalled()
  })

  it('記下錄了幾秒：手動停止是實際秒數，滿時間自動停止是最長秒數，reset 後歸零', async () => {
    vi.useFakeTimers()
    stubBrowser()
    const manual = useAudioRecorder({ maxSeconds: 30 })
    await manual.start()
    vi.advanceTimersByTime(7000)
    manual.stop()
    expect(manual.recordedSeconds.value).toBe(7)
    expect(manual.remaining.value).toBe(0)

    const auto = useAudioRecorder({ maxSeconds: 5 })
    await auto.start()
    vi.advanceTimersByTime(5000)
    await flushPromises()
    expect(auto.recordedSeconds.value).toBe(5)

    manual.reset()
    expect(manual.recordedSeconds.value).toBe(0)
  })
})
