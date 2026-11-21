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
})
